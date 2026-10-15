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
}
