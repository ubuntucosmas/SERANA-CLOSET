<?php

namespace App\Services;

use App\Events\MpesaWebhookHandled;
use App\Models\CustomOrder;
use App\Models\OrderLine;
use App\Models\OrderPayment;
use App\Models\PaymentAttempt;
use App\Support\Actor;
use App\Support\LegacyOrderMap;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The single writer of an order's money (design §1.1 principle 1, I4): ledger
 * rows, lines that change what is owed, the derived money columns, and the
 * legacy is_paid/status kept in step during the dual-run.
 *
 * Every write runs in a transaction (3 attempts on deadlock, closures are
 * database-only) and locks in the global order (N9): the order's products by
 * id, then its stock holds, then the order row. Events and mail fire after
 * commit (N10).
 */
class OrderSettlementService
{
    /** Methods staff may record by hand. Card waits for the owner (design D3). */
    public const MANUAL_METHODS = ['mpesa_manual', 'cash', 'bank'];

    /**
     * Book money received for an order, exactly once per STK attempt and per
     * (method, reference) (I13, N4). $amount null means "whatever is still due
     * when the lock is taken". Becoming fully paid commits the stock holds and,
     * for shop and drop orders, sends the receipt after commit.
     *
     * @return array{booked: bool, became_paid: bool, payment: ?OrderPayment}
     */
    public function recordPayment(CustomOrder $order, ?int $amount, string $method, Actor $actor, ?string $reference = null,
        ?PaymentAttempt $attempt = null, ?string $reason = null, array $rawPayload = [], bool $limitToBalance = false): array
    {
        $result = DB::transaction(function () use ($order, $amount, $method, $actor, $reference, $attempt, $reason, $limitToBalance) {
            $locked = $this->lockForMoney($order);

            if ($existing = $this->alreadyBooked($method, $reference, $attempt)) {
                return ['booked' => false, 'became_paid' => false, 'payment' => $existing];
            }

            $balance = $this->balance($locked);
            $amount ??= $balance;
            if ($amount <= 0) {
                return ['booked' => false, 'became_paid' => false, 'payment' => null];
            }
            if ($limitToBalance && $amount > $balance) {
                throw new \DomainException('That is more than the balance of KSh ' . number_format(max(0, $balance)) . '.');
            }

            $wasPaid = (bool) $locked->is_paid;
            $payment = $this->book($locked, OrderPayment::PAYMENT, $amount, $method, $actor, $reference, $attempt, $reason);

            // A full, correct payment settles a legacy amount mismatch, as it did before the ledger.
            if ($locked->resolution === 'amount_mismatch' && $this->balance($locked) <= 0) {
                DB::table('custom_orders')->where('id', $locked->id)->update(['resolution' => 'none']);
                $locked->resolution = 'none';
            }

            $derived = $this->recompute($locked);
            $becamePaid = ! $wasPaid && $derived['is_paid'];
            if ($becamePaid) {
                $settled = [
                    'mpesa_receipt_number' => $reference ?? $locked->mpesa_receipt_number,
                    // Money that arrives for an order the Studio archived brings it back.
                    'deleted_at' => null,
                    'payment_failed' => false,
                ];
                // A paid shop or drop order is open again (a late payment); bespoke work keeps its stage.
                if ($locked->kind !== 'bespoke') {
                    $settled['order_state'] = 'open';
                }
                DB::table('custom_orders')->where('id', $locked->id)->update($settled);
                $locked->forceFill($settled);
                $this->recompute($locked); // project the legacy columns from the settled state
                $locked->commitReservations();
            }

            return ['booked' => true, 'became_paid' => $becamePaid, 'payment' => $payment];
        }, 3);

        // After commit: a queued listener must never see an order the database hasn't settled yet.
        if ($result['booked']) {
            // Every payment gets its receipt (design B), whatever the order.
            event(new \App\Events\PaymentRecorded(CustomOrder::withTrashed()->findOrFail($order->id), $result['payment'], $rawPayload));
        }
        if ($result['became_paid'] && $order->kind !== 'bespoke') {
            event(new MpesaWebhookHandled(CustomOrder::withTrashed()->findOrFail($order->id), $rawPayload));
        }

        return $result;
    }

    /**
     * Settle whatever is still due as an M-Pesa payment. Kept for callers that
     * mean exactly that; returns whether the order became paid.
     */
    public function markPaid(CustomOrder $order, ?string $receiptNumber = null, array $rawPayload = [], ?PaymentAttempt $attempt = null): bool
    {
        return $this->recordPayment($order, null, 'mpesa_stk', $receiptNumber ? Actor::safaricom() : Actor::system(),
            $receiptNumber, $attempt, null, $rawPayload)['became_paid'];
    }

    /**
     * Mark a non-success outcome and return the held stock to the pool.
     * Never downgrades an already-paid order, and a later failed prompt never
     * hides a collected-but-mismatched payment.
     */
    public function markUnpaid(CustomOrder $order, string $status): void
    {
        DB::transaction(function () use ($order, $status) {
            $locked = $this->lockForMoney($order);

            $updated = CustomOrder::withTrashed()->whereKey($locked->id)
                ->where('is_paid', false)
                ->when($status !== OrderStatus::AMOUNT_MISMATCH, fn ($q) => $q->where('status', '!=', OrderStatus::AMOUNT_MISMATCH))
                ->update(LegacyOrderMap::forStatus($status));

            if ($updated > 0) {
                $locked->refresh();
                $locked->releaseReservations();
                $this->recompute($locked);
            }
        }, 3);
    }

    /**
     * A "successful" payment whose amount doesn't match: the money is real, so
     * it is booked (when Safaricom reported it), the order is flagged for a
     * human and the owner is alerted after commit. Phase C replaces this with
     * the over/under rules (design §4, C).
     */
    public function flagAmountMismatch(CustomOrder $order, int $expected, $paid, ?string $reference = null, ?PaymentAttempt $attempt = null): void
    {
        DB::transaction(function () use ($order, $expected, $paid, $reference, $attempt) {
            $locked = $this->lockForMoney($order);

            $amount = is_numeric($paid) ? (int) round((float) $paid) : 0;
            if ($amount > 0 && ! $this->alreadyBooked('mpesa_stk', $reference, $attempt)) {
                $this->book($locked, OrderPayment::PAYMENT, $amount, 'mpesa_stk', Actor::safaricom(), $reference, $attempt,
                    "amount mismatch: KSh {$expected} was expected");
            }

            $updated = CustomOrder::withTrashed()->whereKey($locked->id)->where('is_paid', false)
                ->update(LegacyOrderMap::forStatus(OrderStatus::AMOUNT_MISMATCH));
            if ($updated > 0) {
                $locked->refresh();
                $locked->releaseReservations();
            }
            $this->recompute($locked);
        }, 3);

        Log::error("M-Pesa amount mismatch for Order #{$order->id}.", ['expected' => $expected, 'paid' => $paid]);

        $ownerEmail = \App\Support\StoreProfile::notificationEmail();
        if (! $ownerEmail) {
            return;
        }

        // Queued, never sent here: this runs inside Safaricom's callback request.
        try {
            Mail::to($ownerEmail)->queue(new \App\Mail\AmountMismatchAlertMail($order, $expected, $paid));
        } catch (\Throwable $e) {
            Log::error("Amount-mismatch alert email could not be queued for Order #{$order->id}: " . $e->getMessage());
        }
    }

    /**
     * A bespoke M-Pesa payment arrived for a different amount than its prompt.
     * It is booked as received (recordPayment); this tells the owner, after
     * commit, because a customer cannot change an STK amount. The Studio's
     * Attention list shows it too (StudioController::index).
     */
    public function alertUnexpectedAmount(CustomOrder $order, int $prompted, int $paid, ?string $reference): void
    {
        Log::warning("Unexpected M-Pesa amount on bespoke Order #{$order->id}.", ['prompted' => $prompted, 'paid' => $paid, 'reference' => $reference]);

        $ownerEmail = \App\Support\StoreProfile::notificationEmail();
        if (! $ownerEmail) {
            return;
        }
        try {
            Mail::to($ownerEmail)->queue(new \App\Mail\UnexpectedAmountAlertMail($order, $prompted, $paid, $reference));
        } catch (\Throwable $e) {
            Log::error("Unexpected-amount alert could not be queued for Order #{$order->id}: " . $e->getMessage());
        }
    }

    /**
     * Set what a bespoke order costs. Lines are append-only (I3): the first quote
     * is an item line, later changes are adjustment lines for the difference.
     */
    public function setQuote(CustomOrder $order, int $quote, Actor $actor, string $reason): void
    {
        DB::transaction(function () use ($order, $quote, $actor, $reason) {
            $locked = $this->lockForMoney($order);
            $current = (int) $locked->lines()->sum('amount');
            $difference = $quote - $current;

            if ($difference !== 0) {
                $first = ! $locked->lines()->exists();
                OrderLine::create([
                    'custom_order_id' => $locked->id,
                    'line_type' => $first ? OrderLine::ITEM : OrderLine::ADJUSTMENT,
                    'description' => $first ? 'Bespoke quote' : 'Quote change',
                    'quantity' => 1,
                    'unit_amount' => $difference,
                    'amount' => $difference,
                    'actor_type' => $actor->type,
                    'actor_id' => $actor->id,
                    'reason' => $reason,
                ]);
            }

            DB::table('custom_orders')->where('id', $locked->id)->update(['price_quoted' => $quote]);
            $this->recompute($locked);
        }, 3);
    }

    /**
     * Recompute the stored money columns from lines and ledger (N1), and project
     * the legacy columns from them: the legacy is_paid and status follow the
     * ledger (Phase C: refunds, reversals and cancellations can legitimately
     * take an order out of "paid"). paid_at records the first time it was paid.
     * Call with the order locked; the instance is refreshed with what is stored.
     */
    public function recompute(CustomOrder $order): array
    {
        $derived = OrderLedger::derive(
            (int) $order->lines()->sum('amount'),
            (int) $order->payments()->where('type', OrderPayment::PAYMENT)->sum('amount'),
            (int) $order->payments()->where('type', OrderPayment::REFUND)->sum('amount'),
            $order->resolution ?? OrderLedger::RESOLUTION_NONE,
        );
        $money = array_diff_key($derived, ['is_paid' => true]);
        $order->forceFill($money);

        $legacy = ['is_paid' => $derived['is_paid'], 'status' => $order->legacyStatus(true)];
        if ($derived['is_paid'] && $order->paid_at === null) {
            $legacy['paid_at'] = now();
        }

        DB::table('custom_orders')->where('id', $order->id)->update($money + $legacy);
        $order->forceFill($legacy)->syncOriginalAttributes(array_keys($money + $legacy));

        return $derived;
    }

    /** Lock order N9: the order's products by id, then its stock holds, then the order row. */
    public function lockForMoney(CustomOrder $order): CustomOrder
    {
        $order->lockStock();

        return CustomOrder::withTrashed()->whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    /** Is this money already on the books? An STK attempt and a (method, reference) settle once. */
    public function alreadyBooked(string $method, ?string $reference, ?PaymentAttempt $attempt): ?OrderPayment
    {
        if ($attempt && ($payment = OrderPayment::where('payment_attempt_id', $attempt->id)->first())) {
            return $payment;
        }

        return $reference !== null ? OrderPayment::where('method', $method)->where('external_reference', $reference)->first() : null;
    }

    private function balance(CustomOrder $order): int
    {
        $net = (int) $order->payments()->where('type', OrderPayment::PAYMENT)->sum('amount')
            - (int) $order->payments()->where('type', OrderPayment::REFUND)->sum('amount');

        return (int) $order->lines()->sum('amount') - $net;
    }

    private function book(CustomOrder $order, string $type, int $amount, string $method, Actor $actor, ?string $reference, ?PaymentAttempt $attempt, ?string $reason): OrderPayment
    {
        return OrderPayment::create([
            'custom_order_id' => $order->id,
            'type' => $type,
            'amount' => $amount,
            'method' => $method,
            'external_reference' => $reference !== null ? mb_substr($reference, 0, 100) : null,
            'payment_attempt_id' => $attempt?->id,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }
}
