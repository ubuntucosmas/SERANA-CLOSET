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
        $orders = CustomOrder::where('status', OrderStatus::PENDING_PAYMENT)
            ->where('is_paid', false)
            ->whereNotNull('mpesa_checkout_id')
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where('created_at', '>=', now()->subDay())
            ->get();

        $resolved = 0;

        foreach ($orders as $order) {
            $result = $mpesa->queryStkStatus($order->mpesa_checkout_id);
            $code = $result['data']['ResultCode'] ?? null;

            if ($code === null) {
                continue; // network/auth hiccup or still processing — try again next run
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
}
