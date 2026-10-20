<?php

namespace App\Console\Commands;

use App\Services\StockReconciler;
use Illuminate\Console\Command;

/** Does stock per size agree with the old model and with itself? (design §13.5.3) */
class StockReconcileCommand extends Command
{
    protected $signature = 'serana:stock:reconcile';

    protected $description = 'Compare stock per size with the old batch numbers, and check its integrity';

    public function handle(StockReconciler $reconciler): int
    {
        $report = $reconciler->report();
        $this->line('Stock per size is ' . ($report['v2'] ? '<fg=green>on</>' : 'off (the old model sells)') . '.');
        foreach (['integrity' => 'Integrity', 'differences' => 'Old model vs stock per size', 'uncounted' => 'Not counted yet',
            'to_replay' => 'Old-model sales after a count (replayed at the switch)', 'unreadable_sales' => "Sales after a count whose size can't be read"] as $key => $title) {
            if ($report[$key] !== []) {
                $this->line("<options=bold>{$title}</>");
                foreach ($report[$key] as $line) {
                    $this->line("  {$line}");
                }
            }
        }

        $failures = $reconciler->failures($report);
        if ($failures !== []) {
            $this->error('Stock reconciliation failed: ' . count($failures) . ' problem(s).');

            return self::FAILURE;
        }
        $this->info('Stock reconciliation passed.');

        return self::SUCCESS;
    }
}
