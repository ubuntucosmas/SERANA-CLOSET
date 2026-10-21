<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checkout is the top of the money path: every line item must be validated
 * individually and everything price-relevant snapshotted from the catalog,
 * never taken from the client.
 */
class CheckoutIntegrityTest extends TestCase
{
    use \Tests\Concerns\DeliveryFixtures;
    use RefreshDatabase;

    private function product(float $price = 5000, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name'     => 'Atelier Jacket',
            'slug'     => 'atelier-jacket-' . uniqid(),
            'price'    => $price,
            'in_stock' => true,
        ], $overrides));
    }

    private function checkoutPayload(array $items): array
    {
        return [
            'full_name' => 'Buyer',
            'email'     => 'b@example.com',
            'address'   => '1 Street',
            'city'      => 'Nairobi',
            'delivery_option_id' => $this->freeDelivery(),
            'phone'     => '0712345678',
            'items'     => $items,
        ];
    }

    /**
     * A negative quantity would subtract from the server-computed total,
     * letting a crafted cart pay less than the goods are worth.
     */
    public function test_checkout_rejects_negative_zero_and_fractional_quantities(): void
    {
        $product = $this->product();

        foreach ([-1, 0, 1.5] as $quantity) {
            $this->postJson(route('checkout.process'), $this->checkoutPayload([
                ['id' => $product->id, 'quantity' => $quantity],
            ]))->assertStatus(422);
        }

        $this->assertSame(0, CustomOrder::count(), 'no order may be created from a tampered cart');
    }

    public function test_checkout_rejects_unknown_products(): void
    {
        $this->postJson(route('checkout.process'), $this->checkoutPayload([
            ['id' => 999999, 'quantity' => 1],
        ]))->assertStatus(422);
    }

    public function test_checkout_rejects_invalid_phone_numbers(): void
    {
        $product = $this->product();

        $payload = $this->checkoutPayload([['id' => $product->id, 'quantity' => 1]]);
        $payload['phone'] = '12345';

        $this->postJson(route('checkout.process'), $payload)->assertStatus(422);
    }

    /**
     * The stored line items must come from the catalog. A client-forged name
     * or price must neither change what is charged nor what the receipt shows.
     */
    public function test_checkout_snapshots_items_server_side(): void
    {
        $product = $this->product(price: 5000, overrides: ['garment_type' => 'hoodie', 'specifications' => ['sizes' => ['One size']]]);

        $this->postJson(route('checkout.process'), $this->checkoutPayload([
            [
                'id'       => $product->id,
                'quantity' => 2,
                'name'     => 'Totally Free Jacket', // forged
                'price'    => 1,                     // forged
            ],
        ]))->assertOk()->assertJson(['success' => true]);

        $order = CustomOrder::sole();
        $item = $order->items_json[0];

        $this->assertSame(10000.0, (float) $order->price_quoted);
        $this->assertSame($product->name, $item['name']);
        $this->assertSame(5000.0, (float) $item['price']);
        $this->assertSame(2, $item['quantity']);
        $this->assertSame('254712345678', $order->phone, 'phone is stored normalized');
    }

    /** The old product-level model (stock per size holds each size: CheckoutBySizeTest). */
    public function test_different_sizes_share_one_combined_stock_hold(): void
    {
        config(['serana.stock_v2' => 'off']);
        $product = $this->product(overrides: ['batch_limit' => 3]);
        $this->postJson(route('checkout.process'), $this->checkoutPayload([
            ['id' => $product->id, 'quantity' => 1, 'options' => ['top_size' => 'M']],
            ['id' => $product->id, 'quantity' => 2, 'options' => ['top_size' => 'L']],
        ]))->assertOk()->assertJsonPath('amount', 15000)->assertJsonStructure(['reference']);

        $order = CustomOrder::sole();
        $this->assertCount(2, $order->items_json);
        $this->assertSame('M', $order->items_json[0]['options']['top_size']);
        $this->assertSame('L', $order->items_json[1]['options']['top_size']);
        $this->assertSame(3, $order->stockReservations()->sole()->quantity);
    }

    /** The old product-level model (per size: CheckoutBySizeTest). */
    public function test_multiple_sizes_cannot_exceed_combined_stock(): void
    {
        config(['serana.stock_v2' => 'off']);
        $product = $this->product(overrides: ['batch_limit' => 2]);
        $this->postJson(route('checkout.process'), $this->checkoutPayload([
            ['id' => $product->id, 'quantity' => 2, 'options' => ['top_size' => 'M']],
            ['id' => $product->id, 'quantity' => 1, 'options' => ['top_size' => 'L']],
        ]))->assertUnprocessable();
        $this->assertDatabaseCount('custom_orders', 0);
        $this->assertDatabaseCount('stock_reservations', 0);
    }

    public function test_unavailable_products_cannot_be_ordered(): void
    {
        $product = $this->product(overrides: ['in_stock' => false, 'batch_limit' => null]);
        $this->postJson(route('checkout.process'), $this->checkoutPayload([
            ['id' => $product->id, 'quantity' => 1],
        ]))->assertUnprocessable();
        $this->assertDatabaseCount('custom_orders', 0);
    }
}
