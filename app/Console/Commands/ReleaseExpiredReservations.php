<?php

namespace App\Console\Commands;

use App\Models\CustomOrder;
use App\Models\StockReservation;
use App\Support\OrderStatus;
use App\Support\PaymentSettings;
use Illuminate\Console\Command;

/**
 * Returns abandoned-checkout stock to the pool. Without this, a limited batch
 * could be drained to zero by carts that never pay. Scheduled every minute.
 */
class ReleaseExpiredReservations extends Command
{
    protected $signature = 'stock:release-expired';
    protected $description = 'Release stock held by unpaid checkouts whose hold has expired';

    public function handle(): int
    {
        $window = (int) config('services.mpesa.reservation_minutes', 15);

        $released = StockReservation::where('status', 'held')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'released']);

        // Mark the now-stale orders so the studio dashboard reflects reality.
        // A failed prompt collected nothing, so once the window passes it is an
        // abandoned checkout too; left as "Payment Failed" it would sit in the
        // Studio's payment-problems queue for ever. Spare any order with a
        // prompt sent inside the window: the customer may be typing their PIN
        // (or retrying) right now, and expiring mid-payment would strand the
        // money if the callback is then lost.
        $expiredOrders = CustomOrder::withLegacyStatus([OrderStatus::PENDING_PAYMENT, OrderStatus::PAYMENT_FAILED])
            ->paidUp(false)
            // A part-paid order is never silently expired: see below.
            ->where('amount_paid', '<=', 0)
            ->where('payment_method', '!=', PaymentSettings::WHATSAPP)
            ->whereDoesntHave('stockReservations', fn ($q) => $q->where('status', 'held'))
            ->whereDoesntHave('paymentAttempts', fn ($q) => $q
                ->where('created_at', '>=', now()->subMinutes($window)))
            ->where('created_at', '<=', now()->subMinutes($window))
            ->update(\App\Support\LegacyOrderMap::forStatus(OrderStatus::EXPIRED));

        // WhatsApp orders wait for the studio to confirm payment: they expire only
        // after their own (longer) window, whether or not they held any stock.
        $staleWhatsApp = CustomOrder::where('payment_method', PaymentSettings::WHATSAPP)
            ->withLegacyStatus([OrderStatus::PENDING_PAYMENT])
            ->paidUp(false)
            ->where('created_at', '<=', now()->subHours(PaymentSettings::holdHours()))
            ->get();
        foreach ($staleWhatsApp as $order) {
            $order->releaseReservations();
            $expiredOrders += CustomOrder::whereKey($order->id)->paidUp(false)->update(\App\Support\LegacyOrderMap::forStatus(OrderStatus::EXPIRED));
        }

        // Phase C: a shop order paid short whose balance did not arrive within the
        // day it was held: it expires, its stock is back on sale, and what was
        // received is owed back (Studio → Refunds). No money moves here.
        $settlement = app(\App\Services\OrderSettlementService::class);
        $hours = \App\Support\OrderPolicy::balanceHoldHours();
        CustomOrder::ofKind('shop')->where('order_state', 'open')->where('resolution', 'none')
            ->where('amount_paid', '>', 0)->where('balance_due', '>', 0)
            ->whereDoesntHave('stockReservations', fn ($q) => $q->active())
            ->whereDoesntHave('payments', fn ($q) => $q->where('occurred_at', '>', now()->subHours($hours)))
            ->whereDoesntHave('paymentAttempts', fn ($q) => $q->where('status', \App\Models\PaymentAttempt::PENDING)->where('created_at', '>=', now()->subMinutes($window)))
            ->orderBy('id')
            ->each(function (CustomOrder $order) use ($settlement, $hours, &$expiredOrders) {
                $settlement->cancelForRefund($order, \App\Support\Actor::system(), "Balance not paid within {$hours} hours", 'Balance not paid in time', 'expired');
                \App\Support\Audit::record('order.expired_part_paid', $order, [], ['refund_due' => -(int) $order->fresh()->balance_due], "Balance not paid within {$hours} hours", \App\Support\Actor::system());
                $expiredOrders++;
            });

        $this->info("Released {$released} reservation(s); expired {$expiredOrders} order(s).");

        return self::SUCCESS;
    }
}
