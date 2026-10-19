<?php

namespace Tests\Feature;

use App\Mail\AmountMismatchAlertMail;
use App\Models\CustomOrder;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase C: a short shop payment is now a part payment (ShopAmountMismatchTest);
 * the owner is alerted with PaymentProblemAlertMail. AmountMismatchAlertMail
 * remains for orders still on the old rule (drops).
 *
 * The owner alert for a wrong-amount payment must not be sent inside
 * Safaricom's callback request: a slow SMTP server would delay or fail the
 * callback. It is queued instead; settlement is unchanged.
 */
class AmountMismatchAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mpesa.callback_token' => 'secret', 'services.store.notification_email' => 'owner@serana.test']);
    }

    public function test_a_mismatched_callback_queues_the_owner_alert_instead_of_sending_it(): void
    {
        Mail::fake();

        $product = Product::create(['name' => 'Set', 'slug' => 'set', 'price' => 5000, 'batch_limit' => 3, 'batch_sold' => 0]);
        $order = CustomOrder::create([
            'full_name' => 'Jane Doe', 'phone' => '254712345678', 'whatsapp_number' => '254712345678',
            'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 5000, 'is_paid' => false,
        ]);
        $order->stockReservations()->create(['product_id' => $product->id, 'quantity' => 1, 'status' => 'held', 'expires_at' => now()->addMinutes(10)]);
        $attempt = $order->paymentAttempts()->create(['checkout_request_id' => 'ws_CO_SHORT', 'phone' => '254712345678', 'amount' => 5000, 'status' => PaymentAttempt::PENDING]);

        $this->postJson(route('mpesa.callback', ['token' => 'secret']), ['Body' => ['stkCallback' => [
            'ResultCode' => 0, 'CheckoutRequestID' => 'ws_CO_SHORT', 'CallbackMetadata' => ['Item' => [
                ['Name' => 'Amount', 'Value' => 4000], ['Name' => 'MpesaReceiptNumber', 'Value' => 'RCPT1'],
            ]],
        ]]])->assertOk()->assertExactJson(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

        // Phase C (changed on purpose): a short shop payment is a part payment, not a dead end.
        $order->refresh();
        $this->assertSame(['deposit_paid', 4000, 1000], [$order->payment_status, $order->amount_paid, $order->balance_due]);
        $this->assertFalse($order->is_paid);
        $this->assertSame('held', $order->stockReservations()->first()->status, 'held for the balance');
        $this->assertSame(PaymentAttempt::PAID, $attempt->fresh()->status);

        // Queued, never sent inside the callback.
        Mail::assertNotSent(\App\Mail\PaymentProblemAlertMail::class);
        Mail::assertQueued(\App\Mail\PaymentProblemAlertMail::class, fn ($mail) => $mail->hasTo('owner@serana.test') && $mail->order->is($order));
    }

    /** With the real mailer (not Mail::fake, which ignores raw mail), nothing is delivered during the callback. */
    public function test_the_callback_delivers_no_mail_itself(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $order = CustomOrder::create([
            'full_name' => 'Jane Doe', 'phone' => '254712345678', 'whatsapp_number' => '254712345678',
            'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 5000, 'is_paid' => false,
        ]);
        $order->paymentAttempts()->create(['checkout_request_id' => 'ws_CO_SHORT2', 'phone' => '254712345678', 'amount' => 5000, 'status' => PaymentAttempt::PENDING]);

        $this->postJson(route('mpesa.callback', ['token' => 'secret']), ['Body' => ['stkCallback' => [
            'ResultCode' => 0, 'CheckoutRequestID' => 'ws_CO_SHORT2', 'CallbackMetadata' => ['Item' => [
                ['Name' => 'Amount', 'Value' => 4000], ['Name' => 'MpesaReceiptNumber', 'Value' => 'RCPT2'],
            ]],
        ]]])->assertOk();

        $this->assertCount(0, app('mailer')->getSymfonyTransport()->messages(), 'mail was delivered inside the callback');
        \Illuminate\Support\Facades\Queue::assertPushed(\Illuminate\Mail\SendQueuedMailable::class,
            fn ($job) => $job->mailable instanceof \App\Mail\PaymentProblemAlertMail);
    }

    public function test_the_alert_tells_the_owner_what_was_paid_and_what_to_do(): void
    {
        $order = CustomOrder::create([
            'full_name' => 'Jane Doe', 'phone' => '254712345678', 'whatsapp_number' => '254712345678',
            'type' => 'standard', 'status' => OrderStatus::AMOUNT_MISMATCH, 'price_quoted' => 5000, 'is_paid' => false,
        ]);

        $mail = new AmountMismatchAlertMail($order, 5000, 4000);

        $mail->assertHasSubject("⚠ Payment amount mismatch — order {$order->cipher}");
        $mail->assertSeeInText('received an M-Pesa payment of KSh 4000');
        $mail->assertSeeInText('KSh 5000 was quoted');
        $mail->assertSeeInText('NOT marked paid');
        $mail->assertSeeInText('Jane Doe (254712345678)');
        $mail->assertSeeInText('manual refund or follow-up');

        // Plain text: a name is shown as typed, not HTML-escaped.
        $order->update(['full_name' => 'Ann & Co']);
        (new AmountMismatchAlertMail($order->fresh(), 5000, 4000))->assertSeeInText('Customer: Ann & Co (', false);
    }

    public function test_no_alert_is_queued_without_an_owner_inbox(): void
    {
        Mail::fake();
        config(['services.store.notification_email' => null, 'mail.from.address' => null]);

        $order = CustomOrder::create([
            'full_name' => 'Jane Doe', 'phone' => '254712345678', 'whatsapp_number' => '254712345678',
            'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 5000, 'is_paid' => false,
        ]);

        app(\App\Services\OrderSettlementService::class)->flagAmountMismatch($order, 5000, 4000);

        $this->assertSame(OrderStatus::AMOUNT_MISMATCH, $order->fresh()->status);
        Mail::assertNothingOutgoing();
    }
}
