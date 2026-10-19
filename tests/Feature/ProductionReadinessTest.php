<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Product;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Support\OrderStatus;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeders_refuse_production_before_writing_accounts(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        foreach ([new AdminSeeder, new DatabaseSeeder] as $seeder) {
            try { $seeder->run(); $this->fail('Production demo seeding must be refused.'); }
            catch (\LogicException $exception) { $this->assertStringContainsString('restricted', $exception->getMessage()); }
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_theme_upload_rejects_executable_content_and_preserves_old_asset(): void
    {
        Storage::fake('public');
        config(['filesystems.public_disk' => 'public']);
        ThemeSetting::set('site_logo', 'themes/old.png');
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('admin.theme.update'), ['key' => 'site_logo', 'value' => UploadedFile::fake()->create('payload.php', 1, 'application/x-php')])
            ->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->assertSame('themes/old.png', ThemeSetting::where('key', 'site_logo')->value('value'));
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_settings_reject_unknown_keys_and_unsafe_social_urls_atomically(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson(route('admin.theme.bulk_update'), ['settings' => ['unknown_secret' => 'value']])
            ->assertUnprocessable();
        $this->postJson(route('admin.theme.bulk_update'), ['settings' => ['site_name' => 'New name', 'instagram_url' => 'javascript:alert(1)']])
            ->assertUnprocessable()->assertJsonValidationErrors('settings.instagram_url');
        $this->assertDatabaseCount('theme_settings', 0);
    }

    public function test_catalogue_update_validates_secondary_uploads(): void
    {
        $product = Product::create(['name' => 'Jacket', 'slug' => 'jacket', 'price' => 5000]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->putJson(route('admin.catalog.update_product', $product), [
                'secondary_image_1' => UploadedFile::fake()->create('bad.php', 1, 'application/x-php'),
            ])->assertUnprocessable()->assertJsonValidationErrors('secondary_image_1');
    }

    /** A fractional price would create orders M-Pesa can never charge. */
    public function test_catalogue_prices_must_be_whole_shillings(): void
    {
        $product = Product::create(['name' => 'Jacket', 'slug' => 'jacket', 'price' => 5000]);
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = ['category_id' => \App\Models\Category::create(['name' => 'Tops', 'slug' => 'tops'])->id,
            'name' => 'Jacket', 'description' => 'Warm.', 'garment_type' => 'set'];

        $this->actingAs($admin)->putJson(route('admin.catalog.update_product', $product), $payload + ['price' => '4999.50'])
            ->assertUnprocessable()->assertJsonValidationErrors('price');
        $this->actingAs($admin)->put(route('admin.catalog.update_product', $product), $payload + ['price' => '5000.00'])
            ->assertSessionHasNoErrors();
        $this->assertSame('5000.00', $product->fresh()->price);
    }

    public function test_non_whole_order_amount_is_not_silently_rounded_before_payment(): void
    {
        Http::fake();
        $order = $this->order(['price_quoted' => 5000.25]);
        $this->withSession(['serana.order_ids' => [$order->id]])->postJson(route('mpesa.stk'), ['order_id' => $order->id, 'phone' => '0712345678'])
            ->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_a_nearby_but_wrong_callback_amount_cannot_settle_an_order(): void
    {
        config(['services.mpesa.callback_token' => 'test-token']);
        $order = $this->order(['mpesa_checkout_id' => 'CHECK-EXACT']);
        $this->postJson('/payments/mpesa/callback/test-token', ['Body' => ['stkCallback' => [
            'ResultCode' => 0, 'CheckoutRequestID' => 'CHECK-EXACT',
            'CallbackMetadata' => ['Item' => [['Name' => 'Amount', 'Value' => 4999.75]]],
        ]]])->assertOk();
        $this->assertFalse($order->fresh()->is_paid);
        $this->assertSame(OrderStatus::AMOUNT_MISMATCH, $order->fresh()->status);
    }

    public function test_malformed_callback_does_not_change_payment_state(): void
    {
        config(['services.mpesa.callback_token' => 'test-token']);
        $order = $this->order();
        $this->postJson('/payments/mpesa/callback/test-token', ['Body' => ['stkCallback' => ['ResultCode' => 0]]])->assertUnprocessable();
        $this->assertFalse($order->fresh()->is_paid);
    }

    public function test_paid_status_text_alone_never_issues_a_receipt_or_confirms_payment(): void
    {
        $order = $this->order(['status' => OrderStatus::PAID, 'is_paid' => false]);
        $this->withSession(['serana.order_ids' => [$order->id]])->getJson(route('mpesa.status', $order->id))
            ->assertJsonPath('is_paid', false)->assertJsonPath('receipt_url', null);
        $this->get(URL::temporarySignedRoute('orders.receipt', now()->addMinutes(5), ['order' => $order->id]))
            ->assertRedirect();
    }

    public function test_zero_batch_limit_is_zero_stock_rather_than_unlimited(): void
    {
        $product = Product::create(['name' => 'Jacket', 'slug' => 'jacket', 'price' => 5000, 'batch_limit' => 0]);
        $this->assertSame(0, $product->availableStock());
    }

    private function order(array $extra = []): CustomOrder
    {
        return CustomOrder::create(array_merge(['full_name' => 'Test Buyer', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 5000, 'is_paid' => false], $extra));
    }
}
