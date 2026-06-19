<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Product;
use App\Models\StockReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $limit = 3, int $sold = 0, float $price = 5000): Product
    {
        return Product::create([
            'name'        => 'Limited Jacket',
            'slug'        => 'limited-jacket-' . uniqid(),
            'price'       => $price,
            'batch_limit' => $limit,
            'batch_sold'  => $sold,
            'in_stock'    => true,
        ]);
    }

    private function orderHolding(Product $product, int $qty = 2, array $overrides = []): CustomOrder
    {
        $order = CustomOrder::create(array_merge([
            'full_name'         => 'Buyer',
            'email'             => 'b@example.com',
            'phone'             => '254700000000',
            'whatsapp_number'   => '254700000000',
            'type'              => 'standard',
            'status'            => 'Pending Payment',
            'price_quoted'      => $product->price * $qty,
            'is_paid'           => false,
            'mpesa_checkout_id' => 'ws_CO_' . uniqid(),
        ], $overrides));

        $order->stockReservations()->create([
            'product_id' => $product->id,
            'quantity'   => $qty,
            'status'     => 'held',
            'expires_at' => now()->addMinutes(15),
        ]);

        return $order;
    }

    public function test_checkout_holds_stock_without_marking_it_sold(): void
    {
        $product = $this->product(limit: 3, sold: 0);

        $this->postJson(route('checkout.process'), [
            'full_name' => 'Buyer',
            'email'     => 'b@example.com',
            'address'   => '1 Street',
            'city'      => 'Nairobi',
            'phone'     => '254700000000',
            'items'     => [['id' => $product->id, 'quantity' => 2]],
        ])->assertOk()->assertJson(['success' => true]);

        $product->refresh();
        $this->assertSame(0, $product->batch_sold, 'stock must not be sold before payment');
        $this->assertSame(2, $product->reservedQuantity());
        $this->assertSame(1, $product->availableStock());
    }

    public function test_checkout_blocks_when_only_reservations_remain(): void
    {
        $product = $this->product(limit: 2, sold: 0);
        $this->orderHolding($product, qty: 2); // batch fully held by an unpaid order

        $this->postJson(route('checkout.process'), [
            'full_name' => 'Second Buyer',
            'email'     => 'c@example.com',
            'address'   => '2 Street',
            'city'      => 'Nairobi',
            'phone'     => '254711111111',
            'items'     => [['id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_paid_callback_commits_held_stock_to_sold(): void
    {
        Event::fake();
        config(['services.mpesa.callback_token' => 'secret']);
        $product = $this->product(limit: 3, sold: 0, price: 5000);
        $order = $this->orderHolding($product, qty: 2); // price_quoted = 10000

        $this->postJson('/payments/mpesa/callback/secret', [
            'Body' => ['stkCallback' => [
                'ResultCode' => 0,
                'CheckoutRequestID' => $order->mpesa_checkout_id,
                'CallbackMetadata' => ['Item' => [
                    ['Name' => 'Amount', 'Value' => 10000],
                    ['Name' => 'MpesaReceiptNumber', 'Value' => 'RCPT1'],
                ]],
            ]],
        ])->assertOk();

        $product->refresh();
        $this->assertSame(2, $product->batch_sold);
        $this->assertSame(0, $product->reservedQuantity());
        $this->assertSame('committed', $order->stockReservations()->first()->status);
    }

    public function test_failed_callback_releases_held_stock(): void
    {
        config(['services.mpesa.callback_token' => 'secret']);
        $product = $this->product(limit: 3, sold: 0, price: 5000);
        $order = $this->orderHolding($product, qty: 2);

        $this->postJson('/payments/mpesa/callback/secret', [
            'Body' => ['stkCallback' => [
                'ResultCode' => 1032, // user cancelled
                'CheckoutRequestID' => $order->mpesa_checkout_id,
            ]],
        ])->assertOk();

        $product->refresh();
        $this->assertSame(0, $product->batch_sold);
        $this->assertSame(0, $product->reservedQuantity());
        $this->assertSame('released', $order->stockReservations()->first()->status);
    }

    public function test_expired_holds_are_released_by_scheduler(): void
    {
        $product = $this->product(limit: 3, sold: 0);
        $order = $this->orderHolding($product, qty: 2, overrides: [
            'created_at' => now()->subHour(),
        ]);
        // Force the hold to be in the past.
        $order->stockReservations()->update(['expires_at' => now()->subMinute()]);

        $this->artisan('stock:release-expired')->assertSuccessful();

        $this->assertSame(0, $product->fresh()->reservedQuantity());
        $this->assertSame('Expired', $order->fresh()->status);
    }

    public function test_reconciliation_confirms_payment_when_callback_was_missed(): void
    {
        Event::fake();
        Http::fake([
            '*oauth/v1/generate*'      => Http::response(['access_token' => 'fake']),
            '*stkpushquery/v1/query*'  => Http::response(['ResultCode' => '0', 'ResultDesc' => 'Success']),
        ]);

        $product = $this->product(limit: 3, sold: 0, price: 5000);
        $order = $this->orderHolding($product, qty: 1, overrides: [
            'created_at' => now()->subMinutes(10),
        ]);

        $this->artisan('mpesa:reconcile')->assertSuccessful();

        $fresh = $order->fresh();
        $this->assertTrue($fresh->is_paid);
        $this->assertSame('Paid', $fresh->status);
        $this->assertSame(1, $product->fresh()->batch_sold);
    }

    public function test_reconciliation_retries_through_spike_arrest_throttle(): void
    {
        // Daraja spike-arrests the query endpoint; a paid order must not be
        // skipped just because the first query was rate-limited.
        Event::fake();
        Http::fake([
            '*oauth/v1/generate*' => Http::response(['access_token' => 'fake']),
            '*stkpushquery/v1/query*' => Http::sequence()
                ->push(['fault' => ['detail' => ['errorcode' => 'policies.ratelimit.SpikeArrestViolation']]], 429)
                ->push(['ResultCode' => '0', 'ResultDesc' => 'Success'], 200),
        ]);

        $product = $this->product(limit: 3, sold: 0, price: 5000);
        $order = $this->orderHolding($product, qty: 1, overrides: [
            'created_at' => now()->subMinutes(10),
        ]);

        $this->artisan('mpesa:reconcile')->assertSuccessful();

        $this->assertTrue($order->fresh()->is_paid);
    }
}
