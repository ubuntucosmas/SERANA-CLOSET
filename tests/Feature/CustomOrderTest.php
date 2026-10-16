<?php

namespace Tests\Feature;

use App\Models\ThemeSetting;
use App\Support\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomOrderTest extends TestCase
{
    use RefreshDatabase;

    /** Helper: decode the pre-filled message out of a wa.me url. */
    private function briefText(string $url): string
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        return $q['text'] ?? '';
    }

    public function test_brief_is_created_and_returns_whatsapp_url(): void
    {
        $res = $this->postJson(route('custom-orders.store'), [
            'full_name'       => 'Jane Doe',
            'whatsapp_number' => '254700000000',
        ]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertStringContainsString('wa.me', $res->json('whatsapp_url'));
        $this->assertDatabaseHas('custom_orders', [
            'full_name' => 'Jane Doe',
            'type'      => 'custom',
        ]);
    }

    /** Optional nullable fields must not trigger undefined-key errors. */
    public function test_optional_fields_do_not_error(): void
    {
        $this->postJson(route('custom-orders.store'), [
            'full_name'       => 'Jane Doe',
            'whatsapp_number' => '254700000000',
            'notes'           => 'Please add interior pockets',
            'occasion'        => 'Wedding',
            'deadline'        => now()->addMonth()->toDateString(),
            'selected_fit'    => 'Slim',
            'location'        => 'Nairobi',
        ])->assertOk()->assertJson(['success' => true]);
    }

    /** Impossible measurements or a past deadline are a 422, never a 500. */
    public function test_impossible_measurements_and_past_deadline_are_rejected(): void
    {
        $this->postJson(route('custom-orders.store'), [
            'full_name'       => 'Jane Doe',
            'whatsapp_number' => '254700000000',
            'bust_cm'         => -50,
            'waist_cm'        => 99999,
            'deadline'        => now()->subDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['bust_cm', 'waist_cm', 'deadline']);

        $this->assertDatabaseCount('custom_orders', 0);
    }

    public function test_name_and_whatsapp_are_required(): void
    {
        $this->postJson(route('custom-orders.store'), [])->assertStatus(422);
    }

    /** The brief is sent to the number set in the studio dashboard, normalized. */
    public function test_brief_routes_to_admin_configured_number(): void
    {
        ThemeSetting::set('whatsapp_number', '+254 741 589 200');

        $url = $this->postJson(route('custom-orders.store'), [
            'full_name'       => 'Jane Doe',
            'whatsapp_number' => '254700000000',
        ])->json('whatsapp_url');

        $this->assertStringStartsWith('https://wa.me/254741589200?text=', $url);
    }

    /** Priority Pass must surface in the brief so the atelier never misses it. */
    public function test_priority_pass_appears_in_brief(): void
    {
        $url = $this->postJson(route('custom-orders.store'), [
            'full_name'       => 'Jane Doe',
            'whatsapp_number' => '254700000000',
            'priority_pass'   => true,
        ])->json('whatsapp_url');

        $this->assertStringContainsString('Priority Pass', $this->briefText($url));
    }

    /** With no measurements, the brief shows the fitting note — not walls of N/A. */
    public function test_brief_omits_empty_measurements(): void
    {
        $url = $this->postJson(route('custom-orders.store'), [
            'full_name'       => 'Jane Doe',
            'whatsapp_number' => '254700000000',
        ])->json('whatsapp_url');

        $text = $this->briefText($url);
        $this->assertStringNotContainsString('N/A', $text);
        $this->assertStringContainsString('To be taken at the first fitting', $text);
    }

    public function test_number_normalization(): void
    {
        $this->assertSame('254741589200', WhatsApp::normalizeNumber('+254 741 589 200'));
        $this->assertSame('254741589200', WhatsApp::normalizeNumber('0741589200'));
        $this->assertSame('254741589200', WhatsApp::normalizeNumber('741589200'));
        $this->assertSame('254741589200', WhatsApp::normalizeNumber('254741589200'));
        $this->assertSame('', WhatsApp::normalizeNumber(null));
    }
}
