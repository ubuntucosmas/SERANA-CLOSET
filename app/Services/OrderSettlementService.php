<?php

namespace App\Services;

use App\Events\MpesaWebhookHandled;
use App\Models\CustomOrder;
use App\Support\OrderStatus;

/**
 * Single source of truth for moving an order between payment states. Shared by
 * the live M-Pesa callback and the reconciliation job so both behave identically.
 */
class OrderSettlementService
{
    /**
     * Confirm payment: mark paid, commit held stock to sold, fire downstream event.
     * Idempotent — a second call on an already-paid order is a no-op.
     */
    public function markPaid(CustomOrder $order, ?string $receiptNumber = null, array $rawPayload = []): bool
    {
        if ($order->is_paid || $order->status === OrderStatus::PAID) {
            return false;
        }

        $order->update([
            'is_paid'              => true,
            'status'               => OrderStatus::PAID,
            'mpesa_receipt_number' => $receiptNumber ?? $order->mpesa_receipt_number,
        ]);

        $order->commitReservations();

        event(new MpesaWebhookHandled($order, $rawPayload));

        return true;
    }

    /**
     * Mark a non-success outcome and return the held stock to the pool.
     * Never downgrades an already-paid order.
     */
    public function markUnpaid(CustomOrder $order, string $status): void
    {
        if ($order->is_paid || $order->status === OrderStatus::PAID) {
            return;
        }

        $order->update(['status' => $status]);
        $order->releaseReservations();
    }
}
