<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\OrderProgressSnap;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attrs = []): CustomOrder
    {
        return CustomOrder::create(array_merge([
            'full_name'       => 'Jane Doe',
            'whatsapp_number' => '254700000000',
            'type'            => 'custom',
            'status'          => 'designing',
        ], $attrs));
    }

    public function test_lookup_succeeds_with_reference_and_phone(): void
    {
        $order = $this->order();

        $res = $this->postJson(route('track.lookup'), [
            'reference' => $order->cipher,
            'phone'     => '254700000000',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.reference', $order->cipher)
            ->assertJsonPath('order.status', 'designing');
    }

    public function test_phone_matches_across_formats(): void
    {
        $order = $this->order(['whatsapp_number' => '0700000000']);

        $this->postJson(route('track.lookup'), [
            'reference' => $order->cipher,
            'phone'     => '+254 700 000 000',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_wrong_phone_is_rejected_without_leaking(): void
    {
        $order = $this->order();

        $this->postJson(route('track.lookup'), [
            'reference' => $order->cipher,
            'phone'     => '254711111111',
        ])->assertStatus(404)->assertJsonPath('success', false);
    }

    public function test_unknown_reference_is_rejected(): void
    {
        $this->postJson(route('track.lookup'), [
            'reference' => 'SRN-DEADBEEF-2026',
            'phone'     => '254700000000',
        ])->assertStatus(404);
    }

    public function test_payload_excludes_pii_and_internal_notes(): void
    {
        $order = $this->order([
            'email'          => 'secret@example.com',
            'address'        => '123 Private St',
            'internal_notes' => 'VIP — handle personally',
        ]);

        $payload = $this->postJson(route('track.lookup'), [
            'reference' => $order->cipher,
            'phone'     => '254700000000',
        ])->json('order');

        $flat = json_encode($payload);
        $this->assertStringNotContainsString('secret@example.com', $flat);
        $this->assertStringNotContainsString('123 Private St', $flat);
        $this->assertStringNotContainsString('VIP', $flat);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('internal_notes', $payload);
    }

    public function test_receipt_link_only_appears_once_paid(): void
    {
        $unpaid = $this->order(['type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT]);
        $this->assertNull($this->postJson(route('track.lookup'), [
            'reference' => $unpaid->cipher,
            'phone'     => '254700000000',
        ])->json('order.receipt_url'));

        $paid = $this->order([
            'type'     => 'standard',
            'status'   => OrderStatus::PAID,
            'is_paid'  => true,
            'price_quoted' => 5000, // nothing can be paid for KSh 0
        ]);
        $this->assertStringContainsString('signature', $this->postJson(route('track.lookup'), [
            'reference' => $paid->cipher,
            'phone'     => '254700000000',
        ])->json('order.receipt_url'));
    }

    public function test_progress_snaps_are_exposed(): void
    {
        $order = $this->order();
        OrderProgressSnap::create([
            'custom_order_id' => $order->id,
            'image_path'      => 'progress_snaps/example.webp',
            'description'     => 'First fitting',
        ]);

        $progress = $this->postJson(route('track.lookup'), [
            'reference' => $order->cipher,
            'phone'     => '254700000000',
        ])->json('order.progress');

        $this->assertCount(1, $progress);
        $this->assertSame('First fitting', $progress[0]['description']);
    }
}
