<?php

namespace App\Services;

use App\Events\MpesaWebhookHandled;
use App\Models\CustomOrder;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Single source of truth for moving an order between payment states. Shared by
 * the live M-Pesa callback and the reconciliation job so both behave identically.
 */
class OrderSettlementService
{
    /**
     * Confirm payment: mark paid, commit held stock to sold, fire downstream event.
     *
     * Concurrency-safe: the paid flip is an atomic conditional UPDATE, so when
     * the callback and the reconciler race, exactly one caller wins the claim —
     * the other returns false and fires nothing (no duplicate receipt emails).
     * The flip and the stock commit share one transaction, so an order can
     * never end up paid with its holds left dangling.
     */
    public function markPaid(CustomOrder $order, ?string $receiptNumber = null, array $rawPayload = []): bool
    {
        $settled = DB::transaction(function () use ($order, $receiptNumber) {
            // withTrashed: money that arrives for an order Studio archived must
            // still settle it, and the order comes back so it can be fulfilled.
            $claimed = CustomOrder::withTrashed()->whereKey($order->id)
                ->where('is_paid', false)
                ->update([
                    'is_paid'              => true,
                    'paid_at'              => now(),
                    'status'               => OrderStatus::PAID,
                    'mpesa_receipt_number' => $receiptNumber ?? $order->mpesa_receipt_number,
                    'deleted_at'           => null,
                ]);

            if ($claimed === 0) {
                return false;
            }

            $order->refresh();
            $order->commitReservations();

            return true;
        });

        // Dispatched only after the transaction commits: a queued listener must
        // never observe (or serialize) an order the database hasn't settled yet.
        if ($settled) {
            event(new MpesaWebhookHandled($order->refresh(), $rawPayload));
        }

        return $settled;
    }

    /**
     * Mark a non-success outcome and return the held stock to the pool.
     * Never downgrades an already-paid order (atomic conditional UPDATE).
     */
    public function markUnpaid(CustomOrder $order, string $status): void
    {
        // A collected-but-mismatched payment stays flagged until staff review
        // it; a later failed prompt must not quietly downgrade it.
        $updated = CustomOrder::withTrashed()->whereKey($order->id)
            ->where('is_paid', false)
            ->when($status !== OrderStatus::AMOUNT_MISMATCH, fn ($q) => $q->where('status', '!=', OrderStatus::AMOUNT_MISMATCH))
            ->update(['status' => $status]);

        if ($updated > 0) {
            $order->refresh();
            $order->releaseReservations();
        }
    }

    /**
     * A "successful" payment arrived whose amount doesn't match the quote —
     * the customer's money is in the till but the order can't be fulfilled as
     * paid. This needs a human: flag the order and alert the owner, because a
     * log line alone strands the customer's cash.
     */
    public function flagAmountMismatch(CustomOrder $order, int $expected, $paid): void
    {
        $this->markUnpaid($order, OrderStatus::AMOUNT_MISMATCH);

        Log::error("M-Pesa amount mismatch for Order #{$order->id}.", [
            'expected' => $expected,
            'paid'     => $paid,
        ]);

        $ownerEmail = config('services.store.notification_email')
            ?: config('mail.from.address');

        if (! $ownerEmail) {
            return;
        }

        try {
            Mail::raw(
                "Order {$order->cipher} (#{$order->id}) received an M-Pesa payment of KSh {$paid} "
                . "but KSh {$expected} was quoted. The order was NOT marked paid.\n\n"
                . "Customer: {$order->full_name} ({$order->phone})\n"
                . "The money has been collected — this needs a manual refund or follow-up.",
                fn ($message) => $message->to($ownerEmail)
                    ->subject("⚠ Payment amount mismatch — order {$order->cipher}")
            );
        } catch (\Throwable $e) {
            Log::error("Amount-mismatch alert email failed for Order #{$order->id}: " . $e->getMessage());
        }
    }
}
