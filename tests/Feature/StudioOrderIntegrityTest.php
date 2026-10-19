<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioOrderIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function order(bool $paid = false): CustomOrder
    {
        return CustomOrder::create([
            'full_name' => 'Buyer', 'type' => 'standard', 'whatsapp_number' => '254712345678',
            'status' => $paid ? OrderStatus::PAID : OrderStatus::PENDING_PAYMENT,
            'price_quoted' => 5000, 'is_paid' => $paid,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    public function test_studio_cannot_override_payment_confirmation(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin())->putJson(route('studio.update', $order), [
            'status' => $order->status, 'is_paid' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_paid');
        $this->assertFalse($order->fresh()->is_paid);
    }

    public function test_studio_cannot_fulfil_an_unpaid_shop_order(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin())->putJson(route('studio.update', $order), [
            'status' => 'shipped',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_paid_order_can_progress_without_changing_paid_amount(): void
    {
        $order = $this->order(true);
        $this->actingAs($this->admin())->putJson(route('studio.update', $order), [
            'status' => 'sewing', 'price_quoted' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('price_quoted');
        $this->putJson(route('studio.update', $order), ['status' => 'sewing'])
            ->assertRedirect();
        $this->assertTrue($order->fresh()->is_paid);
        $this->assertSame('sewing', $order->fresh()->status);
        $this->assertSame(5000.0, (float) $order->fresh()->price_quoted);
    }
}
