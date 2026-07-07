<?php

namespace App\Console\Commands;

use App\Models\CustomOrder;
use App\Models\PaymentAttempt;
use App\Models\StockReservation;
use App\Support\OrderStatus;
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
        // Spare any order with a recent unresolved STK push: the customer may be
        // typing their PIN right now, and expiring mid-payment would strand the
        // money if the callback is then lost.
        $expiredOrders = CustomOrder::where('status', OrderStatus::PENDING_PAYMENT)
            ->where('is_paid', false)
            ->whereDoesntHave('stockReservations', fn ($q) => $q->where('status', 'held'))
            ->whereDoesntHave('paymentAttempts', fn ($q) => $q
                ->where('status', PaymentAttempt::PENDING)
                ->where('created_at', '>=', now()->subMinutes($window)))
            ->where('created_at', '<=', now()->subMinutes($window))
            ->update(['status' => OrderStatus::EXPIRED]);

        $this->info("Released {$released} reservation(s); expired {$expiredOrders} order(s).");

        return self::SUCCESS;
    }
}
