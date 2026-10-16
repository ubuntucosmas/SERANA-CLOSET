<?php

namespace Tests\Feature;

use App\Mail\OrderReceiptMail;
use App\Models\CustomOrder;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudioFulfilmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Owner']);
    }

    private function brief(array $o = []): CustomOrder
    {
        return CustomOrder::create(array_merge(['full_name' => 'Client', 'whatsapp_number' => '254712345678', 'type' => 'custom', 'status' => 'designing', 'price_quoted' => 4500], $o));
    }

    public function test_studio_records_a_bespoke_payment_without_losing_the_stage(): void
    {
        $order = $this->brief();
        $this->actingAs($this->admin())->post(route('studio.order.record_payment', $order), ['amount' => 4500, 'reference' => 'QK12ABC'])
            ->assertSessionHas('success');

        $fresh = $order->fresh();
        $this->assertTrue($fresh->is_paid);
        $this->assertSame('designing', $fresh->status, 'production stage is kept');
        $this->assertSame('QK12ABC', $fresh->mpesa_receipt_number);
        $this->assertStringContainsString('Payment recorded by Owner: KSh 4,500, ref QK12ABC', $fresh->internal_notes);
    }

    public function test_studio_cannot_record_payment_for_shop_or_drop_orders(): void
    {
        $shop = CustomOrder::create(['full_name' => 'B', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 5000]);
        $this->actingAs($this->admin())->post(route('studio.order.record_payment', $shop), ['amount' => 5000, 'reference' => 'X'])->assertSessionHas('error');
        $this->assertFalse($shop->fresh()->is_paid);
    }

    public function test_recording_payment_requires_amount_and_reference_and_only_once(): void
    {
        $order = $this->brief();
        $this->actingAs($this->admin())->post(route('studio.order.record_payment', $order), ['amount' => 0, 'reference' => ''])
            ->assertSessionHasErrors(['amount', 'reference']);
        $order->update(['is_paid' => true]);
        $this->actingAs($this->admin())->post(route('studio.order.record_payment', $order), ['amount' => 4500, 'reference' => 'Y'])->assertSessionHas('error');
    }

    public function test_attention_lists_duplicate_payments_and_low_stock(): void
    {
        $order = CustomOrder::create(['full_name' => 'Twice', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => OrderStatus::PAID, 'is_paid' => true, 'price_quoted' => 5000]);
        foreach (['A', 'B'] as $id) $order->paymentAttempts()->create(['checkout_request_id' => "ws_$id", 'phone' => '254712345678', 'amount' => 5000, 'status' => PaymentAttempt::PAID]);
        Product::create(['name' => 'Nearly Gone', 'slug' => 'nearly-gone', 'price' => 100, 'batch_limit' => 5, 'batch_sold' => 4, 'in_stock' => true]);

        $this->actingAs($this->admin())->get(route('studio.index'))->assertInertia(fn ($page) => $page
            ->where('attention.awaiting.0.next_action', 'Paid 2 times: refund owed')
            ->where('attention.low_stock.0.note', '1 left'));
    }

    public function test_tracking_dates_use_nairobi_time_and_items_carry_size_labels(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 22:30:00', 'UTC')); // 01:30 on 16 Oct in Nairobi
        $order = CustomOrder::create(['full_name' => 'B', 'phone' => '254712345678', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => OrderStatus::PAID, 'is_paid' => true, 'price_quoted' => 5000,
            'items_json' => [['id' => 1, 'name' => 'Set', 'price' => 5000, 'quantity' => 1, 'options' => ['top_size' => 'M', 'bottom_size' => 'L']]]]);

        $payload = $this->postJson(route('track.lookup'), ['reference' => $order->cipher, 'phone' => '0712345678'])->json('order');
        $this->assertSame('Oct 16, 2026', $payload['placed_at']);
        $this->assertSame('Top M · Bottom L', $payload['items'][0]['size_label']);

        $html = (new OrderReceiptMail($order))->render();
        $this->assertStringContainsString('Top M · Bottom L', $html);
        $this->assertStringContainsString('Oct 16, 2026', $html);
        Carbon::setTestNow();
    }
}
