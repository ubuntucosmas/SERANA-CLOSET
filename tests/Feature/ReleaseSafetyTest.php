<?php

namespace Tests\Feature;

use App\Models\ThemeSetting;
use App\Models\User;
use App\Support\PublicUrl;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** V1 release hardening: staff accounts keep their history, settings stay private, links follow the storage disk. */
class ReleaseSafetyTest extends TestCase
{
    use RefreshDatabase;

    // ---- Staff can't delete their own accounts ----

    public function test_staff_cannot_delete_their_own_account(): void
    {
        foreach ([Role::OWNER, Role::MANAGER, Role::CASHIER] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertForbidden();

            $this->assertNotNull($user->fresh(), "a {$role} keeps their account");
            $this->assertAuthenticatedAs($user);
            $this->assertFalse($this->actingAs($user)->get(route('profile.edit'))->viewData('page')['props']['canDeleteAccount']);
        }
    }

    public function test_a_customer_can_still_delete_their_account(): void
    {
        $user = User::factory()->create(['role' => Role::CLIENT]);
        $this->assertTrue($this->actingAs($user)->get(route('profile.edit'))->viewData('page')['props']['canDeleteAccount']);

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

        $this->assertNull($user->fresh());
    }

    // ---- Public pages carry only public settings ----

    private function seedSettings(): void
    {
        foreach ([
            'site_name' => 'Serana Closet', 'contact_email' => 'hello@serana.test', 'notification_email' => 'owner-inbox@serana.test',
            'stock_v2' => '1', 'shop_policy' => '{"cashier_discount_percent":10}', 'order_policy' => '{"deposit_percent":50}',
            'payment_settings' => '{"mpesa_enabled":true}', 'site_favicon' => 'themes/icon.png', 'cat_men_bg' => 'themes/men.jpg',
        ] as $key => $value) {
            ThemeSetting::set($key, $value);
        }
    }

    public function test_visitors_never_receive_private_settings(): void
    {
        $this->seedSettings();

        $page = $this->get('/');
        $settings = $page->viewData('page')['props']['theme_settings'];

        $this->assertSame('Serana Closet', $settings['site_name']);
        $this->assertSame('hello@serana.test', $settings['contact_email']);
        $this->assertSame('/storage/themes/men.jpg', $settings['cat_men_bg']);
        foreach (['notification_email', 'stock_v2', 'shop_policy', 'order_policy', 'payment_settings', 'custom_order_options'] as $private) {
            $this->assertArrayNotHasKey($private, $settings, "{$private} stays on the server");
        }
        $page->assertDontSee('owner-inbox@serana.test');
        $page->assertDontSee('cashier_discount_percent');
        // The favicon is the disk URL once, not /storage/storage/.
        $page->assertSee('href="/storage/themes/icon.png"', false);
    }

    public function test_the_studio_still_gets_the_owner_inbox_it_edits(): void
    {
        $this->seedSettings();
        $owner = User::factory()->create(['role' => Role::OWNER]);

        $settings = $this->actingAs($owner)->get(route('studio.index'))->viewData('page')['props']['theme_settings'];

        $this->assertSame('owner-inbox@serana.test', $settings['notification_email']);
        $this->assertArrayNotHasKey('shop_policy', $settings);
    }

    // ---- Bespoke brief links follow the configured public disk ----

    public function test_brief_image_links_use_the_local_public_disk_url(): void
    {
        Mail::fake();
        Storage::fake('public');
        config(['filesystems.public_disk' => 'public', 'app.url' => 'https://serana.test']);

        $json = $this->postJson(route('custom-orders.store'), [
            'request_id' => (string) Str::uuid(), 'full_name' => 'Amina', 'whatsapp_number' => '0712345678',
            'images' => [UploadedFile::fake()->image('dress.jpg', 400, 400)],
        ])->assertOk()->json();

        $path = \App\Models\CustomOrder::sole()->inspiration_image_paths[0];
        $this->assertStringContainsString(rawurlencode('https://serana.test/storage/' . $path), $json['whatsapp_url']);
        $this->assertStringNotContainsString(rawurlencode('/storage/storage/'), $json['whatsapp_url']);
    }

    public function test_absolute_links_use_the_remote_disk_url_when_configured(): void
    {
        config([
            'filesystems.public_disk' => 's3',
            'filesystems.disks.s3' => ['driver' => 's3', 'key' => 'k', 'secret' => 's', 'region' => 'auto', 'bucket' => 'serana',
                'url' => 'https://cdn.serana.test', 'endpoint' => 'https://example.r2.cloudflarestorage.com', 'use_path_style_endpoint' => true],
        ]);

        $this->assertSame('https://cdn.serana.test/inspiration_images/a.webp', PublicUrl::absolute('inspiration_images/a.webp'));
        $this->assertNull(PublicUrl::absolute(null));
    }
}
