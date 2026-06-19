<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Services\MpesaService;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StkAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrder(): CustomOrder
    {
        return CustomOrder::create([
            'full_name'    => 'Jane Doe',
            'whatsapp_number' => '254700000000',
            'phone'        => '254700000000',
            'type'         => 'standard',
            'status'       => OrderStatus::PENDING_PAYMENT,
            'price_quoted' => 4500,
        ]);
    }

    public function test_stk_push_blocked_for_stranger_and_never_calls_safaricom(): void
    {
        // A stranger guessing a sequential order id must not be able to fire an
        // STK prompt to an arbitrary phone — the gateway must never be reached.
        $this->mock(MpesaService::class, fn ($m) => $m->shouldNotReceive('initiateStkPush'));

        $order = $this->pendingOrder();

        $this->postJson(route('mpesa.stk'), [
            'order_id' => $order->id,
            'phone'    => '254799999999',
        ])->assertStatus(404);
    }

    public function test_stk_push_allowed_for_the_session_that_created_the_order(): void
    {
        $this->mock(MpesaService::class, fn ($m) => $m->shouldReceive('initiateStkPush')
            ->once()
            ->andReturn(['success' => true, 'data' => ['CheckoutRequestID' => 'ws_CO_TEST']]));

        $order = $this->pendingOrder();

        $this->withSession(['serana.order_ids' => [$order->id]])
            ->postJson(route('mpesa.stk'), [
                'order_id' => $order->id,
                'phone'    => '254700000000',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
