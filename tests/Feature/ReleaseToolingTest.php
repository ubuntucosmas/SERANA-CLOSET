<?php

namespace Tests\Feature;

use App\Console\Commands\ProductionPreflight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReleaseToolingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_password_reset_rejects_weak_or_mismatched_passwords(): void
    {
        $admin = User::factory()->create(['email' => 'owner@serana.test', 'role' => 'owner', 'password' => Hash::make('password')]);

        $this->artisan('serana:admin', ['email' => 'owner@serana.test'])
            ->expectsQuestion('New password (at least 12 characters)', 'short')
            ->expectsQuestion('Repeat the password', 'short')
            ->assertFailed();
        $this->artisan('serana:admin', ['email' => 'owner@serana.test'])
            ->expectsQuestion('New password (at least 12 characters)', 'a-long-enough-one')
            ->expectsQuestion('Repeat the password', 'a-different-one!')
            ->assertFailed();

        $this->assertTrue(Hash::check('password', $admin->fresh()->password), 'nothing changed');
    }

    public function test_admin_password_reset_signs_out_other_sessions(): void
    {
        $admin = User::factory()->create(['email' => 'owner@serana.test', 'role' => 'owner', 'password' => Hash::make('password')]);
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert(['id' => 'old', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => time()]);

        $this->artisan('serana:admin', ['email' => 'owner@serana.test'])
            ->expectsQuestion('New password (at least 12 characters)', 'kitenge-sunrise-42')
            ->expectsQuestion('Repeat the password', 'kitenge-sunrise-42')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('kitenge-sunrise-42', $admin->fresh()->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $admin->id)->count());
    }

    public function test_admin_command_creates_an_admin_and_never_promotes_a_customer(): void
    {
        $this->artisan('serana:admin', ['email' => 'New@Serana.test', '--generate' => true, '--name' => 'Wanjiru'])
            ->expectsOutputToContain('Password (shown once')->assertSuccessful();
        $this->assertSame('owner', User::where('email', 'new@serana.test')->value('role'));

        User::factory()->create(['email' => 'buyer@serana.test', 'role' => 'customer']);
        $this->artisan('serana:admin', ['email' => 'buyer@serana.test', '--generate' => true])->assertFailed();
        $this->assertSame('customer', User::where('email', 'buyer@serana.test')->value('role'));
    }

    public function test_preflight_blocks_a_misconfigured_server(): void
    {
        User::factory()->create(['role' => 'owner', 'password' => Hash::make('password')]);

        $this->artisan('serana:preflight')
            ->expectsOutputToContain('No staff account uses the default password')
            ->expectsOutputToContain('Scheduler is running')
            ->assertFailed();
    }

    public function test_preflight_passes_a_production_ready_server(): void
    {
        config([
            'app.env' => 'production', 'app.debug' => false, 'app.name' => 'Serana Closet', 'app.url' => 'https://seranacloset.com',
            'session.secure' => true, 'logging.channels.daily.level' => 'warning', 'services.drops.enabled' => false,
            'mail.default' => 'smtp', 'mail.from.address' => 'orders@seranacloset.com', 'services.store.notification_email' => 'owner@seranacloset.com',
            'services.whatsapp.number' => '254711222333', 'queue.default' => 'database', 'filesystems.public_disk' => 's3',
            'services.mpesa.key' => '', // WhatsApp-only launch: a warning, not a blocker
        ]);
        $this->app->detectEnvironment(fn () => 'production');
        User::factory()->create(['role' => 'owner', 'password' => Hash::make('kitenge-sunrise-42')]);
        Cache::forever(ProductionPreflight::HEARTBEAT, now()->timestamp);
        // Since 2026-10-22: every scheduled job has really run, and there is a recent off-server backup.
        foreach (array_keys(\App\Support\JobRuns::JOBS) as $job) {
            \App\Support\JobRuns::ok($job);
        }
        // (deploy/backup.sh)
        $status = sys_get_temp_dir() . '/serana-backup-status-preflight-test.json';
        config(['serana.backup_status_file' => $status]);
        file_put_contents($status, json_encode(['ok' => true, 'at' => now()->subHour()->toIso8601String(), 'database' => 'serana-db.sql.gz', 'offsite' => true, 'remote' => 'r2:serana-backups']));

        try {
            $this->artisan('serana:preflight')->expectsOutputToContain('Ready to take orders')->assertSuccessful();
        } finally {
            @unlink($status);
        }
    }
}
