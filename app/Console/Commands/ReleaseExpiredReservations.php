<?php

namespace App\Console\Commands;

use App\Models\CustomOrder;
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
        $released = StockReservation::where('status', 'held')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'released']);

        // Mark the now-stale orders so the studio dashboard reflects reality.
        $expiredOrders = CustomOrder::where('status', OrderStatus::PENDING_PAYMENT)
            ->where('is_paid', false)
            ->whereDoesntHave('stockReservations', fn ($q) => $q->where('status', 'held'))
            ->where('created_at', '<=', now()->subMinutes(config('services.mpesa.reservation_minutes', 15)))
            ->update(['status' => OrderStatus::EXPIRED]);

        $this->info("Released {$released} reservation(s); expired {$expiredOrders} order(s).");

        return self::SUCCESS;
    }
}
