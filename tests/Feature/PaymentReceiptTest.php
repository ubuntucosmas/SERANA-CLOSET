<?php

namespace Tests\Feature;

use App\Mail\OrderReceiptMail;
use App\Models\CustomOrder;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\OrderPolicy;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Batch 2 Phase B (design B): every payment, deposit or balance, for every
 * kind of order, sends a receipt (email + PDF) showing the total, this
 * payment, the amount paid to date and the balance due.
 */
class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.store.notification_email' => 'studio@serana.test']);
    }

    private function bespoke(int $quote = 10000): CustomOrder
    {
        return CustomOrder::create(['full_name' => 'Amina', 'email' => 'amina@example.com', 'whatsapp_number' => '254712345678', 'type' => 'Gown', 'status' => 'pending', 'price_quoted' => $quote]);
    }

    private function record(CustomOrder $order, int $amount, string $reference): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::OWNER]))
            ->post(route('studio.order.record_payment', $order), ['amount' => $amount, 'method' => 'mpesa_manual', 'reference' => $reference])->assertSessionHasNoErrors();
    }

    /** @return list<OrderReceiptMail> */
    private function receipts(): array
    {
        return Mail::sent(OrderReceiptMail::class)->all();
    }

    public function test_a_deposit_sends_a_receipt_with_this_payment_and_the_balance(): void
    {
        $order = $this->bespoke();

        $this->record($order, 5000, 'QDEP1');

        $receipts = $this->receipts();
        $this->assertCount(1, $receipts);
        $mail = $receipts[0];
        $this->assertTrue($mail->hasTo('amina@example.com') && $mail->hasBcc('studio@serana.test'));
        $this->assertSame('QDEP1', $mail->payment->external_reference);
        $mail->assertHasSubject("Payment received — {$order->cipher}");
        $mail->assertSeeInHtml('This payment');
        $mail->assertSeeInHtml('KSh 5,000');
        $mail->assertSeeInHtml('Paid to date');
        $mail->assertSeeInHtml('Balance due');
        $mail->assertSeeInHtml(OrderPolicy::depositPolicy());
    }

    public function test_the_balance_payment_sends_a_second_receipt_marked_paid_in_full(): void
    {
        $order = $this->bespoke();
        $this->record($order, 5000, 'QDEP1');

        $this->record($order, 5000, 'QBAL1');

        $receipts = $this->receipts();
        $this->assertCount(2, $receipts);
        $receipts[1]->assertHasSubject("Your Serana receipt — {$order->cipher}");
        $receipts[1]->assertSeeInHtml('Paid in full');
        $this->assertSame('QBAL1', $receipts[1]->payment->external_reference);
    }

    public function test_a_shop_order_paid_in_one_go_gets_exactly_one_receipt(): void
    {
        $shop = CustomOrder::create(['full_name' => 'Jane', 'email' => 'jane@example.com', 'whatsapp_number' => '254700000000', 'phone' => '254700000000',
            'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 4500, 'items_json' => [['name' => 'Silk Dress', 'price' => 4500, 'quantity' => 1]]]);

        app(OrderSettlementService::class)->markPaid($shop, 'QSHOP1');

        $this->assertCount(1, $this->receipts());
        $this->receipts()[0]->assertSeeInHtml('Paid in full');
    }

    public function test_money_beyond_the_total_is_shown_as_owed_back(): void
    {
        $order = $this->bespoke(5000);
        app(OrderSettlementService::class)->recordPayment($order, 5000, 'mpesa_stk', Actor::safaricom(), 'R1');
        app(OrderSettlementService::class)->recordPayment($order, 5000, 'mpesa_stk', Actor::safaricom(), 'R2');

        $this->assertCount(2, $this->receipts());
        // Phase C: a second full payment is "paid twice", with the refund promise.
        $this->receipts()[1]->assertSeeInHtml('We received this payment twice');
        $this->receipts()[1]->assertSeeInHtml('KSh 5,000 will be refunded to you within 2 working days');
    }

    public function test_the_pdf_receipt_shows_the_same_figures(): void
    {
        $order = $this->bespoke();
        $this->record($order, 5000, 'QDEP1');
        $payment = OrderPayment::sole();

        $html = view('receipts.luxury', OrderReceiptMail::viewData($order->fresh(), $payment))->render();

        foreach (['This payment', 'QDEP1', 'Paid to date', 'Balance due', 'KSh 5,000.00', OrderPolicy::depositPolicy()] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_a_flagged_wrong_amount_alerts_the_owner_instead_of_sending_a_receipt(): void
    {
        $shop = CustomOrder::create(['full_name' => 'Jane', 'email' => 'jane@example.com', 'whatsapp_number' => '254700000000', 'phone' => '254700000000',
            'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 4500]);

        app(OrderSettlementService::class)->flagAmountMismatch($shop, 4500, 4000, 'RSHORT');

        $this->assertCount(0, $this->receipts());
    }
}
