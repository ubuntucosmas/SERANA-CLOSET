<?php

namespace Tests\Feature;

use App\Console\Commands\ProductionPreflight;
use App\Models\AuditLog;
use App\Models\CustomOrder;
use App\Models\PaymentAttempt;
use App\Models\ThemeSetting;
use App\Models\UnmatchedPayment;
use App\Models\User;
use App\Support\OperatorAlerts;
use App\Support\OrderStatus;
use App\Support\Role;
use App\Support\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * V1 launch closure (2026-10-22): statement-only M-Pesa money (OD-1), owner-only
 * contact settings, the minimal alerting and the backup record.
 */
class LaunchClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mpesa.callback_token' => 'secret']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function order(array $extra = []): CustomOrder
    {
        return CustomOrder::create(array_merge(['full_name' => 'Wanjiru K', 'email' => 'w@example.com', 'phone' => '254712345678', 'whatsapp_number' => '254712345678',
            'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 3000], $extra));
    }

    private function orphanCallback(string $id = 'ws_ORPHAN', int $amount = 3000, string $code = 'RQA1234567', int $phone = 254712345678): void
    {
        $this->postJson('/payments/mpesa/callback/secret', ['Body' => ['stkCallback' => ['ResultCode' => 0, 'CheckoutRequestID' => $id, 'CallbackMetadata' => ['Item' => [
            ['Name' => 'Amount', 'Value' => $amount], ['Name' => 'MpesaReceiptNumber', 'Value' => $code], ['Name' => 'PhoneNumber', 'Value' => $phone],
        ]]]]])->assertOk();
    }

    private function review(User $as): array
    {
        return $this->actingAs($as)->get(route('studio.index'))->viewData('page')['props']['attention']['payment_review'];
    }

    // ---- OD-1: unmatched money waits for the owner; it is never dropped or attached casually ----

    public function test_unmatched_money_is_kept_once_and_suggests_the_likely_order(): void
    {
        $order = $this->order();
        $this->orphanCallback();
        $this->orphanCallback(); // Safaricom retries the same callback

        $this->assertSame(1, UnmatchedPayment::count());
        [$item] = $this->review($this->user(Role::MANAGER)); // managers see it too
        $this->assertSame([3000, 'RQA1234567', '678'], [$item['amount'], $item['receipt'], $item['phone_ending']]);
        $this->assertSame([[$order->cipher, true]], array_map(fn ($o) => [$o['reference'], $o['phone_matches']], $item['likely_orders']));
        $this->assertFalse($order->fresh()->isPaidUp(), 'nothing is attached without the owner');
    }

    public function test_the_owner_matches_it_and_the_order_settles_through_the_ledger(): void
    {
        $owner = $this->user(Role::OWNER);
        $order = $this->order();
        $this->orphanCallback();
        $unmatched = UnmatchedPayment::sole();

        $this->actingAs($owner)->post(route('studio.payment_review.match', $unmatched), ['reference' => $order->cipher, 'reason' => 'Same phone and amount, statement 14:02'])
            ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'The order is paid'));

        $fresh = $order->fresh();
        $this->assertTrue($fresh->isPaidUp());
        $this->assertSame(1, $fresh->payments()->where('external_reference', 'RQA1234567')->count(), 'one booking, under the M-Pesa code');
        $this->assertSame([UnmatchedPayment::MATCHED, $order->id], [$unmatched->fresh()->state, $unmatched->fresh()->custom_order_id]);
        $this->assertTrue(AuditLog::where('action', 'payment.matched')->where('reason', 'Same phone and amount, statement 14:02')->exists());
        $this->assertSame([], $this->review($owner), 'closed');

        // Twice is refused: the money is booked once.
        $this->actingAs($owner)->post(route('studio.payment_review.match', $unmatched), ['reference' => $order->cipher, 'reason' => 'again'])->assertSessionHasErrors('order');
        $this->assertSame(1, $fresh->payments()->count());
    }

    public function test_a_different_phone_needs_the_owner_to_say_they_checked(): void
    {
        $owner = $this->user(Role::OWNER);
        $order = $this->order();
        $this->orphanCallback(phone: 254799000111);
        $unmatched = UnmatchedPayment::sole();

        $this->actingAs($owner)->post(route('studio.payment_review.match', $unmatched), ['reference' => $order->cipher, 'reason' => 'x'])->assertSessionHasErrors('phone_checked');
        $this->assertFalse($order->fresh()->isPaidUp());

        $this->actingAs($owner)->post(route('studio.payment_review.match', $unmatched), ['reference' => $order->cipher, 'reason' => 'Her sister paid; confirmed on call', 'phone_checked' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($order->fresh()->isPaidUp());
    }

    public function test_money_for_no_order_stays_listed_until_the_refund_is_recorded(): void
    {
        $owner = $this->user(Role::OWNER);
        $this->orphanCallback(amount: 1234);
        $unmatched = UnmatchedPayment::sole();

        $this->actingAs($owner)->post(route('studio.payment_review.refunded', $unmatched), ['refund_reference' => 'RFX1', 'reason' => 'x'])->assertSessionHasErrors('reason');
        $this->actingAs($owner)->post(route('studio.payment_review.note', $unmatched), ['reason' => 'Called the number, no answer'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('studio.payment_review.refund_required', $unmatched), ['reason' => 'Nobody ordered this'])->assertSessionHasNoErrors();
        $this->assertSame('refund_required', $this->review($owner)[0]['status'], 'still listed');

        $this->actingAs($owner)->post(route('studio.payment_review.refunded', $unmatched), ['refund_reference' => 'RFX1', 'reason' => 'Sent back'])->assertSessionHasNoErrors();
        $this->assertSame([UnmatchedPayment::REFUNDED, 'RFX1'], [$unmatched->fresh()->state, $unmatched->fresh()->refund_reference]);
        $this->assertSame([], $this->review($owner));
        $this->assertStringContainsString('Called the number, no answer', $unmatched->fresh()->notes);
    }

    public function test_only_the_owner_settles_review_cases(): void
    {
        $this->orphanCallback();
        $unmatched = UnmatchedPayment::sole();
        $order = $this->order();

        $this->actingAs($this->user(Role::MANAGER))->post(route('studio.payment_review.match', $unmatched), ['reference' => $order->cipher, 'reason' => 'x'])->assertForbidden();
        $this->actingAs($this->user(Role::MANAGER))->post(route('studio.payment_review.refund_required', $unmatched), ['reason' => 'x'])->assertForbidden();
        $this->assertSame(UnmatchedPayment::NEEDS_REVIEW, $unmatched->fresh()->state);
    }

    public function test_statement_money_can_be_added_once_per_code(): void
    {
        $owner = $this->user(Role::OWNER);
        $payload = ['amount' => 2500, 'receipt' => 'tk9ab12cde', 'phone' => '0712 345 678', 'paid_at' => now()->subHour()->format('Y-m-d\TH:i'), 'reason' => 'On statement, no order'];

        $this->actingAs($owner)->post(route('studio.payment_review.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('studio.payment_review.store'), $payload)->assertSessionHasErrors('receipt');

        $this->assertSame(['TK9AB12CDE', 'statement', '254712345678'], [UnmatchedPayment::sole()->receipt, UnmatchedPayment::sole()->source, UnmatchedPayment::sole()->phone]);
    }

    public function test_a_prompt_only_the_statement_can_settle_is_confirmed_or_closed_by_the_owner(): void
    {
        $owner = $this->user(Role::OWNER);
        [$paid, $unpaid] = [$this->order(), $this->order(['full_name' => 'Other'])];
        foreach ([[$paid, 'ws_P1'], [$unpaid, 'ws_P2']] as [$o, $id]) {
            PaymentAttempt::forceCreate(['custom_order_id' => $o->id, 'checkout_request_id' => $id, 'phone' => '254712345678', 'amount' => 3000,
                'status' => PaymentAttempt::PENDING, 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
        }
        $this->assertSame(['prompt', 'prompt'], array_column($this->review($owner), 'kind'));

        $p1 = PaymentAttempt::where('checkout_request_id', 'ws_P1')->sole();
        $this->actingAs($owner)->post(route('studio.payment_review.prompt_confirm', $p1), ['receipt' => 'RSTMT12345', 'reason' => 'On statement 21 Oct 10:14'])->assertSessionHasNoErrors();
        $this->assertTrue($paid->fresh()->isPaidUp());
        $this->assertSame(PaymentAttempt::PAID, $p1->fresh()->status);

        $p2 = PaymentAttempt::where('checkout_request_id', 'ws_P2')->sole();
        $this->actingAs($owner)->post(route('studio.payment_review.prompt_close', $p2), ['reason' => 'Not on the statement'])->assertSessionHasNoErrors();
        $this->assertSame([PaymentAttempt::FAILED, OrderStatus::PAYMENT_FAILED], [$p2->fresh()->status, $unpaid->fresh()->legacyStatus()]);
        $this->assertSame([], $this->review($owner));
    }

    public function test_a_prompt_still_being_checked_automatically_is_not_settled_by_hand(): void
    {
        $order = $this->order();
        $attempt = PaymentAttempt::create(['custom_order_id' => $order->id, 'checkout_request_id' => 'ws_NEW', 'phone' => '254712345678', 'amount' => 3000, 'status' => PaymentAttempt::PENDING]);

        $this->actingAs($this->user(Role::OWNER))->post(route('studio.payment_review.prompt_confirm', $attempt), ['receipt' => 'RSTMT12345', 'reason' => 'x'])->assertSessionHasErrors('reason');
        $this->assertFalse($order->fresh()->isPaidUp());
    }

    // ---- Owner-only destinations ----

    public function test_a_manager_cannot_change_where_customers_orders_and_alerts_go(): void
    {
        ThemeSetting::set('whatsapp_number', '254711111111');
        $settings = ['site_name' => 'Serana Closet', 'site_tagline' => 'New tagline', 'whatsapp_number' => '254711111111', 'notification_email' => null];
        $manager = $this->user(Role::MANAGER);

        // The rest of the form still saves for a manager.
        $this->actingAs($manager)->post(route('admin.theme.bulk_update'), ['settings' => $settings])->assertSessionHasNoErrors();
        $this->assertSame('New tagline', ThemeSetting::where('key', 'site_tagline')->value('value'));

        $this->actingAs($manager)->post(route('admin.theme.bulk_update'), ['settings' => ['whatsapp_number' => '0799 999 999'] + $settings])
            ->assertSessionHasErrors(['settings.whatsapp_number' => 'Only the owner can change where customers, orders and alerts are sent.']);
        $this->actingAs($manager)->post(route('admin.theme.update'), ['key' => 'notification_email', 'value' => 'thief@example.com'])->assertSessionHasErrors('notification_email');
        $this->assertSame('254711111111', ThemeSetting::where('key', 'whatsapp_number')->value('value'));
        $this->assertNull(ThemeSetting::where('key', 'notification_email')->value('value'));

        $this->actingAs($this->user(Role::OWNER))->post(route('admin.theme.bulk_update'), ['settings' => ['whatsapp_number' => '0799 999 999'] + $settings])->assertSessionHasNoErrors();
        $this->assertSame('254799999999', ThemeSetting::where('key', 'whatsapp_number')->value('value'));
        $this->assertTrue(AuditLog::where('action', 'settings.store.updated')->exists(), 'still audited');
    }

    // ---- Alerting ----

    public function test_health_reports_the_scheduler_and_queue_for_an_uptime_monitor(): void
    {
        Cache::forget(ProductionPreflight::HEARTBEAT);
        $this->getJson('/health')->assertStatus(503)->assertJson(['status' => 'fail', 'failing' => ['scheduler']]);

        Cache::forever(ProductionPreflight::HEARTBEAT, now()->timestamp);
        $this->getJson('/health')->assertOk()->assertJson(['status' => 'ok', 'failing' => []]);

        config(['queue.default' => 'database']);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subHour()->timestamp, 'created_at' => now()->subHour()->timestamp]);
        $this->getJson('/health')->assertStatus(503)->assertJson(['failing' => ['queue']]);
    }

    public function test_server_errors_are_counted_and_the_owner_is_emailed_at_most_every_half_hour(): void
    {
        $this->app['env'] = 'production';
        ThemeSetting::set('notification_email', 'owner@serana.test');
        $sent = [];
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSent::class, function ($e) use (&$sent) { $sent[] = $e->message; });

        OperatorAlerts::error(new \RuntimeException('boom one'));
        OperatorAlerts::error(new \RuntimeException('boom two'));

        $this->assertSame(2, OperatorAlerts::recentErrors());
        $this->assertCount(1, $sent, 'throttled');
        $this->assertSame('owner@serana.test', $sent[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('boom one', $sent[0]->getTextBody());

        $this->travel(31)->minutes();
        OperatorAlerts::error(new \RuntimeException('boom three'));
        $this->assertCount(2, $sent);
        $this->assertContains('errors', array_column(SystemHealth::warnings(), 'key'));
    }

    public function test_the_owner_is_emailed_when_the_studio_shows_new_problems_and_not_repeatedly(): void
    {
        ThemeSetting::set('notification_email', 'owner@serana.test');
        Cache::forget(ProductionPreflight::HEARTBEAT);
        $sent = [];
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSent::class, function ($e) use (&$sent) { $sent[] = $e->message->getSubject(); });

        $this->assertTrue(OperatorAlerts::health(), 'scheduler stale: emailed');
        $this->assertFalse(OperatorAlerts::health(), 'same problem: not again');
        $this->orphanCallback();
        $this->assertTrue(OperatorAlerts::health(), 'a new money case: emailed');

        $this->assertCount(2, $sent);
        $this->assertStringContainsString('needs attention', $sent[0]);
    }

    public function test_failing_payment_checks_with_safaricom_are_shown(): void
    {
        Cache::forever(ProductionPreflight::HEARTBEAT, now()->timestamp);
        Cache::put(\App\Console\Commands\ReconcilePendingPayments::FAILING_SINCE, now()->subMinutes(20)->timestamp, now()->addDay());

        $this->assertContains('mpesa_checks', array_column(SystemHealth::warnings(), 'key'));
    }

    public function test_each_scheduled_job_records_its_runs_and_a_failed_run_is_shown(): void
    {
        $this->travelTo(now()->startOfHour()); // every job is due on the hour
        config(['services.store.notification_email' => null, 'mail.from.address' => null]); // health() sends nothing and returns false
        Cache::forever(ProductionPreflight::HEARTBEAT, now()->timestamp);
        $this->artisan('schedule:run')->assertSuccessful();
        // In-process jobs record here; command jobs run as their own processes (proved on ddev, see the closure report).
        $this->assertArrayHasKey('ok_at', \App\Support\JobRuns::get('scheduler-heartbeat'), 'it really ran');
        $this->assertNotSame([], \App\Support\JobRuns::get('stock:release-expired'), 'every tracked job records its outcome');

        // The alert task with nothing new to send still counts as a good run (a closure returning false would fail).
        $this->assertArrayHasKey('ok_at', \App\Support\JobRuns::get('operator-alerts'));
        $this->assertFalse(\App\Support\JobRuns::lastRunFailed('operator-alerts'));

        \App\Support\JobRuns::failed('mpesa:reconcile');
        $warning = collect(SystemHealth::warnings())->firstWhere('key', 'job:mpesa:reconcile');
        $this->assertSame('Checking unconfirmed M-Pesa payments with Safaricom: the last run failed', $warning['title']);

        \App\Support\JobRuns::ok('mpesa:reconcile');
        $this->assertNull(collect(SystemHealth::warnings())->firstWhere('key', 'job:mpesa:reconcile'), 'clears on success');
    }

    // ---- Backups ----

    public function test_a_missing_stale_or_on_server_backup_is_warned_about_in_production(): void
    {
        $this->app['env'] = 'production';
        $file = sys_get_temp_dir() . '/serana-backup-status-test.json';
        config(['serana.backup_status_file' => $file]);
        @unlink($file);
        $keys = fn () => array_column(SystemHealth::warnings(), 'title', 'key');

        $this->assertSame('No backup has been recorded', $keys()['backup']);
        file_put_contents($file, json_encode(['ok' => true, 'at' => now()->subHours(30)->toIso8601String(), 'offsite' => true]));
        $this->assertSame('The last backup is 30 hours old', $keys()['backup']);
        file_put_contents($file, json_encode(['ok' => true, 'at' => now()->subHour()->toIso8601String(), 'offsite' => false]));
        $this->assertSame('The last backup is only on this server', $keys()['backup']);
        file_put_contents($file, json_encode(['ok' => true, 'at' => now()->subHour()->toIso8601String(), 'offsite' => true, 'remote' => 'r2:x']));
        $this->assertArrayNotHasKey('backup', $keys());
        @unlink($file);
    }
}
