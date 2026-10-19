<?php

namespace App\Console\Commands;

use App\Models\CustomOrder;
use App\Models\OrderPayment;
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

    public function handle(\App\Services\LedgerVerifier $verifier): int
    {
        $limit = (int) $this->option('limit');
        $differences = $verifier->differences();
        $printed = 0;
        foreach ($differences as $id => $issues) {
            $cipher = CustomOrder::withTrashed()->whereKey($id)->value('cipher');
            foreach ($issues as $issue) {
                if ($printed++ < $limit) {
                    $this->line("  order #{$id} ({$cipher}) {$issue}");
                }
            }
        }
        $count = count($differences);

        $count === 0
            ? $this->info('Ledger verified: 0 difference(s).')
            : $this->error("Ledger differs from the legacy columns: {$count} difference(s).");

        $this->reportSharedReferences();

        return $count === 0 ? self::SUCCESS : self::FAILURE;
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
}
