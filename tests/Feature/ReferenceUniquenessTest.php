<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\OrderPayment;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\Role;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase C Step 0 (owner decision): one M-Pesa code is booked once, whichever
 * way it reached the ledger (STK callback or typed in the Studio), enforced
 * by the database and explained in the Studio. A genuine duplicate callback
 * for the same order stays idempotent.
 */
class ReferenceUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.mpesa.callback_token' => 'secret', 'services.mpesa.query_spacing_seconds' => 0, 'services.store.notification_email' => 'owner@serana.test']);
        $this->owner = User::factory()->create(['role' => Role::OWNER, 'name' => 'Owner']);
    }

    private function shop(array $o = []): CustomOrder
    {
        return CustomOrder::create(array_merge(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'phone' => '254712345678', 'type' => 'standard',
            'status' => 'Pending Payment', 'price_quoted' => 3000, 'items_json' => [['name' => 'Cap', 'price' => 3000, 'quantity' => 1]]], $o));
    }

    private function bespoke(): CustomOrder
    {
        return CustomOrder::create(['full_name' => 'Amina', 'whatsapp_number' => '254712345678', 'type' => 'Gown', 'status' => 'pending', 'price_quoted' => 3000]);
    }

    /** An order already paid by STK with M-Pesa code QSTK123. */
    private function paidByStk(): CustomOrder
    {
        $order = $this->shop();
        $attempt = $order->paymentAttempts()->create(['checkout_request_id' => 'ws_FIRST', 'phone' => '254712345678', 'amount' => 3000, 'status' => 'pending']);
        app(OrderSettlementService::class)->recordPayment($order, 3000, 'mpesa_stk', Actor::safaricom(), 'QSTK123', $attempt);

        return $order->fresh();
    }

    private function stkCallback(string $checkoutId, string $receipt, int $amount = 3000)
    {
        return $this->postJson(route('mpesa.callback', ['token' => 'secret']), ['Body' => ['stkCallback' => [
            'ResultCode' => 0, 'CheckoutRequestID' => $checkoutId, 'CallbackMetadata' => ['Item' => [
                ['Name' => 'Amount', 'Value' => $amount], ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
            ]],
        ]]])->assertOk();
    }

    public function test_the_database_refuses_one_mpesa_code_twice_across_stk_and_manual(): void
    {
        $this->paidByStk();
        $other = $this->bespoke();

        $this->assertSame('mpesa', OrderPayment::where('external_reference', 'QSTK123')->value('reference_scope'), 'computed by the database');
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('order_payments')->insert(['custom_order_id' => $other->id, 'type' => 'payment', 'amount' => 3000, 'method' => 'mpesa_manual',
            'external_reference' => 'QSTK123', 'actor_type' => 'system', 'occurred_at' => now(), 'created_at' => now()]);
    }

    public function test_studio_record_payment_refuses_an_mpesa_code_booked_on_another_order(): void
    {
        $paid = $this->paidByStk();
        $order = $this->bespoke();

        $this->actingAs($this->owner)->post(route('studio.order.record_payment', $order), ['amount' => 3000, 'method' => 'mpesa_manual', 'reference' => 'QSTK123'])
            ->assertSessionHasErrors(['reference' => "This M-Pesa code is already recorded on order {$paid->cipher}."]);
        $this->assertSame(0, $order->payments()->count());
    }

    public function test_whatsapp_confirmation_refuses_an_mpesa_code_booked_on_another_order(): void
    {
        $paid = $this->paidByStk();
        $order = $this->shop(['payment_method' => 'whatsapp']);

        $this->actingAs($this->owner)->post(route('studio.order.record_payment', $order), ['amount' => 3000, 'method' => 'mpesa_manual', 'reference' => 'QSTK123'])
            ->assertSessionHasErrors(['reference' => "This M-Pesa code is already recorded on order {$paid->cipher}."]);
        $this->assertFalse($order->fresh()->is_paid);
    }

    public function test_other_methods_keep_their_own_namespace(): void
    {
        $this->paidByStk();
        $order = $this->bespoke();

        // A cash note that happens to read like an M-Pesa code is a different reference.
        $this->actingAs($this->owner)->post(route('studio.order.record_payment', $order), ['amount' => 3000, 'method' => 'cash', 'reference' => 'QSTK123'])
            ->assertSessionHasNoErrors();
        $second = $this->bespoke();
        $this->post(route('studio.order.record_payment', $second), ['amount' => 3000, 'method' => 'cash', 'reference' => 'QSTK123'])
            ->assertSessionHasErrors(['reference' => "This reference is already recorded on order {$order->cipher}."]);
    }

    public function test_a_callback_carrying_a_code_booked_on_another_order_is_not_booked_and_is_flagged(): void
    {
        // The code was typed into the Studio on another order first.
        $first = $this->bespoke();
        $this->actingAs($this->owner)->post(route('studio.order.record_payment', $first), ['amount' => 3000, 'method' => 'mpesa_manual', 'reference' => 'QCLASH1']);
        $order = $this->shop();
        $attempt = $order->paymentAttempts()->create(['checkout_request_id' => 'ws_CLASH', 'phone' => '254712345678', 'amount' => 3000, 'status' => 'pending']);

        $this->stkCallback('ws_CLASH', 'QCLASH1');

        $this->assertSame(0, $order->payments()->count(), 'one M-Pesa transaction is never counted twice');
        $fresh = $order->fresh();
        $this->assertSame(['needs_resolution', "M-Pesa code QCLASH1 is already recorded on order {$first->cipher}."], [$fresh->resolution, $fresh->resolution_reason]);
        $this->assertSame(PaymentAttempt::FAILED, $attempt->fresh()->status, 'reconciliation must not book it either');
        Mail::assertQueued(\App\Mail\PaymentProblemAlertMail::class, fn ($m) => $m->hasTo('owner@serana.test') && str_contains($m->body, 'QCLASH1'));
    }

    public function test_a_genuine_duplicate_callback_for_the_same_order_books_once(): void
    {
        $order = $this->shop();
        $order->paymentAttempts()->create(['checkout_request_id' => 'ws_SAME', 'phone' => '254712345678', 'amount' => 3000, 'status' => 'pending']);

        $this->stkCallback('ws_SAME', 'QSAME1');
        $this->stkCallback('ws_SAME', 'QSAME1');

        $this->assertSame(1, $order->payments()->count());
        $this->assertSame('none', $order->fresh()->resolution);
    }

    public function test_reconciliation_after_the_callback_books_once(): void
    {
        $order = $this->shop();
        $attempt = $order->paymentAttempts()->create(['checkout_request_id' => 'ws_REC', 'phone' => '254712345678', 'amount' => 3000, 'status' => 'pending']);
        $this->stkCallback('ws_REC', 'QREC1');
        // A stale reconcile run that still saw the attempt pending.
        app(OrderSettlementService::class)->recordPayment($order, 3000, 'mpesa_stk', Actor::system(), null, $attempt);

        $this->assertSame(1, $order->payments()->count());
    }

    public function test_ledger_check_reports_a_code_booked_on_two_orders(): void
    {
        $this->ledgerChecks = []; // builds a legacy double booking on purpose
        $a = $this->paidByStk();
        $b = $this->shop();
        // As the Phase A backfill booked a reused legacy code: suffixed past the unique index.
        DB::table('order_payments')->insert(['custom_order_id' => $b->id, 'type' => 'payment', 'amount' => 3000, 'method' => 'mpesa_stk',
            'external_reference' => "QSTK123#dup-order-{$b->id}", 'actor_type' => 'safaricom', 'occurred_at' => now(), 'created_at' => now()]);
        app(OrderSettlementService::class)->recompute($b->fresh());

        $this->artisan('serana:ledger-check')
            ->expectsOutputToContain("{$b->cipher}  M-Pesa code QSTK123 is also booked on order {$a->cipher}: reverse it or confirm it genuine")
            ->assertFailed();
    }
}
