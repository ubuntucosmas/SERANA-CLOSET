<?php

namespace App\Console\Commands;

use App\Services\LedgerBackfill;
use Illuminate\Console\Command;

/** Builds lines, ledger and split columns for every order from its legacy fields. Safe to re-run. */
class LedgerBackfillCommand extends Command
{
    protected $signature = 'serana:ledger:backfill';

    protected $description = 'Backfill order lines, the payments ledger and the split status columns from legacy fields';

    public function handle(LedgerBackfill $backfill): int
    {
        $totals = $backfill->all();

        $this->info("Backfilled {$totals['orders']} order(s).");
        $this->line("{$totals['adjusted']} order(s) needed a backfill adjustment line (items did not add up to the stored total).");
        $this->line("{$totals['reference_renamed']} payment reference(s) were already booked on another order and were marked for review.");
        $this->line('Run php artisan serana:ledger:verify to compare legacy and new columns.');

        return self::SUCCESS;
    }
}
