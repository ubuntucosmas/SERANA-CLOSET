<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Services\MpesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MpesaPaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $overrides = []): CustomOrder
    {
        return CustomOrder::create(array_merge([
            'full_name'    => 'Test Buyer',
            'email'        => 'buyer@example.com',
            'phone'        => '254700000000',
            'whatsapp_number' => '254700000000',
            'type'         => 'standard',
            'status'       => 'Pending Payment',
            'price_quoted' => 5000,
            'is_paid'      => false,
            'mpesa_checkout_id' => 'ws_CO_TEST123',
        ], $overrides));
    }

    /**
     * The OAuth token must be cached, not re-fetched per call. Safaricom
     * rate-limits token generation; re-fetching (esp. in the reconcile loop)
     * trips it and silently strands paid orders.
     */
    public function test_access_token_is_cached_across_calls(): void
    {
        Http::fake(['*oauth/v1/generate*' => Http::response(['access_token' => 'tok-1', 'expires_in' => 3600])]);

        $service = app(MpesaService::class);
        $this->assertSame('tok-1', $service->getAccessToken());
        $this->assertSame('tok-1', $service->getAccessToken());

        Http::assertSentCount(1); // one token request despite two calls
    }

    /** A failed auth must NOT be cached as null — the next call should retry. */
    public function test_failed_auth_is_not_cached(): void
    {
        Http::fake(['*oauth/v1/generate*' => Http::response('unauthorized', 401)]);

        $service = app(MpesaService::class);
        $this->assertNull($service->getAccessToken());
        $this->assertNull($service->getAccessToken());

        Http::assertSentCount(2); // retried, not poisoned by a cached null
    }

    /** The amount sent to Daraja must come from the order, never the client. */
    public function test_stk_push_ignores_client_supplied_amount(): void
    {
        Http::fake([
            '*oauth/v1/generate*'       => Http::response(['access_token' => 'fake-token']),
            '*stkpush/v1/processrequest*' => Http::response(['CheckoutRequestID' => 'ws_CO_NEW']),
        ]);

        $order = $this->makeOrder(['price_quoted' => 5000]);

        // Initiation is authorized to the session that created the order.
        $this->withSession(['serana.order_ids' => [$order->id]])
            ->postJson(route('mpesa.stk'), [
                'order_id' => $order->id,
                'phone'    => '0700000000',
                'amount'   => 1, // malicious underpayment attempt
            ])->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'processrequest')
                && (int) $request['Amount'] === 5000; // server price, not the client's 1
        });
    }

    /** A callback without the secret token must be rejected and change nothing. */
    public function test_callback_rejected_without_valid_token(): void
    {
        $order = $this->makeOrder();

        $this->postJson('/payments/mpesa/callback/wrong-token', [
            'Body' => ['stkCallback' => [
                'ResultCode' => 0,
                'CheckoutRequestID' => $order->mpesa_checkout_id,
                'CallbackMetadata' => ['Item' => [
                    ['Name' => 'Amount', 'Value' => 5000],
                    ['Name' => 'MpesaReceiptNumber', 'Value' => 'ABC123'],
                ]],
            ]],
        ])->assertStatus(403);

        $this->assertFalse($order->fresh()->is_paid);
    }

    /**
     * Fail closed: with no token configured on the server, even a callback
     * that guesses the empty token is rejected; a URL without a token never
     * reaches the handler at all.
     */
    public function test_callback_rejected_when_no_token_is_configured_or_supplied(): void
    {
        config(['services.mpesa.callback_token' => '']);
        $order = $this->makeOrder();
        $payload = ['Body' => ['stkCallback' => [
            'ResultCode' => 0,
            'CheckoutRequestID' => $order->mpesa_checkout_id,
            'CallbackMetadata' => ['Item' => [['Name' => 'Amount', 'Value' => 5000]]],
        ]]];

        $this->postJson('/payments/mpesa/callback/anything', $payload)->assertStatus(403);
        $this->postJson('/payments/mpesa/callback/', $payload)->assertNotFound();

        $this->assertFalse($order->fresh()->is_paid);
    }

    /** A correct-token callback whose amount differs from the quote must not mark paid. */
    public function test_callback_rejects_amount_mismatch(): void
    {
        config(['services.mpesa.callback_token' => 'secret']);
        $order = $this->makeOrder(['price_quoted' => 5000]);

        $this->postJson('/payments/mpesa/callback/secret', [
            'Body' => ['stkCallback' => [
                'ResultCode' => 0,
                'CheckoutRequestID' => $order->mpesa_checkout_id,
                'CallbackMetadata' => ['Item' => [
                    ['Name' => 'Amount', 'Value' => 1], // underpaid
                    ['Name' => 'MpesaReceiptNumber', 'Value' => 'ABC123'],
                ]],
            ]],
        ])->assertOk();

        // Phase C (changed on purpose): the shilling is booked as a part payment; the order is not paid.
        $fresh = $order->fresh();
        $this->assertFalse($fresh->is_paid);
        $this->assertSame(['deposit_paid', 1, 4999], [$fresh->payment_status, $fresh->amount_paid, $fresh->balance_due]);
    }

    /** A correct-token, correct-amount callback marks the order paid. */
    public function test_callback_marks_paid_on_correct_amount(): void
    {
        Event::fake([\App\Events\MpesaWebhookHandled::class]); // only the receipt event: model events must still fire
        config(['services.mpesa.callback_token' => 'secret']);
        $order = $this->makeOrder(['price_quoted' => 5000]);

        $this->postJson('/payments/mpesa/callback/secret', [
            'Body' => ['stkCallback' => [
                'ResultCode' => 0,
                'CheckoutRequestID' => $order->mpesa_checkout_id,
                'CallbackMetadata' => ['Item' => [
                    ['Name' => 'Amount', 'Value' => 5000],
                    ['Name' => 'MpesaReceiptNumber', 'Value' => 'ABC123'],
                ]],
            ]],
        ])->assertOk();

        $fresh = $order->fresh();
        $this->assertTrue($fresh->is_paid);
        $this->assertSame('Paid', $fresh->status);
        $this->assertSame('ABC123', $fresh->mpesa_receipt_number);
    }

    /** Receipts are not reachable without a valid signature (no IDOR). */
    public function test_receipt_requires_valid_signature(): void
    {
        $order = $this->makeOrder(['is_paid' => true, 'status' => 'Paid']);

        $this->get("/orders/{$order->id}/receipt")->assertStatus(403);
    }

    /** A stranger cannot read another order's payment status. */
    public function test_status_hidden_from_unrelated_session(): void
    {
        $order = $this->makeOrder(['is_paid' => true, 'status' => 'Paid']);

        $this->getJson(route('mpesa.status', $order->id))->assertStatus(404);
    }
}
