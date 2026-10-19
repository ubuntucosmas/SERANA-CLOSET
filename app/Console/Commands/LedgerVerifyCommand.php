<?php

namespace App\Console\Commands;

use App\Models\CustomOrder;
use App\Models\OrderPayment;
use App\Services\OrderLedger;
use App\Support\LegacyOrderMap;
use Illuminate\Console\Command;

/**
 * Compares every order's legacy columns with the split model and the ledger
 * (design §2.3). Must report 0 differences before reads cut over (A4).
 * Exits non-zero on any difference.
 */
class LedgerVerifyCommand extends Command
{
    protected $signature = 'serana:ledger:verify {--limit=50 : How many differences to print}';

    protected $description = 'Verify the ledger and split status columns against the legacy order columns';

    public function handle(): int
    {
        $differences = 0;
        $printed = 0;
        $limit = (int) $this->option('limit');

        CustomOrder::withTrashed()->orderBy('id')->chunkById(200, function ($orders) use (&$differences, &$printed, $limit) {
            foreach ($orders as $order) {
                $issues = $this->check($order);
                if ($issues === []) {
                    continue;
                }
                $differences++;
                foreach ($issues as $issue) {
                    if ($printed++ < $limit) {
                        $this->line("  order #{$order->id} ({$order->cipher}) {$issue}");
                    }
                }
            }
        });

        $differences === 0
            ? $this->info('Ledger verified: 0 difference(s).')
            : $this->error("Ledger differs from the legacy columns: {$differences} difference(s).");

        $this->reportSharedReferences();

        return $differences === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** Not a difference, but money for the owner to look at: one reference on several orders. */
    private function reportSharedReferences(): void
    {
        $mark = \App\Services\LedgerBackfill::DUPLICATE_MARK;
        $shared = OrderPayment::where('external_reference', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $mark) . '%')
            ->get(['external_reference'])
            ->map(fn ($p) => strstr($p->external_reference, $mark, true))
            ->unique()->values();
        if ($shared->isEmpty()) {
            return;
        }

        $details = $shared->map(fn ($reference) => $reference . ' (orders ' . OrderPayment::where('external_reference', $reference)
            ->orWhere('external_reference', 'like', $reference . '#%')->orderBy('custom_order_id')->pluck('custom_order_id')->implode(', ') . ')');
        $this->warn("Review: {$shared->count()} M-Pesa reference(s) booked on more than one order: " . $details->implode('; '));
    }

    /** @return list<string> */
    private function check(CustomOrder $order): array
    {
        $issues = [];
        $expected = LegacyOrderMap::columns($order->type, $order->status, $order->payment_method);
        foreach ($expected as $column => $value) {
            if ($order->{$column} !== $value) {
                $issues[] = "{$column}: legacy implies " . var_export($value, true) . ', stored ' . var_export($order->{$column}, true);
            }
        }

        $chargeable = (int) $order->lines()->sum('amount');
        $payments = (int) $order->payments()->where('type', OrderPayment::PAYMENT)->sum('amount');
        $refunds = (int) $order->payments()->where('type', OrderPayment::REFUND)->sum('amount');
        try {
            $derived = OrderLedger::derive($chargeable, $payments, $refunds, $order->resolution);
        } catch (\DomainException $e) {
            return [...$issues, $e->getMessage()];
        }

        foreach (['chargeable_total', 'amount_paid', 'balance_due', 'payment_status'] as $column) {
            if ($order->{$column} !== $derived[$column]) {
                $issues[] = "{$column}: ledger gives " . var_export($derived[$column], true) . ', stored ' . var_export($order->{$column}, true);
            }
        }
        if ($derived['is_paid'] !== (bool) $order->is_paid) {
            $issues[] = 'is_paid: ledger gives ' . var_export($derived['is_paid'], true) . ', legacy ' . var_export((bool) $order->is_paid, true);
        }
        if ($chargeable !== (int) round((float) $order->price_quoted)) {
            $issues[] = "lines total {$chargeable}, legacy price_quoted {$order->price_quoted}";
        }

        return $issues;
    }
}
