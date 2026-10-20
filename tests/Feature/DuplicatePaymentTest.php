<?php

namespace Tests\Feature;

use App\Mail\OrderReceiptMail;
use App\Models\CustomOrder;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\OrderPolicy;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase C item 2: a second payment on an already-paid order is recorded, the
 * excess is owed back (refund_due, in the Refunds queue), and the customer is
 * told it comes back within the policy's working days: by email (the receipt
 * for that payment) and by a prefilled WhatsApp message in the Studio.
 */
class DuplicatePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.mpesa.callback_token' => 'secret', 'services.store.notification_email' => 'owner@serana.test']);
    }

    private function paidShopOrder(): CustomOrder
    {
        $order = CustomOrder::create(['full_name' => 'Jane Wanjiru', 'email' => 'jane@example.com', 'whatsapp_number' => '254712345678', 'phone' => '254712345678',
            'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 3000, 'items_json' => [['name' => 'Cap', 'price' => 3000, 'quantity' => 1]]]);
        $first = $order->paymentAttempts()->create(['checkout_request_id' => 'ws_FIRST', 'phone' => '254712345678', 'amount' => 3000, 'status' => 'pending']);
        app(OrderSettlementService::class)->recordPayment($order, 3000, 'mpesa_stk', Actor::safaricom(), 'QFIRST', $first);
        $order->paymentAttempts()->create(['checkout_request_id' => 'ws_SECOND', 'phone' => '254712345678', 'amount' => 3000, 'status' => 'pending']);

        return $order->fresh();
    }

    private function stkCallback(string $checkoutId, string $receipt, int $amount = 3000): void
    {
        $this->postJson(route('mpesa.callback', ['token' => 'secret']), ['Body' => ['stkCallback' => [
            'ResultCode' => 0, 'CheckoutRequestID' => $checkoutId, 'CallbackMetadata' => ['Item' => [
                ['Name' => 'Amount', 'Value' => $amount], ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
            ]],
        ]]])->assertOk();
    }

    public function test_a_second_payment_is_recorded_and_owed_back_with_the_customer_told_by_email(): void
    {
        $order = $this->paidShopOrder();
        Mail::fake();

        $this->stkCallback('ws_SECOND', 'QSECOND');

        $fresh = $order->fresh();
        $this->assertSame(['refund_due', 6000, -3000, 'Paid twice'], [$fresh->payment_status, $fresh->amount_paid, $fresh->balance_due, $fresh->refund_cause]);
        Mail::assertSent(OrderReceiptMail::class, function (OrderReceiptMail $mail) {
            $mail->assertSeeInHtml('We received this payment twice');
            $mail->assertSeeInHtml('KSh 3,000 will be refunded to you within 2 working days');

            return $mail->hasTo('jane@example.com');
        });

        // The very same callback again changes nothing.
        $this->stkCallback('ws_SECOND', 'QSECOND');
        $this->assertSame(6000, $order->fresh()->amount_paid);
    }

    public function test_the_refund_promise_follows_the_studio_setting(): void
    {
        OrderPolicy::save(['deposit_percent' => 50, 'pay_link_days' => 14, 'deposit_policy' => '', 'refund_working_days' => 3]);
        $order = $this->paidShopOrder();
        Mail::fake();

        $this->stkCallback('ws_SECOND', 'QSECOND');

        Mail::assertSent(OrderReceiptMail::class, function (OrderReceiptMail $mail) { $mail->assertSeeInHtml('within 3 working days'); return true; });
        $this->assertSame(3, OrderPolicy::refundWorkingDays());
    }

    public function test_the_studio_queue_has_a_prefilled_whatsapp_message_for_the_customer(): void
    {
        $order = $this->paidShopOrder();
        $this->stkCallback('ws_SECOND', 'QSECOND');

        $row = collect($this->actingAs(User::factory()->create(['role' => Role::OWNER]))->get(route('studio.index', ['tab' => 'refunds']))
            ->viewData('page')['props']['refunds'])->firstWhere('reference', $order->cipher);

        $this->assertStringStartsWith('https://wa.me/254712345678?text=', $row['whatsapp_url']);
        $message = rawurldecode(explode('?text=', $row['whatsapp_url'])[1]);
        $this->assertStringContainsString('Hi Jane', $message);
        $this->assertStringContainsString($order->cipher, $message);
        $this->assertStringContainsString('came through twice', $message);
        $this->assertStringContainsString('We will refund KSh 3,000 to you within 2 working days', $message);
    }

    public function test_the_owner_sets_the_refund_promise_and_it_is_validated(): void
    {
        $owner = User::factory()->create(['role' => Role::OWNER]);
        $policy = ['deposit_percent' => 50, 'pay_link_days' => 14, 'deposit_policy' => ''];

        $this->actingAs($owner)->put(route('admin.order_policy.update'), $policy + ['refund_working_days' => 0])->assertSessionHasErrors('refund_working_days');
        $this->actingAs($owner)->put(route('admin.order_policy.update'), $policy + ['refund_working_days' => 5, 'resolution_days' => 4])->assertSessionHasNoErrors();
        $this->assertSame([5, 4], [OrderPolicy::refundWorkingDays(), OrderPolicy::resolutionDays()]);

        // Older forms without the new fields keep the defaults.
        $this->actingAs($owner)->put(route('admin.order_policy.update'), $policy)->assertSessionHasNoErrors();
        $this->assertSame([2, 3], [OrderPolicy::refundWorkingDays(), OrderPolicy::resolutionDays()]);
    }
}
