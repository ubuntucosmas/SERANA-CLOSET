<?php

namespace Tests\Feature;

use App\Events\MpesaWebhookHandled;
use App\Models\AuditLog;
use App\Models\CustomOrder;
use App\Models\OrderLine;
use App\Models\OrderPayment;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Support\OrderStatus;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Batch 2 Phase A3b (design §1.1 principle 1, I4, I13, N1): OrderSettlementService
 * is the single writer of ledger rows, books every shilling exactly once, and
 * keeps the legacy columns in step. (The after-each-test verifier in
 * Tests\TestCase also checks every order these tests touch.)
 */
class SettlementLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mpesa.callback_token' => 'secret', 'services.mpesa.query_spacing_seconds' => 0]);
        Event::fake([MpesaWebhookHandled::class]);
    }

    private function shopOrder(array $o = []): CustomOrder
    {
        $product = Product::create(['name' => 'Set', 'slug' => 'set-' . uniqid(), 'price' => 5000, 'batch_limit' => 5]);
        $order = CustomOrder::create(array_merge([
            'full_name' => 'Jane', 'phone' => '254712345678', 'whatsapp_number' => '254712345678', 'type' => 'standard',
            'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 5000,
            'items_json' => [['id' => $product->id, 'name' => 'Set', 'price' => 5000, 'quantity' => 1]],
        ], $o));
        $order->stockReservations()->create(['product_id' => $product->id, 'quantity' => 1, 'status' => 'held', 'expires_at' => now()->addMinutes(10)]);

        return $order;
    }

    private function attempt(CustomOrder $order, string $id = 'ws_CO_1', int $amount = 5000): PaymentAttempt
    {
        return $order->paymentAttempts()->create(['checkout_request_id' => $id, 'phone' => '254712345678', 'amount' => $amount, 'status' => PaymentAttempt::PENDING]);
    }

    private function stkCallback(string $id, int $amount, string $receipt, int $code = 0)
    {
        return $this->postJson(route('mpesa.callback', ['token' => 'secret']), ['Body' => ['stkCallback' => [
            'ResultCode' => $code, 'CheckoutRequestID' => $id, 'CallbackMetadata' => ['Item' => [
                ['Name' => 'Amount', 'Value' => $amount], ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
            ]],
        ]]])->assertOk();
    }

    private function bespoke(int $quote = 10000): CustomOrder
    {
        return CustomOrder::create(['full_name' => 'Client', 'whatsapp_number' => '254712345678', 'type' => 'Gown', 'status' => 'designing', 'price_quoted' => $quote]);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => Role::OWNER, 'name' => 'Owner']);
    }

    public function test_a_paid_callback_books_one_stk_payment_from_safaricom(): void
    {
        $order = $this->shopOrder();
        $attempt = $this->attempt($order);

        $this->stkCallback('ws_CO_1', 5000, 'RCPT1');

        $payment = $order->payments()->sole();
        $this->assertSame([OrderPayment::PAYMENT, 5000, 'mpesa_stk', 'RCPT1', $attempt->id, 'safaricom'],
            [$payment->type, $payment->amount, $payment->method, $payment->external_reference, $payment->payment_attempt_id, $payment->actor_type]);
        $fresh = $order->fresh();
        $this->assertSame(['paid', 5000, 0, true], [$fresh->payment_status, $fresh->amount_paid, $fresh->balance_due, $fresh->is_paid]);
        $this->assertSame('committed', $order->stockReservations()->value('status'));
        Event::assertDispatchedTimes(MpesaWebhookHandled::class, 1);
    }

    public function test_a_replayed_callback_books_nothing_more(): void
    {
        $order = $this->shopOrder();
        $this->attempt($order);

        $this->stkCallback('ws_CO_1', 5000, 'RCPT1');
        $this->stkCallback('ws_CO_1', 5000, 'RCPT1');

        $this->assertSame(1, $order->payments()->count());
        Event::assertDispatchedTimes(MpesaWebhookHandled::class, 1);
    }

    public function test_reconciliation_after_the_callback_books_nothing_more(): void
    {
        $order = $this->shopOrder();
        $attempt = $this->attempt($order);
        $this->stkCallback('ws_CO_1', 5000, 'RCPT1');

        // As if reconciliation picked the same attempt up before seeing the callback's effect.
        app(\App\Services\OrderSettlementService::class)->recordPayment($order, 5000, 'mpesa_stk', \App\Support\Actor::system(), null, $attempt);

        $this->assertSame(1, $order->payments()->count());
    }

    public function test_a_payment_found_by_reconciliation_is_booked_by_the_system(): void
    {
        $order = $this->shopOrder();
        $attempt = $this->attempt($order);
        $attempt->forceFill(['created_at' => now()->subMinutes(5)])->save();
        Http::fake([
            '*oauth/v1/generate*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            '*stkpushquery*' => Http::response(['ResultCode' => '0', 'ResultDesc' => 'ok']),
        ]);

        $this->artisan('mpesa:reconcile')->assertSuccessful();

        $payment = $order->payments()->sole();
        $this->assertSame(['system', null, $attempt->id, 5000], [$payment->actor_type, $payment->external_reference, $payment->payment_attempt_id, $payment->amount]);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_paying_an_already_paid_order_books_the_money_and_owes_a_refund(): void
    {
        $order = $this->shopOrder();
        $this->attempt($order, 'ws_CO_1');
        $this->attempt($order, 'ws_CO_2');

        $this->stkCallback('ws_CO_1', 5000, 'RCPT1');
        $this->stkCallback('ws_CO_2', 5000, 'RCPT2');

        $fresh = $order->fresh();
        $this->assertSame(['refund_due', 10000, -5000, true], [$fresh->payment_status, $fresh->amount_paid, $fresh->balance_due, $fresh->is_paid]);
        $this->assertSame(['RCPT1', 'RCPT2'], $order->payments()->orderBy('id')->pluck('external_reference')->all());
        Event::assertDispatchedTimes(MpesaWebhookHandled::class, 1); // one receipt, for the payment that settled it
    }

    public function test_a_wrong_amount_is_booked_as_real_money_and_flagged(): void
    {
        $order = $this->shopOrder();
        $this->attempt($order);

        $this->stkCallback('ws_CO_1', 4000, 'RCPT-SHORT');

        $fresh = $order->fresh();
        $this->assertSame([OrderStatus::AMOUNT_MISMATCH, 'amount_mismatch', 'amount_mismatch', 4000, false],
            [$fresh->status, $fresh->resolution, $fresh->payment_status, $fresh->amount_paid, $fresh->is_paid]);
        $this->assertSame(['RCPT-SHORT', 4000], [$order->payments()->value('external_reference'), $order->payments()->value('amount')]);
        $this->assertSame('released', $order->stockReservations()->value('status'), 'unchanged in Phase A; Phase C keeps holds');
        Event::assertNotDispatched(MpesaWebhookHandled::class);
    }

    public function test_recording_a_bespoke_payment_books_it_and_never_overwrites_the_quote(): void
    {
        $order = $this->bespoke(10000);

        $this->actingAs($this->owner())->post(route('studio.order.record_payment', $order), ['amount' => 4000, 'method' => 'mpesa_manual', 'reference' => 'QDEP1'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $fresh = $order->fresh();
        $this->assertSame([10000, 'deposit_paid', 4000, 6000, false], [(int) $fresh->price_quoted, $fresh->payment_status, $fresh->amount_paid, $fresh->balance_due, $fresh->is_paid]);
        $payment = $order->payments()->sole();
        $this->assertSame(['mpesa_manual', 'QDEP1', 'user'], [$payment->method, $payment->external_reference, $payment->actor_type]);

        $this->post(route('studio.order.record_payment', $order), ['amount' => 6000, 'method' => 'cash', 'reference' => 'Cash at the studio']);
        $fresh = $order->fresh();
        $this->assertSame(['paid', 10000, 0, true, 'designing'], [$fresh->payment_status, $fresh->amount_paid, $fresh->balance_due, $fresh->is_paid, $fresh->status]);
    }

    public function test_recording_a_payment_needs_a_method_a_quote_and_no_more_than_the_balance(): void
    {
        $owner = $this->owner();
        $unquoted = $this->bespoke(0);
        $this->actingAs($owner)->post(route('studio.order.record_payment', $unquoted), ['amount' => 1000, 'method' => 'cash', 'reference' => 'x'])
            ->assertSessionHasErrors('amount');

        $order = $this->bespoke(5000);
        $this->post(route('studio.order.record_payment', $order), ['amount' => 1000, 'reference' => 'x'])->assertSessionHasErrors('method');
        $this->post(route('studio.order.record_payment', $order), ['amount' => 1000, 'method' => 'barter', 'reference' => 'x'])->assertSessionHasErrors('method');
        $this->post(route('studio.order.record_payment', $order), ['amount' => 6000, 'method' => 'cash', 'reference' => 'x'])->assertSessionHasErrors('amount');

        $this->assertSame(0, OrderPayment::count());
    }

    public function test_a_reference_already_booked_is_refused_with_where_it_was_used(): void
    {
        $owner = $this->owner();
        $first = $this->bespoke(5000);
        $second = $this->bespoke(5000);
        $this->actingAs($owner)->post(route('studio.order.record_payment', $first), ['amount' => 5000, 'method' => 'mpesa_manual', 'reference' => 'QSAME']);

        $this->post(route('studio.order.record_payment', $second), ['amount' => 5000, 'method' => 'mpesa_manual', 'reference' => 'QSAME'])
            ->assertSessionHasErrors(['reference' => "QSAME is already recorded on order {$first->cipher}."]);
        $this->assertSame(0, $second->payments()->count());
    }

    public function test_confirming_a_whatsapp_payment_books_the_method_and_who_confirmed_it(): void
    {
        $order = $this->shopOrder(['payment_method' => 'whatsapp']);
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('studio.order.record_payment', $order), ['amount' => 5000, 'method' => 'mpesa_manual', 'reference' => 'TILL123'])
            ->assertSessionHas('success');

        $payment = $order->payments()->sole();
        $this->assertSame(['mpesa_manual', 'TILL123', 'user', $owner->id], [$payment->method, $payment->external_reference, $payment->actor_type, $payment->actor_id]);
        $this->assertSame('paid', $order->fresh()->payment_status);
        Event::assertDispatchedTimes(MpesaWebhookHandled::class, 1);
    }

    public function test_changing_a_bespoke_quote_appends_lines_instead_of_editing(): void
    {
        $order = CustomOrder::create(['full_name' => 'Client', 'whatsapp_number' => '254712345678', 'type' => 'Gown', 'status' => 'pending']);
        $owner = $this->owner();

        $this->actingAs($owner)->put(route('studio.update', $order), ['status' => 'pending', 'price_quoted' => 8000])->assertSessionHasNoErrors();
        $this->put(route('studio.update', $order), ['status' => 'pending', 'price_quoted' => 9500])->assertSessionHasNoErrors();

        $this->assertSame([[OrderLine::ITEM, 8000], [OrderLine::ADJUSTMENT, 1500]], $order->lines()->orderBy('id')->get()->map(fn ($l) => [$l->line_type, $l->amount])->all());
        $this->assertSame([9500, 9500], [$order->fresh()->chargeable_total, (int) $order->fresh()->price_quoted]);
        $this->assertSame('user', OrderLine::where('custom_order_id', $order->id)->latest('id')->value('actor_type'));
        $this->assertSame(2, AuditLog::where('action', 'order.updated')->count());
    }

    public function test_nothing_outside_settlement_writes_the_ledger(): void
    {
        $writers = collect(\Illuminate\Support\Facades\File::allFiles(app_path()))
            ->filter(fn ($file) => preg_match('/OrderPayment::(create|insert|forceCreate)|payments\(\)->create/', $file->getContents()))
            ->map(fn ($file) => str_replace(app_path() . '/', '', $file->getPathname()))
            ->values()->all();

        $this->assertSame(['Services/LedgerBackfill.php', 'Services/OrderSettlementService.php'], collect($writers)->sort()->values()->all());
    }
}
