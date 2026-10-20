<?php

namespace App\Console\Commands;

use App\Models\CustomOrder;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\Audit;
use App\Support\OrderPolicy;
use Illuminate\Console\Command;

/**
 * Phase C: a late payment whose item sold out must not wait for ever. Once
 * the policy's days pass with no decision in the Studio, the order is
 * cancelled and everything paid is owed back (the Refunds queue). No money
 * moves here: a person still sends and records the refund.
 */
class ResolveOverdueOrders extends Command
{
    protected $signature = 'serana:resolve-overdue';
    protected $description = 'Turn undecided sold-out late payments into refunds owed after the policy days';

    public function handle(OrderSettlementService $settlement): int
    {
        $days = OrderPolicy::resolutionDays();
        $reason = "No decision within {$days} days";
        $count = 0;

        CustomOrder::withTrashed()->where('resolution', 'needs_resolution')->where('resolution_code', 'stock_gone')
            ->where('resolution_since', '<=', now()->subDays($days))->orderBy('id')
            ->each(function (CustomOrder $order) use ($settlement, $reason, &$count) {
                $settlement->cancelForRefund($order, Actor::system(), $reason, 'Sold out after a late payment');
                Audit::record('order.resolved', $order, ['resolution' => 'needs_resolution'], ['action' => 'refund', 'balance_due' => $order->fresh()->balance_due], $reason, Actor::system());
                $count++;
            });

        $this->info("{$count} undecided order(s) moved to refunds owed.");

        return self::SUCCESS;
    }
}
