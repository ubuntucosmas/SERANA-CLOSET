<?php

namespace App\Console\Commands;

use App\Models\CustomOrder;
use App\Models\PaymentAttempt;
use App\Services\MpesaService;
use App\Services\OrderSettlementService;
use App\Support\OrderStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safaricom STK callbacks fail to arrive far more often than people expect. This
 * job actively asks Daraja for the final state of every STK push that never got
 * a callback, so paying customers aren't stranded in "Pending Payment".
 *
 * It resolves individual PaymentAttempts (not just an order's latest push), and
 * deliberately includes EXPIRED orders: a customer can complete the prompt after
 * the hold lapsed, and money taken must always settle or surface.
 */
class ReconcilePendingPayments extends Command
{
    protected $signature = 'mpesa:reconcile';
    protected $description = 'Resolve pending M-Pesa payments via STK Query when the callback never arrived';

    /** Daraja result codes that mean the push is definitively not getting paid. */
    private const FAILURE_CODES = [1032, 1037, 1025, 1, 2001];

    public function handle(MpesaService $mpesa, OrderSettlementService $settlement): int
    {
        // Daraja spike-arrests the STK Query endpoint (~5/min, no burst). Space
        // the queries out AND cap the batch, so a backlog can never stretch one
        // run past its scheduling slot — the next minute's run picks up the rest.
        $spacing = max(0, (int) config('services.mpesa.query_spacing_seconds', 13));
        $batch   = max(1, (int) config('services.mpesa.reconcile_batch', 4));

        $this->closeUnconfirmedPrompts($settlement);

        $targets = $this->targets()->take($batch);

        $resolved = 0;
        $first = true;

        foreach ($targets as $target) {
            if (! $first && $spacing > 0) {
                sleep($spacing);
            }
            $first = false;

            /** @var CustomOrder $order */
            $order = $target['order'];
            /** @var PaymentAttempt|null $attempt */
            $attempt = $target['attempt'];

            $result = $this->queryRespectingThrottle($mpesa, $target['checkout_id'], $spacing);
            $code = $result['data']['ResultCode'] ?? null;

            if ($code === null) {
                continue; // network/auth/throttle hiccup or still processing — retry next run
            }

            if ((int) $code === 0) {
                // The STK was pushed with a server-authoritative amount, so a
                // confirmed success means the correct amount was paid.
                $attempt?->markPaid();
                // STK Query returns no receipt code; the attempt links the money.
                $booked = $settlement->recordPayment($order, $attempt?->amount, 'mpesa_stk', \App\Support\Actor::system(),
                    null, $attempt, 'confirmed by STK query', $result['data'] ?? []);
                if ($booked['became_paid']) {
                    $resolved++;
                    Log::info("Reconciliation: Order #{$order->id} confirmed paid via STK Query.");
                } elseif ($booked['booked'] && (int) $order->fresh()?->balance_due < 0) {
                    Log::warning("Reconciliation: a second payment was confirmed on Order #{$order->id}; it is in the Refunds queue.");
                }
            } elseif (in_array((int) $code, self::FAILURE_CODES, true)) {
                // Cancelled / timed out / failed — release the hold, unless some
                // other push for this order could still pay it.
                $attempt?->markFailed((string) $code, $result['data']['ResultDesc'] ?? null);
                // Bespoke orders keep their production stage.
                if ($order->isCommerce() && ! $order->hasLivePaymentAttempts()) {
                    $settlement->markUnpaid($order, OrderStatus::PAYMENT_FAILED);
                }
                Log::info("Reconciliation: Order #{$order->id} attempt marked failed (code {$code}).");
            }
            // Any other code: leave as pending and retry next run.
        }

        $this->info("Reconciliation complete. Resolved {$resolved} paid order(s) of {$targets->count()} checked.");

        return self::SUCCESS;
    }

    /**
     * Everything worth asking Daraja about, newest first (customers most
     * recently left waiting are resolved earliest). Each entry:
     * ['order' => CustomOrder, 'checkout_id' => string, 'attempt' => ?PaymentAttempt]
     */
    private function targets(): \Illuminate\Support\Collection
    {
        // Orders a query result may still settle. EXPIRED is included on
        // purpose — see the class docblock.
        $reconcilable = [OrderStatus::PENDING_PAYMENT, OrderStatus::EXPIRED];

        // Give the callback a grace window first; ignore ancient abandoned pushes.
        // Any order qualifies, whatever its status: an unresolved push is what
        // matters, and a status-based filter is how a paid retry on a "Payment
        // Failed" order once went unsettled (B-01). A paid order's push is asked
        // about too: money on it is a second payment, owed back.
        $attempts = PaymentAttempt::with('order')
            ->pending()
            // A placeholder has no id Safaricom knows; its callback claims it (closeUnconfirmedPrompts).
            ->where('checkout_request_id', 'not like', PaymentAttempt::UNCONFIRMED_PREFIX . '%')
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at')
            ->get()
            // Paid orders too: a second prompt the customer also paid, whose callback was
            // lost, is booked like a duplicate callback (refund owed), never left unseen.
            ->filter(fn ($attempt) => $attempt->order !== null)
            ->map(fn ($attempt) => [
                'order'       => $attempt->order,
                'checkout_id' => $attempt->checkout_request_id,
                'attempt'     => $attempt,
            ]);

        // Orders from before per-attempt tracking: only a latest checkout id.
        $legacy = CustomOrder::withTrashed()->withLegacyStatus($reconcilable)
            ->paidUp(false)
            ->whereNotNull('mpesa_checkout_id')
            ->whereDoesntHave('paymentAttempts')
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($order) => [
                'order'       => $order,
                'checkout_id' => $order->mpesa_checkout_id,
                'attempt'     => null,
            ]);

        return $attempts->concat($legacy)->values();
    }

    /**
     * A prompt whose request timed out and whose callback never claimed it in
     * UNCONFIRMED_MINUTES was, as far as anyone can tell, never paid: close it,
     * so the order can be paid again or expire. A prompt expires on the phone
     * in about a minute, so a payment this late would be extraordinary; the
     * Studio still lists any M-Pesa payment that matches no order.
     */
    private function closeUnconfirmedPrompts(OrderSettlementService $settlement): void
    {
        PaymentAttempt::with('order')->pending()->where('checkout_request_id', 'like', PaymentAttempt::UNCONFIRMED_PREFIX . '%')
            ->where('created_at', '<', now()->subMinutes(PaymentAttempt::UNCONFIRMED_MINUTES))->get()
            ->each(function (PaymentAttempt $attempt) use ($settlement) {
                $attempt->markFailed(null, 'Safaricom never confirmed this prompt was sent, and no payment for it arrived.');
                $order = $attempt->order;
                if ($order && $order->isCommerce() && ! $order->hasLivePaymentAttempts()) {
                    $settlement->markUnpaid($order, OrderStatus::PAYMENT_FAILED);
                }
            });
    }

    /**
     * Query Daraja, retrying through a spike-arrest rejection so a genuinely paid
     * order isn't skipped just because we briefly outran Safaricom's rate limit.
     */
    private function queryRespectingThrottle(MpesaService $mpesa, string $checkoutId, int $spacing): array
    {
        $backoff = max($spacing, 13);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $result = $mpesa->queryStkStatus($checkoutId);

            if (! $this->isSpikeArrest($result)) {
                return $result;
            }

            if ($spacing > 0) {
                sleep($backoff);
            }
        }

        return $result;
    }

    private function isSpikeArrest(array $result): bool
    {
        return ($result['data']['fault']['detail']['errorcode'] ?? null)
            === 'policies.ratelimit.SpikeArrestViolation';
    }
}
