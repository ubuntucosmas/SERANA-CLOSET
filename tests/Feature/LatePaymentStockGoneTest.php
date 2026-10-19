<?php

namespace Tests\Feature;

use App\Mail\PaymentProblemAlertMail;
use App\Mail\StockGoneMail;
use App\Models\AuditLog;
use App\Models\CustomOrder;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase C item 3: money that arrives after an order's stock hold lapsed, when
 * someone else has since bought the last unit. The payment is recorded, the
 * batch is never sold past its limit, the order waits for a decision
 * (needs_resolution) and the customer is told. The Studio resolves it with a
 * full refund, a swap, or waiting for the next batch; a job turns anything
 * still undecided after the policy's days into a refund owed.
 */
class LatePaymentStockGoneTest extends TestCase
{
    use RefreshDatabase;

    private Product $jacket;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.mpesa.callback_token' => 'secret', 'services.store.notification_email' => 'owner@serana.test']);
        $this->jacket = $this->product('Limited Jacket', 5000, 1);
    }

    private function product(string $name, int $price, ?int $limit, int $sold = 0): Product
    {
        return Product::create(['name' => $name, 'slug' => str($name)->slug() . '-' . uniqid(), 'price' => $price,
            'batch_limit' => $limit, 'batch_sold' => $sold, 'in_stock' => true]);
    }

    /** Jane's hold on the only jacket lapsed; then someone else bought it. */
    private function lapsedOrder(): CustomOrder
    {
        $order = CustomOrder::create(['full_name' => 'Jane Wanjiru', 'email' => 'jane@example.com', 'whatsapp_number' => '254712345678', 'phone' => '254712345678',
            'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000,
            'items_json' => [['id' => $this->jacket->id, 'name' => 'Limited Jacket', 'price' => 5000, 'quantity' => 1, 'size_label' => 'Size M']]]);
        $order->stockReservations()->create(['product_id' => $this->jacket->id, 'quantity' => 1, 'status' => 'released', 'expires_at' => now()->subMinutes(10)]);
        $this->jacket->update(['batch_sold' => 1]);
        $order->paymentAttempts()->create(['checkout_request_id' => 'ws_LATE', 'phone' => '254712345678', 'amount' => 5000, 'status' => 'pending']);

        return $order->fresh();
    }

    private function paysLate(CustomOrder $order): void
    {
        $this->postJson(route('mpesa.callback', ['token' => 'secret']), ['Body' => ['stkCallback' => [
            'ResultCode' => 0, 'CheckoutRequestID' => 'ws_LATE', 'CallbackMetadata' => ['Item' => [
                ['Name' => 'Amount', 'Value' => 5000], ['Name' => 'MpesaReceiptNumber', 'Value' => 'QLATE1'],
            ]],
        ]]])->assertOk();
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => Role::OWNER, 'name' => 'Owner']);
    }

    public function test_a_late_payment_for_a_sold_out_item_is_recorded_without_overselling(): void
    {
        $order = $this->lapsedOrder();
        $this->paysLate($order);

        $fresh = $order->fresh();
        $this->assertSame([5000, 'needs_resolution', 'stock_gone'], [$fresh->amount_paid, $fresh->payment_status, $fresh->resolution_code]);
        $this->assertStringContainsString('Limited Jacket sold out', $fresh->resolution_reason);
        $this->assertNotNull($fresh->resolution_since);
        $this->assertFalse($fresh->is_paid);
        $this->assertSame(1, $this->jacket->fresh()->batch_sold, 'never past the batch limit');
        $this->assertSame('released', $order->stockReservations()->value('status'));

        Mail::assertQueued(StockGoneMail::class, function (StockGoneMail $mail) {
            $mail->assertSeeInHtml('Limited Jacket');
            $mail->assertSeeInHtml('KSh 5,000');
            $mail->assertSeeInHtml('within 3 days');

            return $mail->hasTo('jane@example.com');
        });
        Mail::assertQueued(PaymentProblemAlertMail::class, fn ($mail) => $mail->hasTo('owner@serana.test'));
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }

    public function test_a_late_payment_still_takes_the_item_when_it_is_free(): void
    {
        $order = $this->lapsedOrder();
        $this->jacket->update(['batch_sold' => 0]); // nobody bought it in the meantime

        $this->paysLate($order);

        $this->assertSame(['paid', true], [$order->fresh()->payment_status, $order->fresh()->is_paid]);
        $this->assertSame(1, $this->jacket->fresh()->batch_sold);
        Mail::assertNotQueued(StockGoneMail::class);
    }

    public function test_full_refund_cancels_the_order_and_owes_back_everything(): void
    {
        $order = $this->lapsedOrder();
        $this->paysLate($order);

        $this->actingAs($this->owner())->post(route('studio.order.resolve', $order), ['action' => 'refund', 'reason' => 'Customer prefers a refund'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $fresh = $order->fresh();
        $this->assertSame(['cancelled', 'none', null, 0, -5000, 'refund_due', 'Sold out after a late payment'],
            [$fresh->order_state, $fresh->resolution, $fresh->resolution_code, $fresh->chargeable_total, $fresh->balance_due, $fresh->payment_status, $fresh->refund_cause]);
        $this->assertSame(1, $this->jacket->fresh()->batch_sold);
        $this->assertSame('Customer prefers a refund', AuditLog::where('action', 'order.resolved')->sole()->reason);
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }

    public function test_swap_to_a_dearer_item_leaves_a_balance_and_holds_the_new_item(): void
    {
        $order = $this->lapsedOrder();
        $this->paysLate($order);
        $coat = $this->product('Wool Coat', 6000, 2);

        $this->actingAs($this->owner())->post(route('studio.order.resolve', $order), [
            'action' => 'swap', 'from_product_id' => $this->jacket->id, 'to_product_id' => $coat->id, 'size' => 'Size L', 'reason' => 'Customer chose the coat',
        ])->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame(['none', 6000, 1000, 'deposit_paid', 'open'], [$fresh->resolution, $fresh->chargeable_total, $fresh->balance_due, $fresh->payment_status, $fresh->order_state]);
        $this->assertSame('held', $order->stockReservations()->where('product_id', $coat->id)->value('status'));
        $this->assertSame(1, $coat->fresh()->available_stock, 'the coat is held for her');
        $this->assertSame('Wool Coat', collect($fresh->items_json)->first()['name']);
        $this->assertNotNull($fresh->pay_link, 'the customer pays the difference by pay link');

        // She pays the difference: the order is paid and the coat is sold.
        app(OrderSettlementService::class)->recordPayment($fresh, 1000, 'mpesa_stk', Actor::safaricom(), 'QBAL1');
        $this->assertSame(['paid', 1], [$order->fresh()->payment_status, $coat->fresh()->batch_sold]);
        $this->assertSame(1, $this->jacket->fresh()->batch_sold);
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }

    public function test_swap_to_a_cheaper_item_is_paid_and_owes_the_difference_back(): void
    {
        $order = $this->lapsedOrder();
        $this->paysLate($order);
        $cap = $this->product('Cap', 3500, null);

        $this->actingAs($this->owner())->post(route('studio.order.resolve', $order), [
            'action' => 'swap', 'from_product_id' => $this->jacket->id, 'to_product_id' => $cap->id, 'reason' => 'Swapped for the cap',
        ])->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame([3500, -1500, 'refund_due', 'Swapped to a cheaper item', true], [$fresh->chargeable_total, $fresh->balance_due, $fresh->payment_status, $fresh->refund_cause, $fresh->is_paid]);
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }

    public function test_a_swap_needs_stock(): void
    {
        $order = $this->lapsedOrder();
        $this->paysLate($order);
        $gone = $this->product('Gone Too', 5000, 1, 1);

        $this->actingAs($this->owner())->post(route('studio.order.resolve', $order), [
            'action' => 'swap', 'from_product_id' => $this->jacket->id, 'to_product_id' => $gone->id, 'reason' => 'x',
        ])->assertSessionHasErrors(['to_product_id' => 'Gone Too has no stock for this order.']);
        $this->assertSame('needs_resolution', $order->fresh()->resolution);
    }

    public function test_waiting_for_the_next_batch_keeps_the_order_open_and_is_left_alone_by_the_job(): void
    {
        $order = $this->lapsedOrder();
        $this->paysLate($order);

        $this->actingAs($this->owner())->post(route('studio.order.resolve', $order), ['action' => 'wait', 'reason' => 'Next batch in two weeks; customer will wait'])
            ->assertSessionHasNoErrors();
        $fresh = $order->fresh();
        $this->assertSame(['waiting_restock', 'open', 5000], [$fresh->payment_status, $fresh->order_state, $fresh->amount_paid]);
        $this->assertSame('Next batch in two weeks; customer will wait', $fresh->resolution_reason);

        $this->travel(10)->days();
        $this->artisan('serana:resolve-overdue')->assertSuccessful();
        $this->assertSame('waiting_restock', $order->fresh()->resolution);

        // The batch is restocked: the Studio swaps her onto the same jacket.
        $this->jacket->update(['batch_limit' => 5]);
        $this->actingAs($this->owner())->post(route('studio.order.resolve', $order), [
            'action' => 'swap', 'from_product_id' => $this->jacket->id, 'to_product_id' => $this->jacket->id, 'size' => 'Size M', 'reason' => 'Restocked',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['paid', 2], [$order->fresh()->payment_status, $this->jacket->fresh()->batch_sold]);
    }

    public function test_an_undecided_late_payment_becomes_a_refund_after_the_policy_days(): void
    {
        $order = $this->lapsedOrder();
        $this->paysLate($order);
        $conflict = CustomOrder::create(['full_name' => 'Amina', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 100]);
        app(OrderSettlementService::class)->flagReferenceConflict($conflict, 'QOTHER', $order);

        $this->travel(2)->days();
        $this->artisan('serana:resolve-overdue')->assertSuccessful();
        $this->assertSame('needs_resolution', $order->fresh()->resolution, 'not yet: 3 days by default');

        $this->travel(1)->days();
        $this->travel(1)->minutes();
        $this->artisan('serana:resolve-overdue')->assertSuccessful();
        $fresh = $order->fresh();
        $this->assertSame(['cancelled', 'refund_due', -5000], [$fresh->order_state, $fresh->payment_status, $fresh->balance_due]);
        $this->assertSame('No decision within 3 days', AuditLog::where('action', 'order.resolved')->sole()->reason);
        $this->assertSame('needs_resolution', $conflict->fresh()->resolution, 'only stock problems are settled by the job');
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }
}
