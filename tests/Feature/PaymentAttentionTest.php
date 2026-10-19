<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\OrderStatus;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase C item 7: the Studio's payment Attention list. Every refund owed,
 * every order waiting for a decision, every amount mismatch and every
 * unexpected bespoke amount, oldest first, with the total count for the
 * dashboard. An unexpected amount leaves the list once the owner confirms it.
 */
class PaymentAttentionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->owner = User::factory()->create(['role' => Role::OWNER]);
    }

    private function shop(string $name): CustomOrder
    {
        return CustomOrder::create(['full_name' => $name, 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 3000,
            'items_json' => [['name' => 'Cap', 'price' => 3000, 'quantity' => 1]]]);
    }

    private function attention(): array
    {
        return $this->actingAs($this->owner)->get(route('studio.index'))->viewData('page')['props']['attention']['payments'];
    }

    public function test_every_payment_problem_is_listed_oldest_first_with_a_count(): void
    {
        // 1. Unexpected bespoke amount (oldest).
        $gown = CustomOrder::create(['full_name' => 'Gown', 'whatsapp_number' => '254712345678', 'type' => 'Gown', 'status' => 'pending', 'price_quoted' => 10000]);
        $attempt = $gown->paymentAttempts()->create(['checkout_request_id' => 'ws_G', 'phone' => '254712345678', 'amount' => 5000, 'status' => 'pending']);
        app(OrderSettlementService::class)->recordPayment($gown, 3000, 'mpesa_stk', Actor::safaricom(), 'QG1', $attempt);
        $this->travel(1)->days();

        // 2. Paid twice: a refund owed.
        $twice = $this->shop('Twice');
        app(OrderSettlementService::class)->recordPayment($twice, 3000, 'mpesa_stk', Actor::safaricom(), 'QT1');
        app(OrderSettlementService::class)->recordPayment($twice->fresh(), 3000, 'mpesa_stk', Actor::safaricom(), 'QT2');
        $this->travel(1)->days();

        // 3. Legacy amount mismatch.
        $mismatch = $this->shop('Mismatch');
        app(OrderSettlementService::class)->flagAmountMismatch($mismatch, 3000, 2500);
        $this->travel(1)->days();

        // 4. Waiting for a decision (newest).
        $conflict = $this->shop('Conflict');
        app(OrderSettlementService::class)->flagReferenceConflict($conflict, 'QT1', $twice);
        $this->travel(1)->hours();

        $items = $this->attention();
        $this->assertSame(['unexpected_amount', 'refund_due', 'amount_mismatch', 'needs_resolution'], array_column($items, 'kind'));
        $this->assertSame(['Gown', 'Twice', 'Mismatch', 'Conflict'], array_column($items, 'full_name'));
        $this->assertSame([3, 2, 1, 0], array_column($items, 'age_days'));
        $this->assertSame('Unexpected amount: KSh 3,000 paid against a KSh 5,000 prompt', $items[0]['detail']);
        $this->assertSame(['Refund owed', 'Paid twice', 3000], [$items[1]['label'], $items[1]['detail'], $items[1]['amount']]);
        $this->assertStringContainsString('QT1 is already recorded', $items[3]['detail']);

        $this->actingAs($this->owner)->get(route('studio.index'))->assertInertia(fn ($page) => $page->where('attention.payments_count', 4));

        // The owner confirms the unexpected amount: it leaves the list.
        $payment = $gown->payments()->sole();
        $this->actingAs($this->owner)->post(route('studio.payment.confirm', $payment), ['reason' => 'Customer paid part on purpose'])->assertSessionHasNoErrors();
        $this->assertSame(['refund_due', 'amount_mismatch', 'needs_resolution'], array_column($this->attention(), 'kind'));
    }

    public function test_a_waiting_order_and_a_settled_refund_are_not_problems(): void
    {
        $twice = $this->shop('Twice');
        app(OrderSettlementService::class)->recordPayment($twice, 3000, 'mpesa_stk', Actor::safaricom(), 'QT1');
        app(OrderSettlementService::class)->recordPayment($twice->fresh(), 3000, 'mpesa_stk', Actor::safaricom(), 'QT2');
        app(OrderSettlementService::class)->recordRefund($twice->fresh(), 3000, 'mpesa_manual', Actor::user($this->owner), 'QR1', 'Paid twice');

        $this->assertSame([], $this->attention());
    }
}
