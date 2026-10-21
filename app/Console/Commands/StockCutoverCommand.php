<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StockCutover;
use App\Support\Actor;
use Illuminate\Console\Command;

/**
 * The switch to stock per size (design §13.5.4). Without --confirm it shows
 * what would happen and whether it's safe. --confirm and --rollback act as
 * the named owner (actor + reason on every human action).
 */
class StockCutoverCommand extends Command
{
    protected $signature = 'serana:stock:cutover {--confirm : Switch stock per size on} {--rollback : Switch it off again} {--by= : The owner\'s email} {--reason= : Why (rollback)}';

    protected $description = 'Preview, confirm or roll back the switch to stock per size';

    public function handle(StockCutover $cutover): int
    {
        if ($this->option('confirm') || $this->option('rollback')) {
            $owner = User::where('email', (string) $this->option('by'))->where('role', \App\Support\Role::OWNER)->first();
            if (! $owner) {
                $this->error('Name the owner who is doing this: --by=owner@example.com');

                return self::FAILURE;
            }
            try {
                if ($this->option('rollback')) {
                    $cutover->rollback(Actor::user($owner), (string) $this->option('reason'));
                    $this->info('Stock per size is off. The old model sells again.');
                } else {
                    $result = $cutover->confirm(Actor::user($owner));
                    $this->info("Stock per size is on. Replayed {$result['replayed']} sale(s); re-baselined {$result['rebaselined']} product(s).");
                }
            } catch (\DomainException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $preview = $cutover->preview();
        foreach (['uncounted' => 'Count these sizes first', 'unmapped_holds' => "Orders with holds that can't be read as a size",
            'unmapped_released' => 'Old holds with no size (not blocking)', 'to_replay' => 'Sales to replay', 'unreadable_sales' => "Sales after a count whose size can't be read", 'integrity' => 'Integrity', 'differences' => 'Differences the count will correct'] as $key => $title) {
            if ($preview[$key] !== []) {
                $this->line("<options=bold>{$title}</>");
                foreach ($preview[$key] as $line) {
                    $this->line("  {$line}");
                }
            }
        }
        if (! $preview['ready']) {
            $this->error($preview['v2'] ? 'Stock per size is already on.' : 'Not ready to switch.');

            return self::FAILURE;
        }
        $this->info('Ready. Run again with --confirm --by=<owner email> to switch stock per size on.');

        return self::SUCCESS;
    }
}
