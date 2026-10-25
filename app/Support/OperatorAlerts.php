<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The V1 alerting (no monitoring platform): the owner's inbox hears about
 * server errors and background problems, and an external uptime check on
 * /health catches a dead server or scheduler (the one thing the app can't
 * report about itself). Every send is throttled and never throws.
 */
final class OperatorAlerts
{
    /** One error email per this many minutes, however many errors happen. */
    public const ERROR_MAIL_MINUTES = 30;

    private const ERRORS_PREFIX = 'serana:errors:';
    private const ERROR_MAILED = 'serana:alerts:error_mailed';
    private const HEALTH_MAILED = 'serana:alerts:health_mailed';

    /** Called for every reported exception (bootstrap/app.php): count it, and tell the owner (throttled). */
    public static function error(\Throwable $e): void
    {
        try {
            $key = self::ERRORS_PREFIX . now()->format('YmdH');
            Cache::add($key, 0, now()->addHours(3));
            Cache::increment($key);
            if (! app()->environment('production') || ! Cache::add(self::ERROR_MAILED, true, now()->addMinutes(self::ERROR_MAIL_MINUTES))) {
                return;
            }
            self::send('Server error on ' . StoreProfile::name(), implode("\n", [
                'The shop hit an error a customer or staff member may have seen.',
                '',
                get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 300),
                'At ' . now()->timezone(config('app.display_timezone'))->format('d M Y, H:i') . ', ' . (app()->runningInConsole() ? 'in a background job' : request()->method() . ' ' . request()->path()),
                '',
                'Further errors in the next ' . self::ERROR_MAIL_MINUTES . ' minutes are counted, not emailed. Details are in storage/logs.',
                'Payments and stock are recorded in transactions: an error never half-saves them.',
            ]));
        } catch (\Throwable) {
            // Alerting must never cause a second failure.
        }
    }

    /** Server errors in the last two clock hours. */
    public static function recentErrors(): int
    {
        return (int) Cache::get(self::ERRORS_PREFIX . now()->format('YmdH'), 0) + (int) Cache::get(self::ERRORS_PREFIX . now()->subHour()->format('YmdH'), 0);
    }

    /**
     * Email the owner when the Studio's warnings or M-Pesa review list change
     * to something new, and again every 12 hours while problems remain.
     *
     * @return bool whether an email was sent
     */
    public static function health(): bool
    {
        $warnings = SystemHealth::warnings();
        $review = app(\App\Services\PaymentReviewService::class)->forStudio();
        $lines = array_map(fn ($w) => "- {$w['title']}: {$w['detail']} {$w['action']}", $warnings);
        if ($review) {
            $lines[] = '- ' . count($review) . ' M-Pesa ' . (count($review) === 1 ? 'payment needs' : 'payments need') . ' review (Studio home → M-Pesa payments to review).';
        }
        $signature = md5(json_encode([array_column($warnings, 'key'), array_map(fn ($r) => $r['kind'] . $r['id'], $review)]));
        $last = Cache::get(self::HEALTH_MAILED);
        if ($lines === []) {
            Cache::forget(self::HEALTH_MAILED);

            return false;
        }
        if ($last && $last['signature'] === $signature && now()->timestamp - $last['at'] < 12 * 3600) {
            return false;
        }
        if (! self::send(StoreProfile::name() . ' needs attention', "The Studio shows:\n\n" . implode("\n", $lines) . "\n\nOpen the Studio home for details.")) {
            return false;
        }
        Cache::forever(self::HEALTH_MAILED, ['signature' => $signature, 'at' => now()->timestamp]);

        return true;
    }

    /** What an uptime monitor checks: the database answers, the scheduler ticks, the email queue moves. */
    public static function probe(): array
    {
        $failing = [];
        try {
            DB::select('SELECT 1');
        } catch (\Throwable) {
            return ['database'];
        }
        $beat = Cache::get(\App\Console\Commands\ProductionPreflight::HEARTBEAT);
        if (! $beat || now()->timestamp - (int) $beat > SystemHealth::SCHEDULER_STALE_MINUTES * 60) {
            $failing[] = 'scheduler';
        }
        if (config('queue.default') === 'database') {
            $oldest = DB::table('jobs')->min('available_at');
            if ($oldest && now()->timestamp - (int) $oldest > SystemHealth::QUEUE_STALE_MINUTES * 60) {
                $failing[] = 'queue';
            }
        }

        return $failing;
    }

    /** Sent now, not queued: the queue may be what is broken. */
    private static function send(string $subject, string $body): bool
    {
        $to = StoreProfile::notificationEmail();
        if (! $to) {
            return false;
        }
        try {
            Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Operator alert email could not be sent.', ['subject' => $subject, 'exception' => get_class($e)]);

            return false;
        }
    }
}
