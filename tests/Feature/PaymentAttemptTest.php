<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Every STK push is tracked as its own PaymentAttempt so retried payments
 * can't orphan money: whichever prompt the customer actually pays settles
 * the order, and a cancelled retry never clobbers a live earlier prompt.
 */
class PaymentAttemptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.mpesa.callback_token' => 'secret',
            'services.mpesa.query_spacing_seconds' => 0,
        ]);
    }

    private function product(int $limit = 3, float $price = 5000): Product
    {
        return Product::create([
            'name'        => 'Limited Jacket',
            'slug'        => 'limited-jacket-' . uniqid(),
            'price'       => $price,
            'batch_limit' => $limit,
            'batch_sold'  => 0,
            'in_stock'    => true,
        ]);
    }

    private function pendingOrder(array $overrides = []): CustomOrder
    {
        return CustomOrder::create(array_merge([
            'full_name'    => 'Jane Doe',
            'email'        => 'jane@example.com',
            'phone'        => '254712345678',
            'whatsapp_number' => '254712345678',
            'type'         => 'standard',
            'status'       => OrderStatus::PENDING_PAYMENT,
            'price_quoted' => 5000,
            'is_paid'      => false,
        ], $overrides));
    }

    private function fakeStkPushSequence(array $checkoutIds): void
    {
        $sequence = Http::sequence();
        foreach ($checkoutIds as $id) {
            $sequence->push(['CheckoutRequestID' => $id]);
        }

        Http::fake([
            '*oauth/v1/generate*'         => Http::response(['access_token' => 'fake']),
            '*stkpush/v1/processrequest*' => $sequence,
        ]);
    }

    private function successCallback(string $checkoutId, int $amount = 5000): array
    {
        return ['Body' => ['stkCallback' => [
            'ResultCode' => 0,
            'CheckoutRequestID' => $checkoutId,
            'CallbackMetadata' => ['Item' => [
                ['Name' => 'Amount', 'Value' => $amount],
                ['Name' => 'MpesaReceiptNumber', 'Value' => 'RCPT1'],
            ]],
        ]]];
    }

    /**
     * The regression that motivated attempts: customer retries the push, then
     * pays the FIRST prompt still on their phone. Its callback must settle the
     * order even though the order's latest checkout id points at the retry.
     */
    public function test_paying_an_earlier_stk_attempt_still_settles_the_order(): void
    {
        Event::fake();
        $this->fakeStkPushSequence(['ws_CO_FIRST', 'ws_CO_SECOND']);
        $order = $this->pendingOrder();

        foreach (range(1, 2) as $push) {
            $this->withSession(['serana.order_ids' => [$order->id]])
                ->postJson(route('mpesa.stk'), ['order_id' => $order->id, 'phone' => '0712345678'])
                ->assertOk();
        }

        $this->assertSame('ws_CO_SECOND', $order->fresh()->mpesa_checkout_id);
        $this->assertSame(2, $order->paymentAttempts()->count());

        // The customer pays the FIRST prompt.
        $this->postJson('/payments/mpesa/callback/secret', $this->successCallback('ws_CO_FIRST'))
            ->assertOk();

        $fresh = $order->fresh();
        $this->assertTrue($fresh->is_paid);
        $this->assertSame(OrderStatus::PAID, $fresh->status);
        $this->assertSame(
            PaymentAttempt::PAID,
            PaymentAttempt::where('checkout_request_id', 'ws_CO_FIRST')->sole()->status
        );
    }

    /** A cancelled retry must not fail an order another prompt may still pay. */
    public function test_cancelled_retry_does_not_fail_order_with_a_live_attempt(): void
    {
        $order = $this->pendingOrder();
        $order->paymentAttempts()->create([
            'checkout_request_id' => 'ws_CO_FIRST',
            'phone' => '254712345678', 'amount' => 5000,
            'status' => PaymentAttempt::PENDING,
        ]);
        $order->paymentAttempts()->create([
            'checkout_request_id' => 'ws_CO_SECOND',
            'phone' => '254712345678', 'amount' => 5000,
            'status' => PaymentAttempt::PENDING,
        ]);

        $this->postJson('/payments/mpesa/callback/secret', ['Body' => ['stkCallback' => [
            'ResultCode' => 1032, // cancelled the retry
            'CheckoutRequestID' => 'ws_CO_SECOND',
        ]]])->assertOk();

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $fresh->status, 'first prompt may still be paid');
        $this->assertSame(
            PaymentAttempt::FAILED,
            PaymentAttempt::where('checkout_request_id', 'ws_CO_SECOND')->sole()->status
        );
    }

    /**
     * An order the expiry job already flipped to EXPIRED (customer dawdled past
     * the hold window) must still be reconciled — money taken must settle.
     */
    public function test_reconciliation_settles_expired_orders_with_pending_attempts(): void
    {
        Event::fake();
        Http::fake([
            '*oauth/v1/generate*'     => Http::response(['access_token' => 'fake']),
            '*stkpushquery/v1/query*' => Http::response(['ResultCode' => '0', 'ResultDesc' => 'Success']),
        ]);

        $product = $this->product();
        $order = $this->pendingOrder(['status' => OrderStatus::EXPIRED, 'created_at' => now()->subMinutes(30)]);
        // The hold lapsed and was released before payment completed.
        $order->stockReservations()->create([
            'product_id' => $product->id, 'quantity' => 1,
            'status' => 'released', 'expires_at' => now()->subMinutes(10),
        ]);
        $order->paymentAttempts()->create([
            'checkout_request_id' => 'ws_CO_LATE',
            'phone' => '254712345678', 'amount' => 5000,
            'status' => PaymentAttempt::PENDING,
        ])->forceFill(['created_at' => now()->subMinutes(10)])->save(); // past the 2-min callback grace

        $this->artisan('mpesa:reconcile')->assertSuccessful();

        $fresh = $order->fresh();
        $this->assertTrue($fresh->is_paid);
        $this->assertSame(OrderStatus::PAID, $fresh->status);
        // The customer paid: their released hold is committed to sold anyway.
        $this->assertSame(1, $product->fresh()->batch_sold);
    }

    /** The expiry job must not expire an order mid-payment. */
    public function test_expiry_job_spares_orders_with_a_recent_pending_attempt(): void
    {
        $inFlight = $this->pendingOrder(['created_at' => now()->subMinutes(30)]);
        $inFlight->paymentAttempts()->create([
            'checkout_request_id' => 'ws_CO_LIVE',
            'phone' => '254712345678', 'amount' => 5000,
            'status' => PaymentAttempt::PENDING,
        ]);

        $abandoned = $this->pendingOrder(['created_at' => now()->subMinutes(30)]);

        $this->artisan('stock:release-expired')->assertSuccessful();

        $this->assertSame(OrderStatus::PENDING_PAYMENT, $inFlight->fresh()->status);
        $this->assertSame(OrderStatus::EXPIRED, $abandoned->fresh()->status);
    }

    /** Never collect money for an order whose stock claim is unrecoverable. */
    public function test_initiate_rejected_for_expired_orders(): void
    {
        $order = $this->pendingOrder(['status' => OrderStatus::EXPIRED]);

        $this->withSession(['serana.order_ids' => [$order->id]])
            ->postJson(route('mpesa.stk'), ['order_id' => $order->id, 'phone' => '0712345678'])
            ->assertStatus(409);

        Http::fake();
        Http::assertNothingSent();
    }

    /**
     * A payment retry after the hold lapsed re-acquires the stock when it is
     * still free — and refuses the push when someone else bought it.
     */
    public function test_initiate_reacquires_released_holds_or_refuses(): void
    {
        $this->fakeStkPushSequence(['ws_CO_RETRY']);

        $product = $this->product(limit: 1);
        $order = $this->pendingOrder();
        $reservation = $order->stockReservations()->create([
            'product_id' => $product->id, 'quantity' => 1,
            'status' => 'released', 'expires_at' => now()->subMinutes(5),
        ]);

        // Stock still free → the hold is re-acquired and the push goes out.
        $this->withSession(['serana.order_ids' => [$order->id]])
            ->postJson(route('mpesa.stk'), ['order_id' => $order->id, 'phone' => '0712345678'])
            ->assertOk();
        $this->assertSame('held', $reservation->fresh()->status);

        // Now the batch sells out to someone else and the hold lapses again.
        // (refresh first: initiate re-held the row behind this stale instance)
        $reservation->refresh()->update(['status' => 'released']);
        $product->update(['batch_sold' => 1]);

        $this->withSession(['serana.order_ids' => [$order->id]])
            ->postJson(route('mpesa.stk'), ['order_id' => $order->id, 'phone' => '0712345678'])
            ->assertStatus(409);
        $this->assertSame('released', $reservation->fresh()->status);
    }
}
