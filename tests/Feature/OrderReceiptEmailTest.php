<?php

namespace Tests\Feature;

use App\Mail\OrderReceiptMail;
use App\Models\CustomOrder;
use App\Services\OrderSettlementService;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderReceiptEmailTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrder(array $attrs = []): CustomOrder
    {
        return CustomOrder::create(array_merge([
            'full_name'    => 'Jane Doe',
            'email'        => 'jane@example.com',
            'whatsapp_number' => '254700000000',
            'phone'        => '254700000000',
            'type'         => 'standard',
            'status'       => OrderStatus::PENDING_PAYMENT,
            'price_quoted' => 4500,
            'items_json'   => [['name' => 'Silk Dress', 'quantity' => 1]],
        ], $attrs));
    }

    public function test_payment_confirmation_emails_customer_and_owner_once(): void
    {
        Mail::fake();
        config(['services.store.notification_email' => 'studio@serana.test']);

        $order = $this->pendingOrder();
        app(OrderSettlementService::class)->markPaid($order, 'QABC123XYZ');

        // Exactly one email — proves the listener isn't double-registered.
        Mail::assertSent(OrderReceiptMail::class, 1);
        Mail::assertSent(OrderReceiptMail::class, fn ($mail) =>
            $mail->hasTo('jane@example.com')
            && $mail->hasBcc('studio@serana.test')
            && $mail->order->is($order)
        );
    }

    public function test_owner_still_notified_when_order_has_no_customer_email(): void
    {
        Mail::fake();
        config(['services.store.notification_email' => 'studio@serana.test']);

        $order = $this->pendingOrder(['email' => null]);
        app(OrderSettlementService::class)->markPaid($order);

        Mail::assertSent(OrderReceiptMail::class, fn ($mail) => $mail->hasTo('studio@serana.test'));
    }

    public function test_no_email_on_failed_payment(): void
    {
        Mail::fake();

        $order = $this->pendingOrder();
        app(OrderSettlementService::class)->markUnpaid($order, OrderStatus::PAYMENT_FAILED);

        Mail::assertNothingSent();
    }

    /** The receipt email + attachment must render without blade/PDF errors. */
    public function test_receipt_mail_renders(): void
    {
        $order = $this->pendingOrder(['status' => OrderStatus::PAID, 'is_paid' => true]);

        $mail = new OrderReceiptMail($order);
        $rendered = $mail->render();

        $this->assertStringContainsString($order->cipher, $rendered);
        $this->assertStringContainsString('Track your order', $rendered);
        $this->assertNotEmpty($mail->attachments());
    }
}
