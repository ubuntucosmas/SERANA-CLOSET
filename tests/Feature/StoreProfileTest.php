<?php

namespace Tests\Feature;

use App\Mail\OrderReceiptMail;
use App\Mail\WhatsAppOrderPlacedMail;
use App\Models\CustomOrder;
use App\Models\Product;
use App\Models\User;
use App\Support\PaymentSettings;
use App\Support\StoreProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreProfileTest extends TestCase
{
    use RefreshDatabase;

    private function saveProfile(array $overrides = []): void
    {
        $settings = array_merge([
            'site_name' => 'Serana Closet', 'site_tagline' => 'Made in Nairobi', 'whatsapp_number' => '0711 222 333',
            'care_phone' => '+254 733 444 555', 'contact_email' => 'hello@serana.test', 'contact_address' => 'Imenti House, Nairobi',
            'notification_email' => 'owner@serana.test', 'instagram_url' => 'https://instagram.com/serana',
            'tiktok_url' => '', 'facebook_url' => '', 'x_url' => 'https://x.com/serana',
            'focal_1_label' => '', 'focal_2_label' => '', 'focal_3_label' => '',
        ], $overrides);
        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->post(route('admin.theme.bulk_update'), ['settings' => $settings])->assertSessionHasNoErrors();
        auth()->logout();
    }

    public function test_sensible_defaults_before_anything_is_set(): void
    {
        config(['services.whatsapp.number' => '0700000001']);
        $this->assertSame('Serana Closet', StoreProfile::name());
        $this->assertSame('254700000001', StoreProfile::whatsapp());
        $this->assertSame('254700000001', StoreProfile::phone(), 'calls fall back to WhatsApp');
        $this->assertSame('Nairobi, Kenya', StoreProfile::address());
        $this->assertSame('instagram', StoreProfile::socials()[0]['key']);
    }

    public function test_one_studio_save_updates_every_page(): void
    {
        $this->saveProfile();

        $this->get(route('contact'))->assertInertia(fn (Assert $p) => $p
            ->where('store.name', 'Serana Closet')
            ->where('store.whatsapp', '254711222333')
            ->where('store.whatsapp_display', '+254 711 222 333')
            ->where('store.whatsapp_url', 'https://wa.me/254711222333')
            ->where('store.phone', '254733444555')
            ->where('store.email', 'hello@serana.test')
            ->where('store.address', 'Imenti House, Nairobi')
            ->where('store.socials', [
                ['key' => 'instagram', 'label' => 'Instagram', 'url' => 'https://instagram.com/serana'],
                ['key' => 'x', 'label' => 'X', 'url' => 'https://x.com/serana'],
            ])
            ->where('whatsapp_number', '254711222333'));
    }

    public function test_custom_and_shop_order_whatsapp_messages_go_to_the_new_number(): void
    {
        $this->saveProfile(['whatsapp_number' => '0799 888 777']);
        $this->postJson(route('custom-orders.store'), ['full_name' => 'Jane', 'whatsapp_number' => '0712345678'])
            ->assertOk()->assertJsonPath('whatsapp_url', fn ($url) => str_starts_with($url, 'https://wa.me/254799888777?'));

        PaymentSettings::save(['mpesa_enabled' => false, 'whatsapp_enabled' => true, 'instructions' => 'Send Money to {whatsapp} ({store})', 'whatsapp_hold_hours' => 48]);
        $product = Product::create(['name' => 'Hoodie', 'slug' => 'h', 'price' => 2500, 'in_stock' => true, 'garment_type' => 'hoodie']);
        Mail::fake();
        $res = $this->postJson(route('checkout.process'), ['request_id' => (string) Str::uuid(), 'full_name' => 'B', 'email' => 'b@example.com', 'phone' => '0712345678',
            'address' => 'X', 'city' => 'Nairobi', 'payment_method' => 'whatsapp', 'items' => [['id' => $product->id, 'quantity' => 1]]])->assertOk();

        $this->assertStringStartsWith('https://wa.me/254799888777?', $res->json('whatsapp_url'));
        // Placeholders follow the profile: no stale number in the instructions.
        $this->assertSame('Send Money to +254 799 888 777 (Serana Closet)', $res->json('instructions'));
        Mail::assertQueued(WhatsAppOrderPlacedMail::class, fn ($m) => $m->hasBcc('owner@serana.test'));
    }

    public function test_receipts_and_owner_copies_use_the_profile(): void
    {
        $this->saveProfile(['site_name' => 'Serana Studio KE']);
        $order = CustomOrder::forceCreate(['full_name' => 'A', 'email' => 'a@example.com', 'type' => 'standard', 'status' => 'Paid', 'price_quoted' => 1000, 'is_paid' => true, 'phone' => '254712345678', 'whatsapp_number' => '254712345678', 'items_json' => []]);

        $html = (new OrderReceiptMail($order))->render();
        $this->assertStringContainsString('Serana Studio KE', $html);
        $this->assertStringContainsString('hello@serana.test', $html);
        $this->assertStringContainsString('+254 711 222 333', $html);
        $this->assertStringNotContainsString('studio@seranacloset.com', $html);
        $this->assertSame('owner@serana.test', StoreProfile::notificationEmail());
    }

    public function test_google_gets_the_stores_contacts_on_the_home_page(): void
    {
        $this->saveProfile();
        $this->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('seo.structured_data.@type', 'ClothingStore')
            ->where('seo.structured_data.telephone', '+254733444555')
            ->where('seo.structured_data.email', 'hello@serana.test')
            ->where('seo.structured_data.sameAs', ['https://instagram.com/serana', 'https://x.com/serana']));
    }

    public function test_bad_contact_details_are_refused(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->post(route('admin.theme.bulk_update'), ['settings' => ['site_name' => 'S', 'care_phone' => 'call me', 'notification_email' => 'nope', 'x_url' => 'not a url']])
            ->assertSessionHasErrors(['settings.care_phone', 'settings.notification_email', 'settings.x_url']);
    }
}
