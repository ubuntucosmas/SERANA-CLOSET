<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Archiving is a Studio tidy-up. It must never detach a customer's money from
 * their order, erase payment evidence, or hide the order from tracking.
 */
class ArchivedOrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mpesa.callback_token' => 'secret', 'services.mpesa.query_spacing_seconds' => 0]);
    }

    private function order(array $overrides = []): CustomOrder
    {
        return CustomOrder::create(array_merge([
            'full_name' => 'Jane Doe', 'phone' => '254712345678', 'whatsapp_number' => '254712345678',
            'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 5000, 'is_paid' => false,
        ], $overrides));
    }

    private function attempt(CustomOrder $order, string $id = 'ws_CO_A', string $status = PaymentAttempt::PENDING): PaymentAttempt
    {
        return $order->paymentAttempts()->create(['checkout_request_id' => $id, 'phone' => '254712345678', 'amount' => 5000, 'status' => $status]);
    }

    private function paidCallback(string $id): array
    {
        return ['Body' => ['stkCallback' => ['ResultCode' => 0, 'CheckoutRequestID' => $id, 'CallbackMetadata' => ['Item' => [
            ['Name' => 'Amount', 'Value' => 5000], ['Name' => 'MpesaReceiptNumber', 'Value' => 'RCPT9'],
        ]]]]];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_paid_callback_settles_and_restores_an_archived_order(): void
    {
        Event::fake();
        $product = Product::create(['name' => 'Jacket', 'slug' => 'jacket', 'price' => 5000, 'batch_limit' => 2, 'batch_sold' => 0, 'in_stock' => true]);
        $order = $this->order();
        $order->stockReservations()->create(['product_id' => $product->id, 'quantity' => 1, 'status' => 'held', 'expires_at' => now()->addMinutes(10)]);
        $this->attempt($order);
        $order->delete(); // archived before Safaricom confirmed

        $this->postJson('/payments/mpesa/callback/secret', $this->paidCallback('ws_CO_A'))
            ->assertOk()->assertJsonPath('ResultCode', 0);

        $fresh = CustomOrder::withTrashed()->find($order->id);
        $this->assertTrue($fresh->is_paid);
        $this->assertSame(OrderStatus::PAID, $fresh->status);
        $this->assertNull($fresh->deleted_at, 'a paid order returns to the active list for fulfilment');
        $this->assertSame(1, $product->fresh()->batch_sold);
    }

    public function test_reconciliation_settles_an_archived_order_when_the_callback_is_lost(): void
    {
        Event::fake();
        Http::fake(['*oauth/v1/generate*' => Http::response(['access_token' => 'fake']), '*stkpushquery/v1/query*' => Http::response(['ResultCode' => '0'])]);
        $order = $this->order();
        $this->attempt($order)->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $order->delete();

        $this->artisan('mpesa:reconcile')->assertSuccessful();

        $this->assertTrue(CustomOrder::withTrashed()->find($order->id)->is_paid);
    }

    public function test_studio_cannot_archive_an_order_with_a_payment_in_progress(): void
    {
        $order = $this->order();
        $this->attempt($order);

        $this->actingAs($this->admin())->delete(route('studio.order.destroy', $order))->assertSessionHas('error');
        $this->assertNull($order->fresh()->deleted_at);
    }

    public function test_studio_cannot_archive_a_paid_order_before_it_is_fulfilled(): void
    {
        $order = $this->order(['is_paid' => true, 'status' => OrderStatus::PAID]);
        $this->actingAs($this->admin())->delete(route('studio.order.destroy', $order))->assertSessionHas('error');
        $this->assertNull($order->fresh()->deleted_at);

        $order->update(['status' => 'shipped']);
        $this->actingAs($this->admin())->delete(route('studio.order.destroy', $order))->assertSessionHas('success');
        $this->assertNotNull(CustomOrder::withTrashed()->find($order->id)->deleted_at);
    }

    public function test_orders_with_payment_history_cannot_be_permanently_deleted(): void
    {
        $order = $this->order();
        $this->attempt($order, 'ws_CO_F', PaymentAttempt::FAILED);
        $order->update(['status' => OrderStatus::PAYMENT_FAILED]);
        $order->delete();

        $this->actingAs($this->admin())->delete(route('studio.order.force_delete', $order->id))->assertSessionHas('error');
        $this->assertSame(1, PaymentAttempt::where('custom_order_id', $order->id)->count());
        $this->assertNotNull(CustomOrder::withTrashed()->find($order->id));
    }

    public function test_unpaid_order_without_payment_history_can_be_purged(): void
    {
        $order = $this->order(['status' => OrderStatus::EXPIRED]);
        $order->delete();

        $this->actingAs($this->admin())->delete(route('studio.order.force_delete', $order->id))->assertSessionHas('success');
        $this->assertNull(CustomOrder::withTrashed()->find($order->id));
    }

    public function test_archived_orders_remain_trackable(): void
    {
        $order = $this->order(['is_paid' => true, 'status' => 'shipped']);
        $order->delete();

        $this->postJson(route('track.lookup'), ['reference' => $order->cipher, 'phone' => '0712345678'])
            ->assertOk()->assertJsonPath('order.reference', $order->cipher);
    }

    public function test_archived_paid_orders_keep_a_working_receipt_link(): void
    {
        $order = $this->order(['is_paid' => true, 'status' => 'shipped']);
        $order->delete();

        $receiptUrl = $this->postJson(route('track.lookup'), ['reference' => $order->cipher, 'phone' => '0712345678'])
            ->assertOk()->json('order.receipt_url');

        $this->assertNotNull($receiptUrl);
        $this->get($receiptUrl)->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_archived_unpaid_orders_still_get_no_receipt(): void
    {
        $order = $this->order();
        $order->delete();

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('orders.receipt', now()->addDay(), ['order' => $order->id]);

        // Found despite the archive, but refused because it is unpaid.
        $response = $this->get($url)->assertRedirect();
        $this->assertNotSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_amount_mismatch_survives_a_later_failed_prompt(): void
    {
        $order = $this->order(['status' => OrderStatus::AMOUNT_MISMATCH]);
        $this->attempt($order, 'ws_CO_LATE');

        $this->postJson('/payments/mpesa/callback/secret', ['Body' => ['stkCallback' => ['ResultCode' => 1032, 'CheckoutRequestID' => 'ws_CO_LATE']]])->assertOk();

        $this->assertSame(OrderStatus::AMOUNT_MISMATCH, $order->fresh()->status);
    }

    public function test_catalogue_rejects_a_zero_price(): void
    {
        $category = \App\Models\Category::create(['name' => 'Tops', 'slug' => 'tops']);
        $this->actingAs($this->admin())->postJson(route('admin.catalog.store_product'), [
            'category_id' => $category->id, 'name' => 'Free Thing', 'description' => 'x', 'price' => 0, 'garment_type' => 'hoodie',
        ])->assertUnprocessable()->assertJsonPath('errors.price.0', 'Price must be at least KSh 1.');
    }
}
