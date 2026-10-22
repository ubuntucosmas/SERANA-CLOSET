<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PaymentSettings;
use App\Support\StoreProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Is this server fit to take real orders? Run on the server after every
 * deploy (deploy/deploy.sh does). Each line says what is wrong and how to fix
 * it. Exits non-zero while any blocker remains, so a deploy can stop on it.
 */
class ProductionPreflight extends Command
{
    protected $signature = 'serana:preflight';

    protected $description = 'Check production settings, payments, email, background jobs and accounts';

    /** Written every minute by the scheduler (routes/console.php). */
    public const HEARTBEAT = 'serana:scheduler:heartbeat';

    private int $blockers = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->section('Application');
        $this->check(app()->environment('production'), 'APP_ENV is production', 'Set APP_ENV=production.');
        $this->check(! config('app.debug'), 'Debug is off', 'Set APP_DEBUG=false: debug pages expose secrets and customer data.');
        $this->check(config('app.name') === 'Serana Closet', 'App name is "Serana Closet"', 'Set APP_NAME="Serana Closet" (email senders and page titles use it). Now: "' . config('app.name') . '".');
        $this->check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL is HTTPS', 'Set APP_URL=https://<your domain>.');
        $this->check(filled(config('app.key')), 'APP_KEY is set', 'Run php artisan key:generate once, on a new install only.');
        $this->check((bool) config('session.secure'), 'Session cookies are HTTPS-only', 'Set SESSION_SECURE_COOKIE=true.');
        // Every file channel reads LOG_LEVEL; daily is the production one.
        $this->check(config('logging.channels.daily.level', 'debug') !== 'debug', 'Log level is not debug', 'Set LOG_LEVEL=warning and LOG_STACK=daily.', blocker: false);
        $this->check(! config('services.drops.enabled'), 'Paid Drops are off for launch', 'Set DROPS_ENABLED=false until a drop policy is published.', blocker: false);
        // Requests record this (WarnOnUntrustedForwardedHeaders): the CLI can't see the proxy itself.
        $proxy = Cache::get(\App\Http\Middleware\WarnOnUntrustedForwardedHeaders::SEEN_KEY);
        $this->check(! $proxy, 'Requests reach the app through a trusted proxy only',
            'forwarded headers arrived from untrusted ' . ($proxy['remote_addr'] ?? '?') . ' in the last 24 hours. If that is your CDN or load balancer, add its ranges to TRUSTED_PROXIES.', blocker: false);

        $this->section('Database');
        $pending = $this->pendingMigrations();
        $this->check($pending === [], 'All migrations have run', 'Back up, then run php artisan migrate --force. Pending: ' . implode(', ', $pending));
        if (($snapshot = \App\Support\DatabaseSafety::snapshotIsolationOn()) !== null) {
            $this->check($snapshot, 'Database aborts stale locking reads (MariaDB 11.6+, innodb_snapshot_isolation=ON)',
                'Run MariaDB 11.6 or newer with innodb_snapshot_isolation=ON (the default). Without it, two refunds or payments racing can both go through (SC-055).');
        }
        $defaults = User::whereIn('role', \App\Support\Role::STAFF)->get()->filter(fn ($u) => Hash::check('password', $u->password))->pluck('email');
        $this->check(User::where('role', \App\Support\Role::OWNER)->exists(), 'A Studio owner exists', 'Create one: php artisan serana:admin you@yourdomain --name="Your Name"');
        // The money gate for the batch-2 cutover: ledger reads only on a ledger that matches.
        if (config('serana.ledger_reads')) {
            $mismatched = count(app(LedgerCheckCommand::class)->scan()['mismatches']);
            $this->check($mismatched === 0, 'Ledger matches the legacy order fields',
                "LEDGER_READS is on but serana:ledger-check finds {$mismatched} mismatch(es). Set LEDGER_READS=false, run php artisan serana:ledger-check and resolve them.");
        }
        // The stock gate (Phase D1): while stock per size sells, both models must agree.
        if (\App\Support\StockMode::v2()) {
            $reconciler = app(\App\Services\StockReconciler::class);
            $problems = count($reconciler->failures($reconciler->report()));
            $this->check($problems === 0, 'Stock per size matches the old batch numbers',
                "serana:stock:reconcile finds {$problems} problem(s). Run it, fix them (recount if needed), or switch stock per size off: php artisan serana:stock:cutover --rollback.");
        }
        $this->check($defaults->isEmpty(), 'No staff account uses the default password', 'Reset: php artisan serana:admin <email> for ' . $defaults->implode(', '));

        $this->section('Email');
        $from = (string) config('mail.from.address');
        $this->check(! in_array(config('mail.default'), ['log', 'array'], true), 'Email is actually sent', 'Set MAIL_MAILER (e.g. smtp) and the provider credentials.');
        $this->check($from !== '' && ! str_ends_with($from, '@example.com'), 'A sender address is set', 'Set MAIL_FROM_ADDRESS on your brand domain.');
        $this->check(! preg_match('/@(gmail|yahoo|outlook|hotmail)\./i', $from), 'Sender is on the brand domain', 'Personal mailboxes land in spam and cap at ~500 a day: use orders@<your domain> with SPF, DKIM and DMARC.', blocker: false);
        $this->check(filled(StoreProfile::notificationEmail()), 'Owner alerts have an inbox', 'Studio → Settings → Store details: set the order alert email.');

        $this->section('Payments');
        $methods = PaymentSettings::methods();
        $this->line('  Customers can pay by: ' . implode(' and ', array_map(fn ($m) => $m === 'mpesa' ? 'M-Pesa prompt' : 'WhatsApp', $methods)));
        if (PaymentSettings::mpesaConfigured()) {
            $this->check(config('services.mpesa.env') === 'production', 'M-Pesa uses the live API', 'Set MPESA_ENV=production with the live Daraja app keys.');
            $this->check((string) config('services.mpesa.shortcode') !== '174379', 'M-Pesa shortcode is your till/paybill', 'MPESA_SHORTCODE is the sandbox 174379: set your live shortcode and passkey.');
            $callback = (string) config('services.mpesa.callback_url');
            $this->check(str_starts_with($callback, 'https://') && str_ends_with($callback, '/' . config('services.mpesa.callback_token')),
                'M-Pesa callback URL is HTTPS and carries the token', 'Set MPESA_CALLBACK_URL=https://<domain>/payments/mpesa/callback/<MPESA_CALLBACK_TOKEN>.');
            // With every proxy trusted, anyone can forge X-Forwarded-For past the allowlist.
            $proxies = config('trustedproxy.proxies');
            $this->check(! (config('services.mpesa.env') === 'production' && ! empty(config('services.mpesa.allowed_ips')) && in_array($proxies, ['*', '**'], true)),
                'M-Pesa IP allowlist cannot be spoofed', 'TRUSTED_PROXIES is "*", so X-Forwarded-For can be forged past MPESA_ALLOWED_IPS. List your proxy/CDN ranges instead.', blocker: false);
        } else {
            $this->check(false, 'M-Pesa credentials', 'Not configured, so checkout offers WhatsApp only. Add the live Daraja keys when the till is approved.', blocker: false);
        }
        $this->check(filled(StoreProfile::whatsapp()), 'Store WhatsApp number is set', 'Studio → Settings → Store details: WhatsApp number.');
        $this->check(\App\Models\DeliveryOption::available()->get()->contains(fn ($o) => $o->type !== \App\Models\DeliveryOption::QUOTE || PaymentSettings::whatsappAvailable()),
            'Customers can choose delivery or collection', 'Studio → Settings → Delivery and collection: switch on at least one option (a pickup shop needs an address).');
        $this->check(! in_array(PaymentSettings::WHATSAPP, $methods, true) || filled(PaymentSettings::rawInstructions()),
            'WhatsApp customers are told how to pay', 'Studio → Settings → Payments: fill in "How customers pay you".', blocker: false);

        $this->section('Background jobs');
        $beat = Cache::get(self::HEARTBEAT);
        $this->check($beat && now()->timestamp - (int) $beat < 180, 'Scheduler is running',
            'Add the cron line in deploy/crontab: without it checkouts never expire, stock is never released and missed M-Pesa callbacks are never confirmed.'
            . ($beat ? ' Last tick: ' . now()->diffForHumans(now()->setTimestamp((int) $beat), true) . ' ago.' : ' It has never run.'));
        if (config('queue.default') === 'database') {
            $oldest = DB::table('jobs')->min('available_at');
            $this->check(! $oldest || now()->timestamp - (int) $oldest < 300, 'Queue worker is running',
                'Emails are waiting unsent: start the worker in deploy/supervisor-serana-worker.conf.');
        }
        $this->check(config('queue.default') !== 'sync', 'Emails are queued, not sent inside payments', 'Set QUEUE_CONNECTION=database (or redis).');
        $failed = DB::table('failed_jobs')->count();
        $this->check($failed === 0, 'No failed jobs', "{$failed} failed job(s): php artisan queue:failed, fix the cause, then queue:retry all.", blocker: false);

        $this->section('Files');
        if (config('filesystems.public_disk') === 'public') {
            $this->check(is_link(public_path('storage')) || is_dir(public_path('storage')), 'Uploads are reachable', 'Run php artisan storage:link.');
        }

        $this->newLine();
        if ($this->blockers) {
            $this->error("Not ready: {$this->blockers} blocker(s), {$this->warnings} warning(s).");

            return self::FAILURE;
        }
        $this->info("Ready to take orders. {$this->warnings} warning(s) to review.");

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>");
    }

    private function check(bool $ok, string $label, string $fix, bool $blocker = true): void
    {
        if ($ok) {
            $this->line("  <fg=green>✓</> {$label}");

            return;
        }
        $blocker ? $this->blockers++ : $this->warnings++;
        $this->line('  ' . ($blocker ? '<fg=red>✗</>' : '<fg=yellow>!</>') . " {$label}: {$fix}");
    }

    /** @return list<string> */
    private function pendingMigrations(): array
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            return ['(migrations table missing)'];
        }
        $ran = $migrator->getRepository()->getRan();
        $files = array_keys($migrator->getMigrationFiles([database_path('migrations')]));

        return array_values(array_diff($files, $ran));
    }
}
