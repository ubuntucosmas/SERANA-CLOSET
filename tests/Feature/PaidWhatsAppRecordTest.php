<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\PaymentAttempt;
use App\Models\ThemeSetting;
use App\Services\OrderSettlementService;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * After an M-Pesa payment the customer posts the paid order to the studio on
 * WhatsApp. The record must come from the settled order, never the browser.
 */
class PaidWhatsAppRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([\App\Events\MpesaWebhookHandled::class]); // only the receipt event: model events must still fire
        ThemeSetting::set('whatsapp_number', '0711 222 333');
    }

    private function order(): CustomOrder
    {
        return CustomOrder::forceCreate([
            'full_name' => 'Amina Njeri', 'email' => 'a@example.com', 'phone' => '254712345678', 'whatsapp_number' => '254712345678',
            'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 7500, 'is_paid' => false,
            'address' => 'Kilimani Rd', 'city' => 'Nairobi', 'payment_method' => 'mpesa',
            'items_json' => [
                ['id' => 1, 'name' => 'Linen Set', 'price' => 5000, 'quantity' => 1, 'options' => ['top_size' => 'M', 'bottom_size' => 'L']],
                ['id' => 2, 'name' => 'Hoodie', 'price' => 2500, 'quantity' => 1, 'options' => ['top_size' => 'S']],
            ],
        ]);
    }

    private function text(?string $url): string
    {
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $q);

        return $q['text'] ?? '';
    }

    public function test_no_record_is_offered_before_payment(): void
    {
        $order = $this->order();

        $this->withSession(['serana.order_ids' => [$order->id]])->getJson(route('mpesa.status', $order->id))
            ->assertOk()->assertJsonPath('whatsapp_url', null);
        $this->assertNull($order->publicTrackingPayload()['paid_whatsapp_url']);
    }

    public function test_paid_order_record_carries_the_settled_payment_details(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 09:30:00', 'UTC'));
        $order = $this->order();
        $order->paymentAttempts()->create(['checkout_request_id' => 'ws_CO_1', 'phone' => '254798765432', 'amount' => 7500, 'status' => PaymentAttempt::PAID]);
        app(OrderSettlementService::class)->markPaid($order, 'TJK4ABC123');

        $url = $this->withSession(['serana.order_ids' => [$order->id]])->getJson(route('mpesa.status', $order->id))
            ->assertOk()->json('whatsapp_url');

        $this->assertStringStartsWith('https://wa.me/254711222333?text=', $url, 'goes to the store number set in the Studio');
        $text = $this->text($url);
        foreach ([
            "*Order code: {$order->cipher}*", '*M-Pesa code: TJK4ABC123*', 'Amount paid: KSh 7,500',
            'Paid on: Oct 18, 2026 12:30 PM (Nairobi)', 'Paid from: +254 798 765 432',
            '1. Linen Set ×1 — KSh 5,000', 'Top M · Bottom L', '2. Hoodie ×1 — KSh 2,500', 'Size S',
            'Amina Njeri · +254 712 345 678', 'Kilimani Rd, Nairobi', route('track', ['ref' => $order->cipher]),
        ] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertSame($url, $order->fresh()->publicTrackingPayload()['paid_whatsapp_url'], 'tracking offers the same record');
    }

    public function test_payment_confirmed_without_a_code_asks_for_it_honestly(): void
    {
        $order = $this->order();
        app(OrderSettlementService::class)->markPaid($order); // reconciliation: Safaricom returns no code

        $text = $this->text($this->withSession(['serana.order_ids' => [$order->id]])
            ->getJson(route('mpesa.status', $order->id))->json('whatsapp_url'));

        $this->assertStringContainsString('*M-Pesa code: [add the code from your M-Pesa SMS]*', $text);
        $this->assertStringContainsString('Paid from: +254 712 345 678', $text);
    }

    public function test_another_session_cannot_read_the_record(): void
    {
        $order = $this->order();
        app(OrderSettlementService::class)->markPaid($order, 'TJK4ABC123');

        $this->getJson(route('mpesa.status', $order->id))->assertNotFound()->assertJsonMissingPath('whatsapp_url');
    }
}
