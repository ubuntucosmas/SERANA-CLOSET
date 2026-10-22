<?php

namespace Tests\Feature;

use App\Console\Commands\ProductionPreflight;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\CustomOrder;
use App\Models\OrderPayment;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TillSession;
use App\Models\User;
use App\Services\MpesaService;
use App\Services\TillService;
use App\Support\Actor;
use App\Support\OrderStatus;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The operational-constraint pass (docs/audit/OPERATIONAL-CONSTRAINTS-2026-10-22.md):
 * each test states the condition before, the action, and the invariant after,
 * not only the HTTP answer.
 */
class OperationalConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\StockFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mpesa.callback_token' => 'secret', 'services.mpesa.query_spacing_seconds' => 0]);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => Role::OWNER]);
    }

    private function shopOrder(array $extra = []): CustomOrder
    {
        return CustomOrder::create(array_merge([
            'full_name' => 'Jane Doe', 'email' => 'jane@example.com', 'whatsapp_number' => '254712345678', 'phone' => '254712345678',
            'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 4500,
        ], $extra));
    }

    private function push(CustomOrder $order)
    {
        return $this->withSession(['serana.order_ids' => [$order->id]])
            ->postJson(route('mpesa.stk'), ['order_id' => $order->id, 'phone' => '0712345678']);
    }

    // ---- M-Pesa: one unanswered prompt at a time (the customer must never be able to pay twice) ----

    public function test_a_second_prompt_is_refused_while_the_first_is_unanswered(): void
    {
        $this->mock(MpesaService::class, fn ($m) => $m->shouldReceive('initiateStkPush')->once()
            ->andReturn(['success' => true, 'data' => ['CheckoutRequestID' => 'ws_FIRST']]));
        $order = $this->shopOrder();

        $this->push($order)->assertOk();
        $this->push($order)->assertStatus(409)
            ->assertJsonPath('code', 'prompt_pending')
            ->assertJsonPath('CheckoutRequestID', 'ws_FIRST', 'the page waits on the prompt already on the phone')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'ending 678') && str_contains($m, 'before asking for another'));

        $this->assertSame(1, $order->paymentAttempts()->count(), 'Safaricom was asked once');
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->fresh()->legacyStatus());
    }

    public function test_a_new_prompt_is_allowed_once_the_first_is_answered_or_its_window_has_passed(): void
    {
        $ids = ['ws_A', 'ws_B', 'ws_C'];
        $this->mock(MpesaService::class, fn ($m) => $m->shouldReceive('initiateStkPush')->times(3)
            ->andReturnUsing(function () use (&$ids) { return ['success' => true, 'data' => ['CheckoutRequestID' => array_shift($ids)]]; }));
        $order = $this->shopOrder();

        $this->push($order)->assertOk();
        // The customer cancels on the phone: the prompt is answered, so a retry is safe.
        $this->postJson('/payments/mpesa/callback/secret', ['Body' => ['stkCallback' => ['ResultCode' => 1032, 'CheckoutRequestID' => 'ws_A']]])->assertOk();
        $this->push($order)->assertOk();

        // No answer at all (a lost callback): after the window the customer may try again.
        $this->travel((int) config('services.mpesa.prompt_window_seconds') + 1)->seconds();
        $this->push($order)->assertOk();

        $this->assertSame(['failed', 'pending', 'pending'], $order->paymentAttempts()->orderBy('id')->pluck('status')->all());
    }

    private function stkTimesOut(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*/oauth/*' => \Illuminate\Support\Facades\Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/stkpush/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 20001 milliseconds with 0 bytes received'),
        ]);
    }

    public function test_a_timed_out_prompt_is_never_called_not_charged_and_its_late_confirmation_settles_the_order(): void
    {
        $this->stkTimesOut();
        $order = $this->shopOrder();

        $this->push($order)->assertStatus(503)->assertJsonPath('code', 'prompt_uncertain')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'don’t pay again') && ! str_contains($m, 'not been charged'));
        $placeholder = $order->paymentAttempts()->sole();
        $this->assertTrue($placeholder->isUnconfirmed());
        $this->push($order)->assertStatus(409)->assertJsonPath('code', 'prompt_pending');

        // Safaricom did send it, and the customer approved: the callback carries an id we never saw.
        $this->postJson('/payments/mpesa/callback/secret', ['Body' => ['stkCallback' => ['ResultCode' => 0, 'CheckoutRequestID' => 'ws_REAL', 'CallbackMetadata' => ['Item' => [
            ['Name' => 'Amount', 'Value' => 4500], ['Name' => 'MpesaReceiptNumber', 'Value' => 'RTIMEOUT1'], ['Name' => 'PhoneNumber', 'Value' => 254712345678],
        ]]]]])->assertOk();

        $this->assertSame(['ws_REAL', PaymentAttempt::PAID], [$placeholder->fresh()->checkout_request_id, $placeholder->fresh()->status]);
        $this->assertTrue($order->fresh()->isPaidUp(), 'the money settled its order');
        $this->assertFalse(AuditLog::where('action', 'payment.unmatched')->exists());
    }

    public function test_money_that_matches_no_order_is_listed_for_the_owner(): void
    {
        Cache::forever(ProductionPreflight::HEARTBEAT, now()->timestamp);
        $this->postJson('/payments/mpesa/callback/secret', ['Body' => ['stkCallback' => ['ResultCode' => 0, 'CheckoutRequestID' => 'ws_NOBODY', 'CallbackMetadata' => ['Item' => [
            ['Name' => 'Amount', 'Value' => 3000], ['Name' => 'MpesaReceiptNumber', 'Value' => 'RORPHAN1'], ['Name' => 'PhoneNumber', 'Value' => 254700000123],
        ]]]]])->assertOk();

        $warning = $this->systemWarnings()['unmatched_payments'];
        $this->assertStringContainsString('KSh 3,000 (code RORPHAN1, phone ending 123)', $warning['detail']);
    }

    public function test_a_placeholder_nobody_claimed_is_closed_so_the_order_can_be_paid_or_expire(): void
    {
        $this->stkTimesOut();
        $order = $this->shopOrder();
        $this->push($order)->assertStatus(503);

        $this->travel(PaymentAttempt::UNCONFIRMED_MINUTES + 1)->minutes();
        $this->mock(MpesaService::class, fn ($m) => $m->shouldNotReceive('queryStkStatus'));
        $this->artisan('mpesa:reconcile')->assertSuccessful();

        $this->assertSame(PaymentAttempt::FAILED, $order->paymentAttempts()->sole()->status);
        $this->assertSame(OrderStatus::PAYMENT_FAILED, $order->fresh()->legacyStatus());
    }

    public function test_only_a_failure_to_connect_proves_no_prompt_was_sent(): void
    {
        $this->assertFalse(MpesaService::mayHaveBeenSent(new \RuntimeException('cURL error 7: Failed to connect to api.safaricom.co.ke port 443')));
        $this->assertFalse(MpesaService::mayHaveBeenSent(new \RuntimeException('cURL error 28: Connection timed out after 5001 milliseconds')));
        $this->assertTrue(MpesaService::mayHaveBeenSent(new \RuntimeException('cURL error 28: Operation timed out after 20001 milliseconds with 0 bytes received')));
    }

    // ---- The Sales Desk: a tender never books money that wasn't handed over ----

    private function openTill(): array
    {
        $cashier = User::factory()->create(['role' => Role::CASHIER, 'pos_pin' => Hash::make('2468')]);
        $product = $this->stockedProduct(3, ['name' => 'Wool Jacket', 'price' => 5000]);
        $this->actingAs($cashier)->postJson(route('pos.unlock'), ['pin' => '2468'])->assertOk();
        $this->postJson(route('pos.till.open'), ['register' => 'Till 1', 'float' => 2000])->assertOk();
        $sale = $this->postJson(route('pos.sales.store'), ['items' => [['id' => $product->id, 'quantity' => 1, 'options' => ['top_size' => ProductVariant::ONE_SIZE]]]])
            ->assertOk()->json('sale');

        return [$cashier, $sale['id'], TillSession::sole()];
    }

    public function test_a_retried_cash_tender_is_booked_once_and_the_drawer_expects_the_cash_once(): void
    {
        [, $saleId, $till] = $this->openTill();
        $tender = fn () => $this->postJson(route('pos.sales.tender', $saleId), ['method' => 'cash', 'amount' => 5000, 'tendered' => 6000]);

        $tender()->assertOk()->assertJsonPath('change', 1000);
        $tender()->assertStatus(422)->assertJsonValidationErrors('amount'); // the lost-response retry

        $order = CustomOrder::find($saleId);
        $this->assertSame(1, $order->payments()->where('type', OrderPayment::PAYMENT)->count());
        $this->assertSame(0, (int) $order->balance_due, 'paid, and nothing owed back');
        $this->assertSame(5000, (int) CashMovement::where('till_session_id', $till->id)->where('type', CashMovement::SALE)->sum('amount'));
        $this->assertSame(7000, app(TillService::class)->expectedCash($till), 'float + one sale');
    }

    public function test_no_other_tender_is_taken_while_a_till_prompt_waits_on_the_customers_phone(): void
    {
        $this->mock(MpesaService::class, fn ($m) => $m->shouldReceive('initiateStkPush')->once()
            ->andReturn(['success' => true, 'data' => ['CheckoutRequestID' => 'ws_TILL']]));
        [, $saleId] = $this->openTill();

        $this->postJson(route('pos.sales.tender', $saleId), ['method' => 'mpesa_prompt', 'amount' => 5000, 'phone' => '0712345678'])->assertOk();
        $this->postJson(route('pos.sales.tender', $saleId), ['method' => 'cash', 'amount' => 5000, 'tendered' => 5000])
            ->assertStatus(422)->assertJsonValidationErrors(['amount' => 'still waiting']);
        $this->postJson(route('pos.sales.tender', $saleId), ['method' => 'mpesa_prompt', 'amount' => 5000, 'phone' => '0712345678'])
            ->assertStatus(422)->assertJsonValidationErrors(['amount' => 'still waiting']);

        $this->assertSame(0, CustomOrder::find($saleId)->payments()->count());
    }

    public function test_a_register_close_rechecks_the_difference_when_cash_lands_during_the_count(): void
    {
        $cashier = User::factory()->create(['role' => Role::CASHIER]);
        $tills = app(TillService::class);
        $session = $tills->open(\App\Models\Location::sole(), 'Till 1', 2000, $cashier);
        // Counted 2,000 (no difference), then a 1,000 cash sale lands before the close is written.
        $tills->cash($session, CashMovement::SALE, 1000, Actor::user($cashier));

        try {
            $tills->close($session, 2000, $cashier, null, null);
            $this->fail('A 1,000 difference closed without a reason or a manager.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('should now hold KSh 3,000', $e->errors()['counted'][0]);
        }
        $this->assertTrue($session->fresh()->isOpen(), 'still open: nothing half-closed');
    }

    // ---- Products: catalogue cleanup never breaks or destroys history ----

    private function productForm(array $extra = []): array
    {
        $category = Category::firstOrCreate(['name' => 'Sets'], ['slug' => 'sets']);

        return $extra + ['category_id' => $category->id, 'name' => 'Silk Set', 'description' => 'Silk.', 'price' => 6000, 'garment_type' => 'set', 'in_stock' => true];
    }

    public function test_a_never_sold_archived_product_can_be_deleted_for_good(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('admin.catalog.store_product'), $this->productForm())->assertSessionHasNoErrors();
        $product = Product::sole();
        $this->assertGreaterThan(0, $product->variants()->count(), 'every product has its sizes');
        $product->delete();

        $this->actingAs($owner)->delete(route('admin.catalog.force_delete_product', $product))->assertRedirect()->assertSessionHas('success');

        $this->assertNull(Product::withTrashed()->find($product->id));
        $this->assertSame(0, ProductVariant::where('product_id', $product->id)->count());
        $this->assertTrue(AuditLog::where('action', 'product.purged')->exists());
    }

    public function test_a_product_with_sales_history_stays_archived(): void
    {
        $owner = $this->owner();
        $product = $this->stockedProduct(2, ['name' => 'Sold Hoodie', 'price' => 3000]);
        $order = $this->shopOrder(['price_quoted' => 3000, 'items_json' => [['id' => $product->id, 'name' => 'Sold Hoodie', 'price' => 3000, 'quantity' => 1]]]);
        $this->assertTrue(DB::table('order_lines')->where('product_id', $product->id)->exists(), 'the order names the product');
        $product->delete();

        $this->actingAs($owner)->delete(route('admin.catalog.force_delete_product', $product))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'stays in the archive'));

        $this->assertTrue(Product::onlyTrashed()->whereKey($product->id)->exists(), 'the record of the sale is kept');
    }

    public function test_two_names_with_the_same_web_address_are_refused_with_a_reason(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('admin.catalog.store_product'), $this->productForm())->assertSessionHasNoErrors();
        Product::sole()->delete(); // archived still owns /shop/silk-set

        $this->actingAs($owner)->post(route('admin.catalog.store_product'), $this->productForm(['name' => 'Silk-Set']))
            ->assertSessionHasErrors(['name' => '“Silk Set” (archived) already uses this web address (/shop/silk-set). Choose a slightly different name.']);
        $this->actingAs($owner)->post(route('admin.catalog.store_product'), $this->productForm(['name' => '★★★']))
            ->assertSessionHasErrors('name');
        $this->assertSame(1, Product::withTrashed()->count());
    }

    public function test_a_replaced_product_image_is_removed_only_after_the_new_one_is_saved(): void
    {
        Storage::fake(config('filesystems.public_disk'));
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('admin.catalog.store_product'), $this->productForm(['image' => UploadedFile::fake()->image('a.jpg')]))->assertSessionHasNoErrors();
        $product = Product::sole();
        $old = $product->image_path;
        Storage::disk(config('filesystems.public_disk'))->assertExists($old);

        $this->actingAs($owner)->put(route('admin.catalog.update_product', $product), $this->productForm(['image' => UploadedFile::fake()->image('b.jpg')]))->assertSessionHasNoErrors();

        $new = $product->fresh()->image_path;
        $this->assertNotSame($old, $new);
        Storage::disk(config('filesystems.public_disk'))->assertExists($new);
        Storage::disk(config('filesystems.public_disk'))->assertMissing($old);
    }

    // ---- Staff: one PIN is one person ----

    public function test_a_pin_held_by_a_suspended_account_cannot_be_given_to_someone_else(): void
    {
        $owner = $this->owner();
        User::factory()->create(['role' => Role::CASHIER, 'pos_pin' => Hash::make('4821'), 'suspended_at' => now()]);
        $new = User::factory()->create(['role' => Role::CASHIER]);

        $this->actingAs($owner)->put(route('studio.staff.pin', $new), ['pin' => '4821', 'reason' => 'Till access'])->assertSessionHasErrors('pin');
        $this->assertNull($new->fresh()->pos_pin);
    }

    public function test_a_pin_that_opens_two_accounts_names_no_one(): void
    {
        // Two active staff sharing a PIN, as could happen before PINs were unique across suspended staff.
        $tablet = User::factory()->create(['role' => Role::CASHIER]);
        User::factory()->create(['role' => Role::CASHIER, 'pos_pin' => Hash::make('1357')]);
        User::factory()->create(['role' => Role::MANAGER, 'pos_pin' => Hash::make('1357')]);

        $this->actingAs($tablet)->postJson(route('pos.unlock'), ['pin' => '1357'])
            ->assertStatus(422)->assertJsonValidationErrors(['pin' => 'more than one person']);
    }

    // ---- Orders: a handover is final ----

    public function test_a_collected_order_cannot_be_moved_back_and_collected_again(): void
    {
        $owner = $this->owner();
        $order = $this->shopOrder(['fulfilment_method' => 'pickup']);
        app(\App\Services\OrderSettlementService::class)->recordPayment($order, 4500, 'mpesa_stk', Actor::safaricom(), 'RCOLL1');
        CustomOrder::whereKey($order->id)->update(\App\Support\LegacyOrderMap::forStatus('collected'));

        $this->actingAs($owner)->put(route('studio.update', $order), ['status' => 'ready_for_pickup', 'internal_notes' => 'oops'])
            ->assertSessionHasErrors(['status' => 'This order was handed over at the Sales Desk, so its stage stays Collected. Add a note instead; a return goes through the Sales Desk.']);
        $this->assertSame('collected', $order->fresh()->fulfilment_status);

        // Notes can still be written.
        $this->actingAs($owner)->put(route('studio.update', $order), ['status' => 'collected', 'internal_notes' => 'Collected by her sister'])->assertSessionHasNoErrors();
        $this->assertSame('Collected by her sister', $order->fresh()->internal_notes);
    }

    // ---- Studio home: background problems are visible to staff ----

    private function systemWarnings(): array
    {
        return collect($this->actingAs($this->owner())->get(route('studio.index'))->viewData('page')['props']['attention']['system'])->keyBy('key')->all();
    }

    public function test_a_stopped_scheduler_is_shown_on_the_studio_home(): void
    {
        Cache::forget(ProductionPreflight::HEARTBEAT);
        $this->assertArrayHasKey('scheduler', $this->systemWarnings());

        Cache::forever(ProductionPreflight::HEARTBEAT, now()->timestamp);
        $this->assertSame([], $this->systemWarnings(), 'healthy: nothing shown');

        $this->travel(10)->minutes();
        $this->assertStringContainsString('M-Pesa payments whose confirmation was missed are not being checked', $this->systemWarnings()['scheduler']['detail']);
    }

    public function test_failed_jobs_stuck_emails_and_prompts_nobody_resolved_are_shown(): void
    {
        Cache::forever(ProductionPreflight::HEARTBEAT, now()->timestamp);
        config(['queue.default' => 'database']);
        DB::table('failed_jobs')->insert(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'Swift transport', 'failed_at' => now()]);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subHour()->timestamp, 'created_at' => now()->subHour()->timestamp]);
        $order = $this->shopOrder();
        PaymentAttempt::forceCreate(['custom_order_id' => $order->id, 'checkout_request_id' => 'ws_OLD', 'phone' => '254712345678', 'amount' => 4500,
            'status' => PaymentAttempt::PENDING, 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
        Cache::forever(ProductionPreflight::HEARTBEAT, now()->timestamp);

        $warnings = $this->systemWarnings();

        $this->assertSame(['failed_jobs', 'queue', 'stale_prompts'], collect(array_keys($warnings))->sort()->values()->all());
        $this->assertStringContainsString($order->cipher, $warnings['stale_prompts']['detail']);
        $this->assertStringContainsString('before taking any other payment', $warnings['stale_prompts']['action']);
    }

    public function test_a_checkout_with_no_way_to_receive_the_order_is_flagged_to_staff(): void
    {
        Cache::forever(ProductionPreflight::HEARTBEAT, now()->timestamp);
        $this->assertArrayNotHasKey('no_delivery', $this->systemWarnings());

        \App\Models\DeliveryOption::query()->update(['active' => false]);

        $this->assertStringContainsString('Settings → Delivery and collection', $this->systemWarnings()['no_delivery']['action']);
        $this->get(route('checkout'))->assertInertia(fn ($page) => $page->where('delivery_options', []));
    }

    // ---- Settings: who changed where customers send orders and money ----

    public function test_store_contact_changes_are_in_the_activity_history(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('admin.theme.bulk_update'), ['settings' => ['whatsapp_number' => '0711 222 333', 'site_name' => 'Serana Closet']])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('admin.theme.bulk_update'), ['settings' => ['whatsapp_number' => '0711222333', 'site_name' => 'Serana Closet']])->assertSessionHasNoErrors();

        $logs = AuditLog::where('action', 'settings.store.updated')->get();
        $this->assertCount(1, $logs, 'the same values again record nothing');
        $this->assertSame('254711222333', $logs[0]->after['whatsapp_number']);
        $this->assertSame($owner->id, $logs[0]->actor_id);
        $this->assertSame('Changed store details', \App\Support\ActivityLabels::action('settings.store.updated'));
    }

    // ---- Bespoke: the studio can always reach the customer ----

    public function test_a_bespoke_request_needs_a_reachable_whatsapp_number(): void
    {
        $this->postJson(route('custom-orders.store'), ['full_name' => 'Amina', 'whatsapp_number' => 'call me'])
            ->assertStatus(422)->assertJsonValidationErrors('whatsapp_number');
        $this->postJson(route('custom-orders.store'), ['full_name' => 'Amina', 'whatsapp_number' => '0712 345 678'])->assertOk();

        $this->assertSame('254712345678', CustomOrder::sole()->whatsapp_number, 'stored as the digits WhatsApp and tracking use');
    }
}
