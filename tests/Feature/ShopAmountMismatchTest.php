<?php

namespace Tests\Feature;

use App\Mail\BalanceDueMail;
use App\Mail\OrderReceiptMail;
use App\Mail\PaymentProblemAlertMail;
use App\Models\CustomOrder;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Support\OrderPolicy;
use App\Support\OrderStatus;
use App\Support\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase C item 4: an M-Pesa payment for a shop order that differs from its
 * prompt. More: the order is paid and the difference is owed back. Less: the
 * money is a part payment, the stock is held for 24 hours and the customer
 * gets a pay link for the balance; unpaid by then, what was received is owed
 * back and the stock goes back on sale.
 */
class ShopAmountMismatchTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.mpesa.callback_token' => 'secret', 'services.store.notification_email' => 'owner@serana.test']);
        $this->product = Product::create(['name' => 'Linen Set', 'slug' => 'linen-set', 'price' => 5000, 'batch_limit' => 3, 'batch_sold' => 0, 'in_stock' => true]);
    }

    private function order(): CustomOrder
    {
        $order = CustomOrder::create(['full_name' => 'Jane Wanjiru', 'email' => 'jane@example.com', 'phone' => '254712345678', 'whatsapp_number' => '254712345678',
            'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 5000,
            'items_json' => [['id' => $this->product->id, 'name' => 'Linen Set', 'price' => 5000, 'quantity' => 1]]]);
        $order->stockReservations()->create(['product_id' => $this->product->id, 'quantity' => 1, 'status' => 'held', 'expires_at' => now()->addMinutes(10)]);

        return $order;
    }

    private function prompt(CustomOrder $order, string $id, int $amount = 5000): PaymentAttempt
    {
        return $order->paymentAttempts()->create(['checkout_request_id' => $id, 'phone' => '254712345678', 'amount' => $amount, 'status' => PaymentAttempt::PENDING]);
    }

    private function stkCallback(string $id, int $paid, string $receipt, int $code = 0): void
    {
        $body = ['ResultCode' => $code, 'CheckoutRequestID' => $id];
        if ($code === 0) {
            $body['CallbackMetadata'] = ['Item' => [['Name' => 'Amount', 'Value' => $paid], ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt]]];
        }
        $this->postJson(route('mpesa.callback', ['token' => 'secret']), ['Body' => ['stkCallback' => $body]])->assertOk();
    }

    public function test_paying_more_settles_the_order_and_owes_the_difference_back(): void
    {
        $order = $this->order();
        $attempt = $this->prompt($order, 'ws_OVER');

        $this->stkCallback('ws_OVER', 6000, 'QOVER1');

        $fresh = $order->fresh();
        $this->assertSame(['refund_due', 6000, -1000, true, 'Paid more than the total', 'none'],
            [$fresh->payment_status, $fresh->amount_paid, $fresh->balance_due, $fresh->is_paid, $fresh->refund_cause, $fresh->resolution]);
        $this->assertSame(OrderStatus::PAID, $fresh->status);
        $this->assertSame(1, $this->product->fresh()->batch_sold);
        $this->assertSame(PaymentAttempt::PAID, $attempt->fresh()->status);
        Mail::assertSent(OrderReceiptMail::class, function (OrderReceiptMail $mail) {
            $mail->assertSeeInHtml('KSh 1,000 more than the total was received');
            $mail->assertSeeInHtml('KSh 1,000 will be refunded to you within 2 working days');

            return true;
        });
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }

    public function test_paying_less_keeps_the_stock_for_a_day_and_sends_a_balance_pay_link(): void
    {
        $order = $this->order();
        $attempt = $this->prompt($order, 'ws_UNDER');

        $this->stkCallback('ws_UNDER', 4000, 'QUNDER1');

        $fresh = $order->fresh();
        $this->assertSame(['deposit_paid', 4000, 1000, false, 'none', 'open'],
            [$fresh->payment_status, $fresh->amount_paid, $fresh->balance_due, $fresh->is_paid, $fresh->resolution, $fresh->order_state]);
        $hold = $order->stockReservations()->sole();
        $this->assertSame('held', $hold->status);
        $this->assertTrue($hold->expires_at->between(now()->addHours(23), now()->addHours(25)), 'held for 24 hours');
        $this->assertSame(PaymentAttempt::PAID, $attempt->fresh()->status, 'the money arrived');
        $this->assertNotNull($fresh->pay_link);

        Mail::assertQueued(BalanceDueMail::class, function (BalanceDueMail $mail) use ($order) {
            $mail->assertSeeInHtml('KSh 4,000');
            $mail->assertSeeInHtml('KSh 1,000');
            $mail->assertSeeInHtml('24 hours');
            $mail->assertSeeInHtml(e('/pay/' . $order->cipher));

            return $mail->hasTo('jane@example.com');
        });
        Mail::assertQueued(PaymentProblemAlertMail::class, fn ($mail) => $mail->hasTo('owner@serana.test'));

        // A cancelled balance prompt keeps the hold and the money.
        $this->prompt($order, 'ws_BAL_CANCEL', 1000);
        $this->stkCallback('ws_BAL_CANCEL', 0, '', 1032);
        $this->assertSame(['held', 'deposit_paid'], [$hold->fresh()->status, $order->fresh()->payment_status]);

        // She pays the balance: the order is paid and the stock sold.
        $this->prompt($order, 'ws_BAL', 1000);
        $this->stkCallback('ws_BAL', 1000, 'QBAL1');
        $this->assertSame(['paid', true, 1], [$order->fresh()->payment_status, $order->fresh()->is_paid, $this->product->fresh()->batch_sold]);
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }

    public function test_a_balance_unpaid_after_a_day_is_owed_back_and_the_stock_released(): void
    {
        $order = $this->order();
        $this->prompt($order, 'ws_UNDER');
        $this->stkCallback('ws_UNDER', 4000, 'QUNDER1');

        $this->travel(23)->hours();
        $this->artisan('stock:release-expired')->assertSuccessful();
        $this->assertSame('deposit_paid', $order->fresh()->payment_status, 'not before the day is up');

        $this->travel(2)->hours();
        $this->artisan('stock:release-expired')->assertSuccessful();

        $fresh = $order->fresh();
        $this->assertSame(['expired', 'refund_due', -4000, 'Balance not paid in time'], [$fresh->order_state, $fresh->payment_status, $fresh->balance_due, $fresh->refund_cause]);
        $this->assertSame(OrderStatus::EXPIRED, $fresh->status);
        $this->assertSame('released', $order->stockReservations()->value('status'));
        $this->assertSame(0, $this->product->fresh()->batch_sold);
        $this->assertSame(3, $this->product->fresh()->available_stock);
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }

    public function test_paying_less_after_the_item_sold_out_waits_for_the_studio(): void
    {
        $this->product->update(['batch_limit' => 1, 'batch_sold' => 1]);
        $order = $this->order();
        $order->stockReservations()->update(['status' => 'released']);
        $this->prompt($order, 'ws_UNDER');

        $this->stkCallback('ws_UNDER', 4000, 'QUNDER1');

        $fresh = $order->fresh();
        $this->assertSame(['needs_resolution', 'stock_gone', 4000], [$fresh->payment_status, $fresh->resolution_code, $fresh->amount_paid]);
        $this->assertSame(1, $this->product->fresh()->batch_sold);
        Mail::assertNotQueued(BalanceDueMail::class);
    }

    public function test_the_short_payment_hold_follows_the_studio_setting(): void
    {
        OrderPolicy::save(['deposit_percent' => 50, 'pay_link_days' => 14, 'deposit_policy' => '', 'balance_hold_hours' => 12]);
        $order = $this->order();
        $this->prompt($order, 'ws_UNDER');

        $this->stkCallback('ws_UNDER', 4000, 'QUNDER1');

        $hold = $order->stockReservations()->sole();
        $this->assertTrue($hold->expires_at->between(now()->addHours(11), now()->addHours(13)), 'held for 12 hours');
        Mail::assertQueued(BalanceDueMail::class, function (BalanceDueMail $mail) {
            $mail->assertSeeInHtml('12 hours');

            return true;
        });

        $this->travel(11)->hours();
        $this->artisan('stock:release-expired')->assertSuccessful();
        $this->assertSame('deposit_paid', $order->fresh()->payment_status);
        $this->travel(2)->hours();
        $this->artisan('stock:release-expired')->assertSuccessful();
        $this->assertSame(['expired', 'refund_due'], [$order->fresh()->order_state, $order->fresh()->payment_status]);
    }

    public function test_the_owner_sets_the_short_payment_hold_and_it_is_validated(): void
    {
        $owner = User::factory()->create(['role' => Role::OWNER]);
        $policy = ['deposit_percent' => 50, 'pay_link_days' => 14, 'deposit_policy' => ''];
        $this->assertSame(24, OrderPolicy::balanceHoldHours(), 'default 24 hours');
        $this->assertSame(24, OrderPolicy::forStudio()['balance_hold_hours']);

        $this->actingAs($owner)->put(route('admin.order_policy.update'), $policy + ['balance_hold_hours' => 0])->assertSessionHasErrors('balance_hold_hours');
        $this->actingAs($owner)->put(route('admin.order_policy.update'), $policy + ['balance_hold_hours' => 169])->assertSessionHasErrors('balance_hold_hours');
        $this->actingAs($owner)->put(route('admin.order_policy.update'), $policy + ['balance_hold_hours' => 48])->assertSessionHasNoErrors();
        $this->assertSame(48, OrderPolicy::balanceHoldHours());
        $this->actingAs(User::factory()->create(['role' => Role::MANAGER]))->put(route('admin.order_policy.update'), $policy + ['balance_hold_hours' => 6])->assertForbidden();
    }
}
