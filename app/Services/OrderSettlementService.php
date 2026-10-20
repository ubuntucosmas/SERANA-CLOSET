<?php

namespace App\Services;

use App\Events\MpesaWebhookHandled;
use App\Models\CustomOrder;
use App\Models\OrderLine;
use App\Models\OrderPayment;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Support\Actor;
use App\Support\LegacyOrderMap;
use App\Support\OrderPolicy;
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
     * @return array{booked: bool, became_paid: bool, payment: ?OrderPayment, stock_gone: list<string>}
     */
    public function recordPayment(CustomOrder $order, ?int $amount, string $method, Actor $actor, ?string $reference = null,
        ?PaymentAttempt $attempt = null, ?string $reason = null, array $rawPayload = [], bool $limitToBalance = false, ?int $tillSessionId = null): array
    {
        $result = DB::transaction(function () use ($order, $amount, $method, $actor, $reference, $attempt, $reason, $limitToBalance, $tillSessionId) {
            $locked = $this->lockForMoney($order);

            if ($existing = $this->alreadyBooked($method, $reference, $attempt)) {
                return ['booked' => false, 'became_paid' => false, 'payment' => $existing, 'stock_gone' => []];
            }

            $balance = $this->balance($locked);
            $amount ??= $balance;
            if ($amount <= 0) {
                return ['booked' => false, 'became_paid' => false, 'payment' => null, 'stock_gone' => []];
            }
            if ($limitToBalance && $amount > $balance) {
                throw new \DomainException('That is more than the balance of KSh ' . number_format(max(0, $balance)) . '.');
            }

            $wasPaid = (bool) $locked->is_paid;
            // Why any excess is owed back: money on an order already settled is a second payment.
            $cause = $balance <= 0 ? 'Paid twice' : 'Paid more than the total';
            // A till payment knows its session; so does an M-Pesa prompt sent from the till (Phase E).
            $payment = $this->book($locked, OrderPayment::PAYMENT, $amount, $method, $actor, $reference, $attempt, $reason,
                ['till_session_id' => $tillSessionId ?? $locked->till_session_id]);

            // Booking the very code that was in conflict here settles the conflict.
            if ($reference !== null && $locked->resolution === 'needs_resolution' && str_starts_with((string) $locked->resolution_reason, "M-Pesa code {$reference} ")) {
                $this->clearResolution($locked);
            }

            // A full, correct payment settles a legacy amount mismatch, as it did before the ledger.
            if ($locked->resolution === 'amount_mismatch' && $this->balance($locked) <= 0) {
                $this->clearResolution($locked);
            }

            $derived = $this->recompute($locked, $cause);
            $becamePaid = ! $wasPaid && $derived['is_paid'];
            $stockGone = [];
            if ($becamePaid) {
                // Never sell past a batch limit: money that arrives after a hold lapsed takes the
                // item only if it is still free; otherwise the order waits for the studio (Phase C).
                $stockGone = $locked->commitReservations(now()->addDays(OrderPolicy::resolutionDays()));
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
                // Sold at the counter: the customer walks out with it (Phase E).
                if ($locked->channel === 'counter') {
                    $settled['fulfilment_status'] = 'collected';
                }
                DB::table('custom_orders')->where('id', $locked->id)->update($settled);
                $locked->forceFill($settled);
                if ($stockGone) {
                    $this->openResolution($locked, 'needs_resolution', 'stock_gone',
                        'Paid after the hold lapsed: ' . implode(', ', $stockGone) . ' sold out in the meantime.');
                    $becamePaid = false;
                }
                $this->recompute($locked); // project the legacy columns from the settled state
            }

            return ['booked' => true, 'became_paid' => $becamePaid, 'payment' => $payment, 'stock_gone' => $stockGone];
        }, 3);

        if ($result['stock_gone'] ?? []) {
            $this->tellStockGone(CustomOrder::withTrashed()->findOrFail($order->id), $result['stock_gone']);
        }

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
                // Money already received (a part payment) keeps its order and its held stock.
                ->where('amount_paid', '<=', 0)
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
                ->update(LegacyOrderMap::forStatus(OrderStatus::AMOUNT_MISMATCH) + ['resolution_code' => 'amount_mismatch', 'resolution_since' => now()]);
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
     * An STK callback carried an M-Pesa code already booked on another order.
     * One transaction is never counted twice (owner decision), so nothing is
     * booked: the order is flagged for the Studio with the reason, and the
     * owner is alerted after commit.
     */
    public function flagReferenceConflict(CustomOrder $order, string $reference, CustomOrder $bookedOn): void
    {
        $reason = "M-Pesa code {$reference} is already recorded on order {$bookedOn->cipher}.";
        DB::transaction(function () use ($order, $reason) {
            $locked = $this->lockForMoney($order);
            $this->openResolution($locked, 'needs_resolution', 'reference_conflict', $reason);
            $this->recompute($locked);
        }, 3);

        Log::warning("M-Pesa code conflict on Order #{$order->id}: {$reason}");
        $this->alertOwner($order, "⚠ M-Pesa code already recorded — order {$order->cipher}",
            "Safaricom confirmed a payment for order {$order->cipher} with M-Pesa code {$reference}, but that code is already recorded on order {$bookedOn->cipher}.\n\n"
            . "It was not booked again. Check the M-Pesa statement: if the earlier entry on {$bookedOn->cipher} was typed by mistake, reverse it there and record this payment here.");
    }

    /**
     * Reverse a booking that should not count (owner decision, Phase C): a
     * 'reversal' row pointing at it, with actor and reason. Not money out.
     * Each payment is reversed at most once, and refunds plus reversals never
     * exceed what was paid (N2).
     */
    public function reversePayment(OrderPayment $payment, Actor $actor, string $reason): OrderPayment
    {
        return DB::transaction(function () use ($payment, $actor, $reason) {
            $locked = $this->lockForMoney($payment->order);
            $payment = OrderPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->type !== OrderPayment::PAYMENT) {
                throw new \DomainException('Only a payment booking can be reversed.');
            }
            if ($payment->reversal()->exists()) {
                throw new \DomainException('This booking is already reversed.');
            }
            ['payments' => $payments, 'refunds' => $refunds] = $locked->ledgerSums();
            if ($refunds > $payments - $payment->amount) {
                throw new \DomainException('Refunds already paid on this order exceed what would remain. Reversing it would break the ledger.');
            }

            $reversal = OrderPayment::create([
                'custom_order_id' => $locked->id, 'type' => OrderPayment::REVERSAL, 'amount' => $payment->amount,
                'method' => $payment->method, 'external_reference' => null, 'reverses_payment_id' => $payment->id,
                'actor_type' => $actor->type, 'actor_id' => $actor->id, 'reason' => $reason, 'occurred_at' => now(),
            ]);
            $this->recompute($locked);

            return $reversal;
        }, 3);
    }

    /**
     * Record money the studio sent back to a customer (the Refunds queue).
     * Never automatic: a person records it, with method, code and reason. No
     * more than is owed (so refunds never exceed payments, N2); the code is
     * unique in its namespace like any payment code. A partial refund leaves
     * the order refund_due for the rest. The refund receipt is queued after
     * commit.
     */
    public function recordRefund(CustomOrder $order, int $amount, string $method, Actor $actor, string $reference, string $reason, ?int $tillSessionId = null): OrderPayment
    {
        $refund = DB::transaction(function () use ($order, $amount, $method, $actor, $reference, $reason, $tillSessionId) {
            $locked = $this->lockForMoney($order);
            $owed = -$this->balance($locked);
            if ($owed <= 0) {
                throw new \DomainException('Nothing is owed to the customer on this order.');
            }
            if ($amount < 1 || $amount > $owed) {
                throw new \DomainException('Only KSh ' . number_format($owed) . ' is owed to the customer.');
            }
            if ($holder = $this->referenceHolder($method, $reference)) {
                throw new \DomainException((OrderPayment::scopeFor($method) === 'mpesa' ? 'This M-Pesa code' : 'This reference')
                    . " is already recorded on order {$holder->order->cipher}.");
            }

            $refund = $this->book($locked, OrderPayment::REFUND, $amount, $method, $actor, $reference, null, $reason, ['till_session_id' => $tillSessionId]);
            $this->recompute($locked);

            return $refund;
        }, 3);

        $this->sendRefundReceipt(CustomOrder::withTrashed()->findOrFail($order->id), $refund);

        return $refund;
    }

    /** The ledger row that holds this code in its namespace: a live payment or any refund. */
    public function referenceHolder(string $method, ?string $reference): ?OrderPayment
    {
        if ($reference === null) {
            return null;
        }

        return OrderPayment::where('reference_scope', OrderPayment::scopeFor($method))->where('external_reference', $reference)
            ->where(fn ($q) => $q->where('type', OrderPayment::REFUND)->orWhere(fn ($p) => $p->where('type', OrderPayment::PAYMENT)->notReversed()))
            ->first();
    }

    /** The refund receipt (with PDF) to the customer, owner in Bcc; best-effort, queued. */
    private function sendRefundReceipt(CustomOrder $order, OrderPayment $refund): void
    {
        $ownerEmail = \App\Support\StoreProfile::notificationEmail();
        $to = $order->email ?: $ownerEmail;
        if (! $to) {
            return;
        }
        try {
            $mailer = Mail::to($to);
            if ($order->email && $ownerEmail) {
                $mailer->bcc($ownerEmail);
            }
            $mailer->queue(new \App\Mail\RefundReceiptMail($order, $refund));
        } catch (\Throwable $e) {
            Log::error("Refund receipt could not be queued for Order #{$order->id}: " . $e->getMessage());
        }
    }

    /**
     * A shop order paid short (Phase C): the stock stays held for the
     * policy's balance hold hours (Studio setting, default 24) while the customer pays the rest by pay link. If the
     * stock can no longer be held (it sold out after the hold lapsed), the
     * order waits for the studio like any late payment.
     */
    public function holdForBalance(CustomOrder $order): void
    {
        $outcome = DB::transaction(function () use ($order) {
            $locked = $this->lockForMoney($order);
            if ((int) $locked->balance_due <= 0 || $locked->resolution !== 'none') {
                return null;
            }
            if (! $locked->secureStockForPayment(now()->addHours(OrderPolicy::balanceHoldHours()))) {
                $gone = $locked->stockReservations()->whereIn('status', ['held', 'released'])->with(['product', 'variant'])->get()
                    ->map(fn ($r) => $r->variant ? app(\App\Services\StockService::class)->describe($r->variant) : ($r->product?->name ?? 'An item'))->unique()->values()->all();
                $this->openResolution($locked, 'needs_resolution', 'stock_gone',
                    'Paid short after the hold lapsed: ' . implode(', ', $gone) . ' sold out in the meantime.');
                $this->recompute($locked);

                return ['stock_gone' => $gone];
            }

            return ['held' => true];
        }, 3);

        $fresh = CustomOrder::withTrashed()->findOrFail($order->id);
        if (isset($outcome['stock_gone'])) {
            $this->tellStockGone($fresh, $outcome['stock_gone']);

            return;
        }
        if ($outcome === null) {
            return;
        }

        if ($fresh->email) {
            try {
                Mail::to($fresh->email)->queue(new \App\Mail\BalanceDueMail($fresh));
            } catch (\Throwable $e) {
                Log::error("Balance notice could not be queued for Order #{$fresh->id}: " . $e->getMessage());
            }
        }
        $this->alertOwner($fresh, "Paid short — order {$fresh->cipher}",
            "Order {$fresh->cipher} was paid KSh " . number_format((int) $fresh->amount_paid) . ' of KSh ' . number_format((int) $fresh->chargeable_total)
            . '. The stock is held for ' . OrderPolicy::balanceHoldHours() . " hours and the customer has a pay link for the balance of KSh "
            . number_format((int) $fresh->balance_due) . ".\n\nIf the balance is not paid by then, the order expires and what was received is owed back (Studio → Refunds).");
    }

    /**
     * Cancel an order and owe back everything paid on it (a full refund
     * decision; no money moves here). Held stock goes back on sale, a
     * cancelling line takes the total to zero, and the order joins the
     * Refunds queue with $cause.
     */
    public function cancelForRefund(CustomOrder $order, Actor $actor, string $reason, string $cause, string $state = 'cancelled'): void
    {
        DB::transaction(function () use ($order, $actor, $reason, $cause, $state) {
            $this->closeOrder($this->lockForMoney($order), $actor, $reason, $cause, $state);
        }, 3);
    }

    /**
     * Cancel a bespoke order (rule N8). Before designing, everything paid is
     * owed back. From designing on, the deposit (up to what was paid) is kept
     * as a retained line with the reason, and anything above it is owed back.
     * The customer is told which applied, after commit.
     *
     * @return array{stage: string, retained: int, owed_back: int}
     */
    public function cancelBespoke(CustomOrder $order, Actor $actor, string $reason): array
    {
        $outcome = DB::transaction(function () use ($order, $actor, $reason) {
            $locked = $this->lockForMoney($order);
            if ($locked->order_state === 'cancelled') {
                throw new \DomainException('This order is already cancelled.');
            }
            if ($locked->fulfilment_status === 'shipped') {
                throw new \DomainException('This order has already left the studio.');
            }
            $stage = (string) $locked->fulfilment_status;
            $started = in_array($stage, ['designing', 'sewing', 'completed'], true);
            $retained = $started ? min($locked->depositRequired(), max(0, (int) $locked->amount_paid)) : 0;

            $this->closeOrder($locked, $actor, $reason,
                $started ? 'Cancelled after work started: paid above the deposit' : 'Cancelled before work started', 'cancelled', $retained);

            return ['stage' => $stage, 'retained' => $retained, 'owed_back' => max(0, -(int) $locked->balance_due)];
        }, 3);

        $fresh = CustomOrder::withTrashed()->findOrFail($order->id);
        if ($fresh->email) {
            try {
                Mail::to($fresh->email)->queue(new \App\Mail\BespokeCancelledMail($fresh, $outcome['retained'], $outcome['owed_back']));
            } catch (\Throwable $e) {
                Log::error("Cancellation notice could not be queued for Order #{$fresh->id}: " . $e->getMessage());
            }
        }

        return $outcome;
    }

    /**
     * Close a locked order: held stock back on sale, a cancelling line takes
     * the total to $retained (a kept deposit, as its own line), and anything
     * paid above that is owed back with $cause.
     */
    private function closeOrder(CustomOrder $locked, Actor $actor, string $reason, string $cause, string $state, int $retained = 0): void
    {
        $locked->releaseReservations();
        $chargeable = (int) $locked->lines()->sum('amount');
        if ($chargeable !== 0) {
            $this->line($locked, OrderLine::ADJUSTMENT, 'Order cancelled', 1, -$chargeable, $actor, $reason);
        }
        if ($retained > 0) {
            $this->line($locked, OrderLine::RETAINED_DEPOSIT, 'Deposit retained', 1, $retained, $actor, $reason);
        }
        $this->clearResolution($locked);
        // The stage it reached is kept ("cancelled at: sewing"); the order state takes it out of every queue.
        $closed = ['order_state' => $state, 'price_quoted' => $retained];
        DB::table('custom_orders')->where('id', $locked->id)->update($closed);
        $locked->forceFill($closed);
        $this->recompute($locked, $cause);
    }

    /** The studio will wait for the next batch: the order stays open, its paid money held, with the note. */
    public function waitForRestock(CustomOrder $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $locked = $this->lockForMoney($order);
            $this->assertStockProblem($locked);
            // The items still ours stay held for as long as the wait takes.
            $locked->stockReservations()->where('status', 'held')->update(['expires_at' => null]);
            $this->openResolution($locked, 'waiting_restock', 'stock_gone', $reason);
            $this->recompute($locked);
        }, 3);
    }

    /**
     * Replace a sold-out item with another (or the same, once restocked) that
     * has stock. Lines record the swap; the price difference becomes a
     * balance due or a refund owed. Once nothing is short and the order is
     * paid, its stock is sold.
     */
    public function swapItem(CustomOrder $order, int $fromProductId, int $toProductId, ?string $size, Actor $actor, string $reason, array $options = []): void
    {
        if (\App\Support\StockMode::v2()) {
            $this->swapToSize($order, $fromProductId, $toProductId, $options + ['top_size' => $size], $actor, $reason);

            return;
        }

        DB::transaction(function () use ($order, $fromProductId, $toProductId, $size, $actor, $reason) {
            // Lock order N9 with one more product: every product involved, by id, before holds and the order row.
            $ids = $order->stockReservations()->pluck('product_id')->push($toProductId)->unique()->sort()->values();
            Product::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id']);
            $locked = $this->lockForMoney($order);
            $this->assertStockProblem($locked);

            // All of the sold-out product's released rows (a set's top and bottom), counted once.
            $fromRows = $locked->stockReservations()->where('product_id', $fromProductId)->where('status', 'released')->get();
            $from = $fromRows->first();
            if (! $from) {
                throw new \DomainException('from_product_id|That is not the item that sold out.');
            }
            $to = Product::find($toProductId);
            $quantity = (int) $fromRows->where('second_part', false)->sum('quantity');
            if (! $to || ! $to->in_stock || ($to->batch_limit !== null && $quantity > (int) $to->availableStock())) {
                throw new \DomainException('to_product_id|' . ($to?->name ?? 'That item') . ' has no stock for this order.');
            }

            $oldUnit = (int) ($locked->lines()->where('product_id', $fromProductId)->where('line_type', OrderLine::ITEM)->value('unit_amount')
                ?? round((float) Product::withTrashed()->find($fromProductId)?->price));
            $newUnit = (int) round((float) $to->price);
            $label = $size !== null && $size !== '' ? $size : null;
            $oldName = Product::withTrashed()->find($fromProductId)?->name ?? 'item';
            $this->line($locked, OrderLine::ADJUSTMENT, "Swapped out: {$oldName}", $quantity, -$oldUnit, $actor, $reason, $fromProductId);
            $this->line($locked, OrderLine::ITEM, $to->name . ($label ? " ({$label})" : ''), $quantity, $newUnit, $actor, $reason, $to->id, $label ? ['size' => $label] : null);

            $fromRows->each->update(['status' => 'cancelled']);
            if ($to->batch_limit !== null) {
                $locked->stockReservations()->create(['product_id' => $to->id, 'quantity' => $quantity, 'status' => 'held',
                    'expires_at' => now()->addHours(OrderPolicy::balanceHoldHours())]);
            }

            // The order's item snapshot follows, so receipts and tracking show what is coming.
            $items = collect($locked->items_json ?? [])->map(function ($item, $key) use ($fromProductId, $to, $newUnit, $label) {
                if (! is_array($item) || (int) ($item['id'] ?? 0) !== $fromProductId) {
                    return $item;
                }

                return array_merge($item, ['id' => $to->id, 'name' => $to->name, 'price' => $newUnit, 'size_label' => $label ?? ($item['size_label'] ?? null)]);
            })->all();
            DB::table('custom_orders')->where('id', $locked->id)->update(['items_json' => json_encode($items)]);
            $locked->forceFill(['items_json' => $items]);

            if (! $locked->stockReservations()->where('status', 'released')->exists()) {
                $this->clearResolution($locked);
            }
            $derived = $this->recompute($locked, 'Swapped to a cheaper item');
            if ($derived['is_paid']) {
                $locked->commitReservations();
            } elseif ($derived['balance_due'] > 0) {
                // The difference is paid by pay link; the new item is held meanwhile.
                DB::table('custom_orders')->where('id', $locked->id)->update(['order_state' => 'open']);
            }
        }, 3);
    }

    /**
     * The swap with stock per size (design §13.4): to a size (a set: its top
     * and bottom sizes) that has stock, held at the shop that has it. Lock
     * order: every level involved (the order's holds and the new sizes at
     * every online location), by id, then the holds, then the order row.
     */
    private function swapToSize(CustomOrder $order, int $fromProductId, int $toProductId, array $options, Actor $actor, string $reason): void
    {
        $stock = app(\App\Services\StockService::class);
        DB::transaction(function () use ($order, $fromProductId, $toProductId, $options, $actor, $reason, $stock) {
            $to = Product::find($toProductId);
            $toVariants = $to ? \App\Models\ProductVariant::resolve($to, array_filter($options, fn ($v) => $v !== null && $v !== '')) : null;
            $online = \App\Models\Location::query()->online()->pluck('id');
            $stock->lockForOrder($order, collect($toVariants ?? [])->flatMap(fn ($v) => $online->map(fn ($l) => [$v->id, $l]))->all());
            $locked = $this->lockForMoney($order);
            $this->assertStockProblem($locked);

            $fromRows = $locked->stockReservations()->where('product_id', $fromProductId)->where('status', 'released')->get();
            if ($fromRows->isEmpty()) {
                throw new \DomainException('from_product_id|That is not the item that sold out.');
            }
            if (! $to || ! $to->in_stock) {
                throw new \DomainException('to_product_id|' . ($to?->name ?? 'That item') . ' is not available.');
            }
            if ($toVariants === null) {
                throw new \DomainException('to_product_id|Choose ' . ($to->garment_type === 'set' ? 'both sizes' : 'a size') . " of {$to->name}.");
            }
            $quantity = (int) $fromRows->where('second_part', false)->sum('quantity');
            $firstPart = \App\Models\ProductVariant::partsFor($to->garment_type)[0];
            $demands = $to->track_stock ? collect($toVariants)->map(fn ($v) => ['variant' => $v->setRelation('product', $to), 'quantity' => $quantity, 'second_part' => $v->part !== $firstPart])->all() : [];
            try {
                $allocations = $stock->allocate($demands);
            } catch (\DomainException $e) {
                throw new \DomainException('to_product_id|' . $e->getMessage());
            }

            $label = collect($toVariants)->map->label()->implode(' · ');
            $oldUnit = (int) ($locked->lines()->where('product_id', $fromProductId)->where('line_type', OrderLine::ITEM)->value('unit_amount')
                ?? round((float) Product::withTrashed()->find($fromProductId)?->price));
            $newUnit = (int) round((float) $to->price);
            $oldName = Product::withTrashed()->find($fromProductId)?->name ?? 'item';
            $this->line($locked, OrderLine::ADJUSTMENT, "Swapped out: {$oldName}", $quantity, -$oldUnit, $actor, $reason, $fromProductId);
            $this->line($locked, OrderLine::ITEM, "{$to->name} ({$label})", $quantity, $newUnit, $actor, $reason, $to->id,
                ['size' => $label, 'variant_ids' => collect($toVariants)->pluck('id')->all()]);

            $fromRows->each->update(['status' => 'cancelled']);
            $stock->hold($locked, $allocations, now()->addHours(OrderPolicy::balanceHoldHours()));

            $sizeOptions = collect($toVariants)->mapWithKeys(fn ($v) => [$v->part === \App\Models\ProductVariant::BOTTOM ? 'bottom_size' : 'top_size' => $v->size])->all();
            $items = collect($locked->items_json ?? [])->map(function ($item) use ($fromProductId, $to, $newUnit, $label, $sizeOptions, $toVariants) {
                if (! is_array($item) || (int) ($item['id'] ?? 0) !== $fromProductId) {
                    return $item;
                }

                return array_merge($item, ['id' => $to->id, 'name' => $to->name, 'slug' => $to->slug, 'price' => $newUnit, 'size_label' => $label,
                    'options' => $sizeOptions, 'variant_ids' => collect($toVariants)->pluck('id')->all()]);
            })->all();
            DB::table('custom_orders')->where('id', $locked->id)->update(['items_json' => json_encode($items)]);
            $locked->forceFill(['items_json' => $items]);

            if (! $locked->stockReservations()->where('status', 'released')->exists()) {
                $this->clearResolution($locked);
            }
            $derived = $this->recompute($locked, 'Swapped to a cheaper item');
            if ($derived['is_paid']) {
                $locked->commitReservations();
            } elseif ($derived['balance_due'] > 0) {
                DB::table('custom_orders')->where('id', $locked->id)->update(['order_state' => 'open']);
            }
        }, 3);
    }

    private function assertStockProblem(CustomOrder $locked): void
    {
        if ($locked->resolution_code !== 'stock_gone' || ! in_array($locked->resolution, ['needs_resolution', 'waiting_restock'], true)) {
            throw new \DomainException('action|This order has no sold-out item waiting for a decision.');
        }
    }

    /** Open a payment problem for the Studio: what kind, why, and since when. */
    private function openResolution(CustomOrder $locked, string $resolution, string $code, string $reason): void
    {
        $values = ['resolution' => $resolution, 'resolution_code' => $code, 'resolution_reason' => mb_substr($reason, 0, 255), 'resolution_since' => now()];
        DB::table('custom_orders')->where('id', $locked->id)->update($values);
        $locked->forceFill($values);
    }

    private function clearResolution(CustomOrder $locked): void
    {
        $values = ['resolution' => 'none', 'resolution_code' => null, 'resolution_reason' => null, 'resolution_since' => null];
        DB::table('custom_orders')->where('id', $locked->id)->update($values);
        $locked->forceFill($values);
    }

    private function line(CustomOrder $order, string $type, string $description, int $quantity, int $unit, Actor $actor, ?string $reason, ?int $productId = null, ?array $meta = null): OrderLine
    {
        return OrderLine::create([
            'custom_order_id' => $order->id, 'line_type' => $type, 'product_id' => $productId,
            'description' => mb_substr($description, 0, 255), 'quantity' => $quantity, 'unit_amount' => $unit, 'amount' => $quantity * $unit,
            'actor_type' => $actor->type, 'actor_id' => $actor->id, 'reason' => $reason, 'meta' => $meta,
        ]);
    }

    /** Tell the customer (and the owner) that a late payment's item sold out; queued after commit. */
    private function tellStockGone(CustomOrder $order, array $items): void
    {
        Log::warning("Late payment on Order #{$order->id}: sold out in the meantime.", ['items' => $items]);
        if ($order->email) {
            try {
                Mail::to($order->email)->queue(new \App\Mail\StockGoneMail($order, $items));
            } catch (\Throwable $e) {
                Log::error("Stock-gone notice could not be queued for Order #{$order->id}: " . $e->getMessage());
            }
        }
        $this->alertOwner($order, "⚠ Paid, but sold out — order {$order->cipher}",
            "Order {$order->cipher} was paid (KSh " . number_format((int) $order->amount_paid) . ') after its hold lapsed, and ' . implode(', ', $items)
            . " sold out in the meantime. Nothing was oversold.\n\nIn the Studio, choose a full refund, a swap, or waiting for the next batch. "
            . 'If nothing is decided within ' . OrderPolicy::resolutionDays() . ' days, it becomes a refund owed.');
    }

    /** Confirm a flagged booking is genuine (two real payments), with a reason. Money is unchanged. */
    public function confirmGenuine(OrderPayment $payment, Actor $actor, string $reason): void
    {
        DB::table('payment_reviews')->insert([
            'order_payment_id' => $payment->id, 'decision' => 'genuine', 'actor_type' => $actor->type,
            'actor_id' => $actor->id, 'reason' => $reason, 'created_at' => now(),
        ]);
    }

    /** Queue a plain-text alert to the owner's inbox, if there is one. */
    private function alertOwner(CustomOrder $order, string $subject, string $body): void
    {
        $ownerEmail = \App\Support\StoreProfile::notificationEmail();
        if (! $ownerEmail) {
            return;
        }
        try {
            Mail::to($ownerEmail)->queue(new \App\Mail\PaymentProblemAlertMail($order, $subject, $body));
        } catch (\Throwable $e) {
            Log::error("Payment problem alert could not be queued for Order #{$order->id}: " . $e->getMessage());
        }
    }

    /**
     * A discount on a sale (Phase E, D4): a negative discount line with who,
     * why and any approver, then the money columns follow.
     */
    public function addDiscount(CustomOrder $order, int $amount, Actor $actor, string $reason, ?int $approvedBy = null): void
    {
        DB::transaction(function () use ($order, $amount, $actor, $reason, $approvedBy) {
            $locked = $this->lockForMoney($order);
            $this->line($locked, OrderLine::DISCOUNT, 'Discount', 1, -$amount, $actor, $reason, null, $approvedBy ? ['approved_by' => $approvedBy] : null);
            $this->recompute($locked);
        }, 3);
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
            $this->recompute($locked, 'Quote lowered below the amount paid');
        }, 3);
    }

    /**
     * Recompute the stored money columns from lines and ledger (N1), and project
     * the legacy columns from them: the legacy is_paid and status follow the
     * ledger (Phase C: refunds, reversals and cancellations can legitimately
     * take an order out of "paid"). paid_at records the first time it was paid.
     * Call with the order locked; the instance is refreshed with what is stored.
     *
     * When the ledger first shows more paid than charged, the order joins the
     * Refunds queue: refund_due_since is stamped and refund_cause records why
     * ($refundCause, from the caller that made it so). Both clear once nothing
     * is owed back.
     */
    public function recompute(CustomOrder $order, ?string $refundCause = null): array
    {
        $derived = OrderLedger::derive(
            (int) $order->lines()->sum('amount'),
            $order->ledgerSums()['payments'],
            $order->ledgerSums()['refunds'],
            $order->resolution ?? OrderLedger::RESOLUTION_NONE,
        );
        $money = array_diff_key($derived, ['is_paid' => true]);
        $order->forceFill($money);

        $legacy = ['is_paid' => $derived['is_paid'], 'status' => $order->legacyStatus(true)];
        if ((int) round((float) $order->price_quoted) !== $derived['chargeable_total']) {
            $legacy['price_quoted'] = $derived['chargeable_total'];
        }
        if ($derived['is_paid'] && $order->paid_at === null) {
            $legacy['paid_at'] = now();
        }
        if ($derived['balance_due'] < 0) {
            if ($order->refund_due_since === null) {
                $legacy['refund_due_since'] = now();
                $legacy['refund_cause'] = $refundCause ?? $order->refund_cause ?? 'Paid more than is owed';
            }
        } elseif ($order->refund_due_since !== null || $order->refund_cause !== null) {
            $legacy['refund_due_since'] = null;
            $legacy['refund_cause'] = null;
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

        // A reversed booking no longer holds its code (it may be booked again, marked).
        return $reference !== null
            ? OrderPayment::where('reference_scope', OrderPayment::scopeFor($method))->where('external_reference', $reference)
                ->where('type', OrderPayment::PAYMENT)->notReversed()->first()
            : null;
    }

    private function balance(CustomOrder $order): int
    {
        ['payments' => $payments, 'refunds' => $refunds] = $order->ledgerSums();
        $net = $payments - $refunds;

        return (int) $order->lines()->sum('amount') - $net;
    }

    private function book(CustomOrder $order, string $type, int $amount, string $method, Actor $actor, ?string $reference, ?PaymentAttempt $attempt, ?string $reason, array $extra = []): OrderPayment
    {
        // The row of a reversed booking keeps its code (append-only); a rebooking is marked and traceable.
        if ($reference !== null && ($reversed = OrderPayment::where('reference_scope', OrderPayment::scopeFor($method))
            ->where('external_reference', $reference)->first())) {
            $reference = "{$reference}#after-reversal-{$reversed->id}";
        }

        return OrderPayment::create(array_merge([
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
        ], $extra));
    }
}
