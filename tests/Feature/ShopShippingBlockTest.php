<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\OrderStatus;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase C follow-up: a shop order with a balance owed (a short payment, or a
 * swap to a dearer item) can't be moved to any fulfilment stage, including
 * shipped (handed over or collected), until the balance is paid.
 */
class ShopShippingBlockTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->owner = User::factory()->create(['role' => Role::OWNER]);
    }

    private function shop(int $price = 5000, ?Product $product = null): CustomOrder
    {
        $product ??= Product::create(['name' => 'Linen Set', 'slug' => 'linen-' . uniqid(), 'price' => $price, 'batch_limit' => 5, 'batch_sold' => 0, 'in_stock' => true]);
        $order = CustomOrder::create(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT,
            'price_quoted' => $price, 'items_json' => [['id' => $product->id, 'name' => $product->name, 'price' => $price, 'quantity' => 1]]]);
        $order->stockReservations()->create(['product_id' => $product->id, 'quantity' => 1, 'status' => 'held', 'expires_at' => now()->addMinutes(15)]);

        return $order;
    }

    private function move(CustomOrder $order, string $stage, array $extra = [])
    {
        return $this->actingAs($this->owner)->put(route('studio.update', $order), ['status' => $stage, 'internal_notes' => null] + $extra);
    }

    public function test_a_short_paid_shop_order_cannot_ship_or_start_until_the_balance_is_paid(): void
    {
        $order = $this->shop();
        app(OrderSettlementService::class)->recordPayment($order, 4000, 'mpesa_stk', Actor::safaricom(), 'QSHORT1');
        app(OrderSettlementService::class)->holdForBalance($order);

        foreach (['shipped', 'completed', 'sewing'] as $stage) {
            $this->move($order, $stage, ['override_reason' => 'owner wants to'])
                ->assertSessionHasErrors(['status' => 'KSh 1,000 is still owed on this order. Take the balance before it leaves the studio.']);
        }
        $this->assertSame('pending', $order->fresh()->fulfilment_status);

        app(OrderSettlementService::class)->recordPayment($order->fresh(), 1000, 'mpesa_stk', Actor::safaricom(), 'QSHORT2');
        $this->move($order, 'shipped')->assertSessionHasNoErrors();
        $this->assertSame('shipped', $order->fresh()->fulfilment_status);
    }

    public function test_a_swap_to_a_dearer_item_cannot_ship_until_the_difference_is_paid(): void
    {
        $jacket = Product::create(['name' => 'Last Jacket', 'slug' => 'last-jacket', 'price' => 5000, 'batch_limit' => 1, 'batch_sold' => 1, 'in_stock' => true]);
        $order = $this->shop(5000, $jacket);
        $order->stockReservations()->update(['status' => 'released']);
        app(OrderSettlementService::class)->recordPayment($order, 5000, 'mpesa_stk', Actor::safaricom(), 'QLATE1');
        $coat = Product::create(['name' => 'Wool Coat', 'slug' => 'wool-coat', 'price' => 6000, 'batch_limit' => 3, 'batch_sold' => 0, 'in_stock' => true]);
        app(OrderSettlementService::class)->swapItem($order->fresh(), $jacket->id, $coat->id, null, Actor::user($this->owner), 'Chose the coat');

        $this->move($order, 'shipped')->assertSessionHasErrors(['status' => 'KSh 1,000 is still owed on this order. Take the balance before it leaves the studio.']);
        $this->assertSame('pending', $order->fresh()->fulfilment_status);
    }
}
