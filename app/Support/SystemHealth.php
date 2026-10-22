<?php

namespace App\Support;

use App\Console\Commands\ProductionPreflight;
use App\Models\PaymentAttempt;
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

    /** @return list<array{key: string, title: string, detail: string, action: string, orders?: list<array{id: int, reference: string}>}> */
    public static function warnings(): array
    {
        $warnings = [];

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

        // Reconciliation asks Safaricom about a prompt for a day; after that nobody does.
        $stale = PaymentAttempt::pending()->where('created_at', '<', now()->subDay())->where('created_at', '>=', now()->subDays(30))
            ->with('order:id,cipher')->latest('id')->get();
        if ($stale->isNotEmpty()) {
            $orders = $stale->pluck('order')->filter()->unique('id')->values();
            $warnings[] = [
                'key' => 'stale_prompts',
                'title' => $stale->count() . ' M-Pesa ' . ($stale->count() === 1 ? 'prompt was' : 'prompts were') . ' never confirmed or cancelled',
                'detail' => 'Safaricom never told us the result and the automatic check has stopped asking. Orders: ' . $orders->take(5)->pluck('cipher')->implode(', ')
                    . ($orders->count() > 5 ? ' and ' . ($orders->count() - 5) . ' more' : '') . '.',
                'action' => 'Look for each order on the M-Pesa statement. If the money arrived, contact the customer and the owner before taking any other payment.',
                // Opened straight from the warning, so nobody has to search for them.
                'orders' => $orders->take(10)->map(fn ($o) => ['id' => $o->id, 'reference' => $o->cipher])->all(),
            ];
        }

        // Money Safaricom confirmed that matches no order (its prompt was never recorded here).
        $unmatched = \App\Models\AuditLog::where('action', 'payment.unmatched')->where('created_at', '>=', now()->subDays(14))->latest('id')->get();
        if ($unmatched->isNotEmpty()) {
            $warnings[] = [
                'key' => 'unmatched_payments',
                'title' => $unmatched->count() . ' M-Pesa ' . ($unmatched->count() === 1 ? 'payment matches' : 'payments match') . ' no order',
                'detail' => $unmatched->take(5)->map(fn ($log) => 'KSh ' . number_format((int) ($log->after['amount'] ?? 0)) . ' (code ' . ($log->after['receipt'] ?? 'unknown')
                    . ', phone ending ' . ($log->after['phone_ending'] ?? '?') . ') on ' . $log->created_at->timezone(config('app.display_timezone'))->format('d M, H:i'))->implode('; ') . '.',
                'action' => 'Find the customer by the phone and time on the M-Pesa statement, then the owner decides: record it on their order or refund it.',
            ];
        }

        return $warnings;
    }
}
