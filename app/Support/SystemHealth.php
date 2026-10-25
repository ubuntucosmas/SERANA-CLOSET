<?php

namespace App\Support;

use App\Console\Commands\ProductionPreflight;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Problems in the background machinery that change what staff should do,
 * for the Studio home. Only what affects orders or money: the scheduler
 * (payment checks, releasing holds), the email queue, failed jobs and
 * M-Pesa prompts nobody could confirm. Each line says what it means and who
 * acts. serana:preflight checks the same things on the server.
 */
final class SystemHealth
{
    /** Minutes without a scheduler tick before staff are warned (it ticks every minute). */
    public const SCHEDULER_STALE_MINUTES = 5;

    /** Minutes an email may wait in the queue before staff are warned. */
    public const QUEUE_STALE_MINUTES = 15;

    /** Hours since the last good backup before staff are warned (the backup runs nightly). */
    public const BACKUP_STALE_HOURS = 26;

    /** What deploy/backup.sh last recorded, or null if it never ran here. */
    public static function lastBackup(): ?array
    {
        // deploy/backup.sh writes it; tests point this elsewhere so they never touch the real record.
        $file = config('serana.backup_status_file') ?: storage_path('app/backup-status.json');
        $status = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($status) && ($status['ok'] ?? false) ? $status : null;
    }

    /** @return list<array{key: string, title: string, detail: string, action: string}> */
    public static function warnings(): array
    {
        $warnings = [];

        // Backups are a server matter: warned about where they must exist (production).
        if (app()->environment('production')) {
            $backup = self::lastBackup();
            $age = $backup ? now()->diffInHours(\Illuminate\Support\Carbon::parse($backup['at']), true) : null;
            if (! $backup || $age > self::BACKUP_STALE_HOURS || ! ($backup['offsite'] ?? false)) {
                $warnings[] = [
                    'key' => 'backup',
                    'title' => ! $backup ? 'No backup has been recorded' : ($age > self::BACKUP_STALE_HOURS ? 'The last backup is ' . (int) $age . ' hours old' : 'The last backup is only on this server'),
                    'detail' => 'If the server or its disk failed now, orders, payments and stock since the last off-server backup could be lost.',
                    'action' => 'Ask whoever runs the server to check the nightly backup (deploy/backup.sh, storage/logs/backup.log).',
                ];
            }
        }

        $beat = Cache::get(ProductionPreflight::HEARTBEAT);
        if (! $beat || now()->timestamp - (int) $beat > self::SCHEDULER_STALE_MINUTES * 60) {
            $warnings[] = [
                'key' => 'scheduler',
                'title' => 'Background checks have stopped',
                'detail' => ($beat ? 'Last ran ' . now()->setTimestamp((int) $beat)->diffForHumans() . '. ' : 'They have not run on this server. ')
                    . 'M-Pesa payments whose confirmation was missed are not being checked, and unpaid orders are not releasing their stock.',
                'action' => 'Ask whoever runs the server to restart the scheduler (deploy/crontab). Don’t ask customers to pay again meanwhile.',
            ];
        }

        foreach (JobRuns::JOBS as $name => [$label]) {
            if (JobRuns::lastRunFailed($name)) {
                $warnings[] = [
                    'key' => 'job:' . $name,
                    'title' => "{$label}: the last run failed",
                    'detail' => 'At ' . now()->setTimestamp(JobRuns::get($name)['failed_at'])->timezone(config('app.display_timezone'))->format('d M, H:i')
                        . '. It is retried automatically; this clears when a run succeeds.',
                    'action' => str_contains($name, ':')
                        ? "If it stays, ask whoever runs the server to run php artisan {$name} by hand and read the error."
                        : 'If it stays, ask whoever runs the server to read storage/logs/laravel.log for the error.',
                ];
            }
        }

        if (($errors = OperatorAlerts::recentErrors()) > 0) {
            $warnings[] = [
                'key' => 'errors',
                'title' => "{$errors} server " . ($errors === 1 ? 'error' : 'errors') . ' in the last two hours',
                'detail' => 'Someone may have seen an error page. Payments and stock are saved in transactions, so nothing was half-recorded.',
                'action' => 'If it keeps happening, ask whoever runs the server to check storage/logs/laravel.log.',
            ];
        }

        $failingSince = Cache::get(\App\Console\Commands\ReconcilePendingPayments::FAILING_SINCE);
        if ($failingSince && now()->timestamp - (int) $failingSince > 10 * 60) {
            $warnings[] = [
                'key' => 'mpesa_checks',
                'title' => 'Payment checks with Safaricom are failing',
                'detail' => 'Since ' . now()->setTimestamp((int) $failingSince)->diffForHumans() . ', Safaricom has not answered our checks on unconfirmed M-Pesa payments (wrong credentials, or Safaricom unreachable). Payments whose confirmation was missed stay pending.',
                'action' => 'Studio → Settings → Payments: Test connection. If it fails, whoever runs the server checks the M-Pesa keys. Don’t ask customers to pay again meanwhile.',
            ];
        }

        if (config('queue.default') === 'database') {
            $oldest = DB::table('jobs')->min('available_at');
            if ($oldest && now()->timestamp - (int) $oldest > self::QUEUE_STALE_MINUTES * 60) {
                $waiting = DB::table('jobs')->count();
                $warnings[] = [
                    'key' => 'queue',
                    'title' => 'Emails are not being sent',
                    'detail' => "{$waiting} " . ($waiting === 1 ? 'email or task is' : 'emails or tasks are') . ' waiting, the oldest for '
                        . now()->setTimestamp((int) $oldest)->diffForHumans(null, true) . '. Receipts and order alerts are delayed; payments and stock are unaffected.',
                    'action' => 'Ask whoever runs the server to restart the queue worker (deploy/supervisor-serana-worker.conf).',
                ];
            }
        }

        // Checkout needs a way to receive the order; without one nobody can buy online.
        $receivable = \App\Models\DeliveryOption::available()->get()
            ->filter(fn ($o) => $o->type !== \App\Models\DeliveryOption::QUOTE || PaymentSettings::whatsappAvailable());
        if ($receivable->isEmpty()) {
            $warnings[] = [
                'key' => 'no_delivery',
                'title' => 'Customers can’t check out',
                'detail' => 'No delivery or collection option is switched on (or the pickup shop has no address), so checkout has nothing to offer.',
                'action' => 'The owner switches one on in Settings → Delivery and collection.',
            ];
        }

        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        if ($failed > 0) {
            $warnings[] = [
                'key' => 'failed_jobs',
                'title' => "{$failed} background " . ($failed === 1 ? 'task' : 'tasks') . ' failed in the last day',
                'detail' => 'Usually an email that could not be sent (receipt, pickup notice or alert). Payments and stock are recorded separately and are unaffected.',
                'action' => 'Ask whoever runs the server to check php artisan queue:failed and retry once the cause is fixed.',
            ];
        }

        // Money cases (unmatched payments, prompts only the statement can settle) are in the
        // Studio's M-Pesa payment review, not here: one place per problem.

        return $warnings;
    }
}
