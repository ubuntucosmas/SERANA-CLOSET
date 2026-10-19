<?php

namespace App\Console\Commands;

use App\Models\Drop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Resolves drops whose deadline has passed so the lifecycle runs itself:
 *   funded (reservations >= target) → greenlit (we make it)
 *   not funded                      → closed   (didn't reach the goal)
 * Scheduled, so the studio never has to babysit a campaign.
 */
class CloseExpiredDrops extends Command
{
    protected $signature = 'drops:close-expired';
    protected $description = 'Greenlight or close live drops once their deadline passes';

    public function handle(): int
    {
        $drops = Drop::where('status', 'live')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->withCount('paidReservations')
            ->get();

        $greenlit = 0;
        $closed = 0;

        foreach ($drops as $drop) {
            if ($drop->is_funded) {
                $drop->update(['status' => 'greenlit']);
                $greenlit++;
                Log::info("Drop #{$drop->id} ({$drop->title}) greenlit: {$drop->reserved_count}/{$drop->target_quantity} reserved.");
            } else {
                $drop->update(['status' => 'closed']);
                $closed++;
                Log::info("Drop #{$drop->id} ({$drop->title}) closed: only {$drop->reserved_count}/{$drop->target_quantity} reserved.");
            }
        }

        $this->info("Drops resolved — {$greenlit} greenlit, {$closed} closed (of {$drops->count()} past deadline).");

        return self::SUCCESS;
    }
}
