<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * When each scheduled job last finished, and whether it failed (routes/console.php
 * records it). The proof the scheduler really runs the work, not just that the work
 * is listed: serana:preflight prints it, and the Studio warns about a job whose
 * last run failed.
 */
final class JobRuns
{
    /** name => [what it does, in staff words; minutes between runs]. */
    public const JOBS = [
        'mpesa:reconcile' => ['Checking unconfirmed M-Pesa payments with Safaricom', 1],
        'stock:release-expired' => ['Releasing stock from unpaid orders', 1],
        'scheduler-heartbeat' => ['Scheduler heartbeat', 1],
        'operator-alerts' => ['Emailing the owner about problems', 5],
        'drops:close-expired' => ['Closing expired drops', 5],
        'serana:resolve-overdue' => ['Turning overdue payment decisions into refunds owed', 60],
    ];

    private const KEY = 'serana:job:';

    public static function ok(string $name): void
    {
        Cache::forever(self::KEY . $name, ['ok_at' => now()->timestamp] + self::get($name));
    }

    public static function failed(string $name): void
    {
        Cache::forever(self::KEY . $name, ['failed_at' => now()->timestamp] + self::get($name));
    }

    /** @return array{ok_at?: int, failed_at?: int} */
    public static function get(string $name): array
    {
        return (array) Cache::get(self::KEY . $name, []);
    }

    /** Did the job's most recent run fail? */
    public static function lastRunFailed(string $name): bool
    {
        $run = self::get($name);

        return isset($run['failed_at']) && $run['failed_at'] > ($run['ok_at'] ?? 0);
    }
}
