<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Drop;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubmissionRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_bespoke_retry_reuses_saved_reference(): void
    {
        $payload = ['request_id' => (string) Str::uuid(), 'full_name' => 'Buyer', 'whatsapp_number' => '0712345678'];
        $first = $this->postJson(route('custom-orders.store'), $payload)->assertOk()->json();
        $second = $this->postJson(route('custom-orders.store'), $payload)->assertOk()->json();
        $this->assertSame($first, $second);
        $this->assertDatabaseCount('custom_orders', 1);
    }

    public function test_drop_retry_reuses_reservation_and_cannot_change_its_size(): void
    {
        config(['services.drops.enabled' => true]);
        $drop = Drop::create(['title' => 'Test Drop', 'price' => 5000, 'target_quantity' => 10, 'status' => 'live', 'sizes' => ['M', 'L']]);
        $payload = ['request_id' => (string) Str::uuid(), 'full_name' => 'Buyer', 'phone' => '0712345678', 'size' => 'M'];
        $first = $this->postJson(route('drops.reserve', $drop->slug), $payload)->assertOk()->json();
        $this->postJson(route('drops.reserve', $drop->slug), $payload)->assertOk()->assertJsonPath('order_id', $first['order_id']);
        $payload['size'] = 'L';
        $this->postJson(route('drops.reserve', $drop->slug), $payload)->assertUnprocessable()->assertJsonValidationErrors('request_id');
        $this->assertDatabaseCount('custom_orders', 1);
        $this->assertSame('M', CustomOrder::sole()->items_json['size']);
    }

    public function test_checkout_retry_does_not_reserve_stock_twice(): void
    {
        $product = Product::create(['name' => 'Jacket', 'slug' => 'jacket', 'price' => 5000, 'in_stock' => true, 'batch_limit' => 1]);
        $payload = ['request_id' => (string) Str::uuid(), 'full_name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '0712345678', 'address' => '1 Street', 'city' => 'Nairobi', 'items' => [['id' => $product->id, 'quantity' => 1]]];
        $first = $this->postJson(route('checkout.process'), $payload)->assertOk()->json();
        $this->postJson(route('checkout.process'), $payload)->assertOk()->assertJsonPath('order_id', $first['order_id']);
        $this->assertDatabaseCount('stock_reservations', 1);
        $this->assertDatabaseCount('custom_orders', 1);
    }

    /** A shopper restarting checkout is never blocked by their own abandoned hold, and the old order is closed. */
    public function test_new_checkout_supersedes_the_same_shoppers_abandoned_order(): void
    {
        $product = Product::create(['name' => 'Last One', 'slug' => 'last-one', 'price' => 5000, 'in_stock' => true, 'batch_limit' => 1]);
        $base = ['full_name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '0712345678', 'address' => '1 Street', 'city' => 'Nairobi', 'items' => [['id' => $product->id, 'quantity' => 1]]];

        $first = $this->postJson(route('checkout.process'), $base + ['request_id' => (string) Str::uuid()])->assertOk()->json();
        // Same browser, refreshed page: a new request_id for the same last unit.
        $second = $this->postJson(route('checkout.process'), $base + ['request_id' => (string) Str::uuid()])->assertOk()->json();

        $this->assertNotSame($first['order_id'], $second['order_id']);
        $this->assertSame(\App\Support\OrderStatus::EXPIRED, \App\Models\CustomOrder::find($first['order_id'])->status);
        $this->assertSame(1, \App\Models\StockReservation::where('status', 'held')->count(), 'only the new order holds the unit');
    }

    /** An order with an M-Pesa prompt in flight is never superseded: the customer may be paying it. */
    public function test_new_checkout_keeps_an_order_whose_payment_is_in_progress(): void
    {
        $product = Product::create(['name' => 'Roomy', 'slug' => 'roomy', 'price' => 5000, 'in_stock' => true]);
        $base = ['full_name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '0712345678', 'address' => '1 Street', 'city' => 'Nairobi', 'items' => [['id' => $product->id, 'quantity' => 1]]];
        $first = $this->postJson(route('checkout.process'), $base + ['request_id' => (string) Str::uuid()])->assertOk()->json();
        \App\Models\PaymentAttempt::create(['custom_order_id' => $first['order_id'], 'checkout_request_id' => 'ws_CO_LIVE', 'phone' => '254712345678', 'amount' => 5000, 'status' => 'pending']);

        $this->postJson(route('checkout.process'), $base + ['request_id' => (string) Str::uuid()])->assertOk();

        $this->assertSame(\App\Support\OrderStatus::PENDING_PAYMENT, \App\Models\CustomOrder::find($first['order_id'])->status);
    }
}
