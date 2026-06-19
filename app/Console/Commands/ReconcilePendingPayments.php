<?php

namespace App\Console\Commands;

use App\Models\CustomOrder;
use App\Services\MpesaService;
use App\Services\OrderSettlementService;
use App\Support\OrderStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safaricom STK callbacks fail to arrive far more often than people expect. This
 * job actively asks Daraja for the final state of orders that pushed an STK but
 * never got a callback, so paying customers aren't stranded in "Pending Payment".
 * Scheduled every few minutes.
 */
class ReconcilePendingPayments extends Command
{
    protected $signature = 'mpesa:reconcile';
    protected $description = 'Resolve pending M-Pesa payments via STK Query when the callback never arrived';

    public function handle(MpesaService $mpesa, OrderSettlementService $settlement): int
    {
        // Give the callback a grace window first; ignore ancient abandoned pushes.
        // Newest first so customers most recently left waiting are resolved earliest.
        $orders = CustomOrder::where('status', OrderStatus::PENDING_PAYMENT)
            ->where('is_paid', false)
            ->whereNotNull('mpesa_checkout_id')
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at')
            ->get();

        // Daraja spike-arrests the STK Query endpoint (~5/min, no burst). Without
        // pacing, a loop of pending orders gets its later queries rejected and the
        // paid ones silently skipped — so space the requests out.
        $spacing = max(0, (int) config('services.mpesa.query_spacing_seconds', 13));

        $resolved = 0;
        $first = true;

        foreach ($orders as $order) {
            if (! $first && $spacing > 0) {
                sleep($spacing);
            }
            $first = false;

            $result = $this->queryRespectingThrottle($mpesa, $order->mpesa_checkout_id, $spacing);
            $code = $result['data']['ResultCode'] ?? null;

            if ($code === null) {
                continue; // network/auth/throttle hiccup or still processing — retry next run
            }

            if ((int) $code === 0) {
                // The STK was pushed with a server-authoritative amount, so a
                // confirmed success means the correct amount was paid.
                if ($settlement->markPaid($order, null, $result['data'] ?? [])) {
                    $resolved++;
                    Log::info("Reconciliation: Order #{$order->id} confirmed paid via STK Query.");
                }
            } elseif (in_array((int) $code, [1032, 1037, 1025, 1, 2001], true)) {
                // Cancelled / timed out / failed — release the hold.
                $settlement->markUnpaid($order, OrderStatus::PAYMENT_FAILED);
                Log::info("Reconciliation: Order #{$order->id} marked failed (code {$code}).");
            }
            // Any other code: leave as pending and retry next run.
        }

        $this->info("Reconciliation complete. Resolved {$resolved} paid order(s) of {$orders->count()} checked.");

        return self::SUCCESS;
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
