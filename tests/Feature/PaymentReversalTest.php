<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CustomOrder;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase C Step 0 (owner decision): a booking that should not count (a legacy
 * double booking, a code typed on the wrong order) is reversed by the owner
 * with a reason, or confirmed genuine. A reversal is a ledger entry, not
 * money out; nothing is edited or deleted.
 */
class PaymentReversalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->owner = User::factory()->create(['role' => Role::OWNER, 'name' => 'Owner']);
    }

    private function shop(): CustomOrder
    {
        return CustomOrder::create(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'phone' => '254712345678', 'type' => 'standard',
            'status' => 'Pending Payment', 'price_quoted' => 3000, 'items_json' => [['name' => 'Cap', 'price' => 3000, 'quantity' => 1]]]);
    }

    /** Two orders paid with one code, the second booked with the backfill's duplicate mark. */
    private function legacyDoubleBooking(): array
    {
        $a = $this->shop();
        app(OrderSettlementService::class)->recordPayment($a, 3000, 'mpesa_stk', Actor::safaricom(), 'TJQA7PAID1');
        $b = $this->shop();
        app(OrderSettlementService::class)->recordPayment($b, 3000, 'mpesa_stk', Actor::safaricom(), "TJQA7PAID1#dup-order-{$b->id}");

        return [$a->fresh(), $b->fresh(), OrderPayment::where('custom_order_id', $b->id)->sole()];
    }

    public function test_the_owner_reverses_a_double_booking_and_the_ledger_follows(): void
    {
        [, $b, $payment] = $this->legacyDoubleBooking();
        $this->artisan('serana:ledger-check')->assertFailed();

        $this->actingAs($this->owner)->post(route('studio.payment.reverse', $payment), ['reason' => 'QA artefact: one KSh 3,000 payment on the statement'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $reversal = OrderPayment::where('reverses_payment_id', $payment->id)->sole();
        $this->assertSame(['reversal', 3000, 'user', $this->owner->id, null], [$reversal->type, $reversal->amount, $reversal->actor_type, $reversal->actor_id, $reversal->external_reference]);
        $fresh = $b->fresh();
        $this->assertSame([0, 3000, 'unpaid', false], [$fresh->amount_paid, $fresh->balance_due, $fresh->payment_status, $fresh->is_paid], 'the legacy flag follows the ledger');
        $this->assertSame('QA artefact: one KSh 3,000 payment on the statement', AuditLog::where('action', 'payment.reversed')->sole()->reason);
        Mail::assertNothingQueued(); // not money out: no refund receipt
        $this->artisan('serana:ledger-check')->assertSuccessful();
        $this->artisan('serana:ledger:verify')->doesntExpectOutputToContain('booked on more than one order')->assertSuccessful();
    }

    public function test_confirming_a_booking_genuine_clears_the_check_without_changing_money(): void
    {
        [, $b, $payment] = $this->legacyDoubleBooking();

        $this->actingAs($this->owner)->post(route('studio.payment.confirm', $payment), ['reason' => 'Statement shows two separate payments'])
            ->assertSessionHasNoErrors();

        $this->assertSame([3000, 'paid'], [$b->fresh()->amount_paid, $b->fresh()->payment_status]);
        $this->assertSame('genuine', DB::table('payment_reviews')->where('order_payment_id', $payment->id)->value('decision'));
        $this->assertSame(1, AuditLog::where('action', 'payment.confirmed_genuine')->count());
        $this->artisan('serana:ledger-check')->assertSuccessful();
        $this->artisan('serana:ledger:verify')->doesntExpectOutputToContain('booked on more than one order')->assertSuccessful();
    }

    public function test_a_booking_is_reversed_once_and_only_by_the_owner_with_a_reason(): void
    {
        [, , $payment] = $this->legacyDoubleBooking();
        $manager = User::factory()->create(['role' => Role::MANAGER]);

        $this->actingAs($manager)->post(route('studio.payment.reverse', $payment), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->owner)->post(route('studio.payment.reverse', $payment), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->owner)->post(route('studio.payment.reverse', $payment), ['reason' => 'first']);
        $this->actingAs($this->owner)->post(route('studio.payment.reverse', $payment), ['reason' => 'again'])->assertSessionHas('error');

        $this->assertSame(1, OrderPayment::where('reverses_payment_id', $payment->id)->count());
    }

    public function test_a_code_typed_on_the_wrong_order_can_be_reversed_and_booked_on_the_right_one(): void
    {
        // The code was typed on the wrong (bespoke) order; the real STK payment for the shop order then clashes.
        $wrong = CustomOrder::create(['full_name' => 'Amina', 'whatsapp_number' => '254712345678', 'type' => 'Gown', 'status' => 'pending', 'price_quoted' => 3000]);
        $this->actingAs($this->owner)->post(route('studio.order.record_payment', $wrong), ['amount' => 3000, 'method' => 'mpesa_manual', 'reference' => 'QREAL1']);
        $right = $this->shop();
        app(OrderSettlementService::class)->flagReferenceConflict($right, 'QREAL1', $wrong);

        $this->post(route('studio.payment.reverse', $wrong->payments()->sole()), ['reason' => 'Typed on the wrong order']);
        $right->fresh()->update(['payment_method' => 'whatsapp']); // confirmed by hand, as a WhatsApp order
        $this->post(route('studio.order.record_payment', $right), ['amount' => 3000, 'method' => 'mpesa_manual', 'reference' => 'QREAL1'])
            ->assertSessionHasNoErrors();

        $fresh = $right->fresh();
        $this->assertSame(['paid', 'none', null], [$fresh->payment_status, $fresh->resolution, $fresh->resolution_reason], 'booking the code clears the conflict');
        $this->assertSame('QREAL1#after-reversal-' . $wrong->payments()->where('type', 'payment')->value('id'), $fresh->payments()->value('external_reference'));
        $this->assertSame(0, $wrong->fresh()->amount_paid);
    }

    public function test_refunds_and_reversals_together_never_exceed_what_was_paid(): void
    {
        $order = $this->shop();
        app(OrderSettlementService::class)->recordPayment($order, 3000, 'mpesa_stk', Actor::safaricom(), 'QN2');
        $payment = $order->payments()->sole();
        DB::table('order_payments')->insert(['custom_order_id' => $order->id, 'type' => 'refund', 'amount' => 1000, 'method' => 'mpesa_manual',
            'external_reference' => 'QREF-N2', 'actor_type' => 'user', 'occurred_at' => now(), 'created_at' => now()]);

        // Reversing the KSh 3,000 booking would make refunds + reversals (4,000) exceed payments (3,000).
        $this->actingAs($this->owner)->post(route('studio.payment.reverse', $payment), ['reason' => 'x'])->assertSessionHas('error');
        $this->assertSame(0, OrderPayment::where('type', 'reversal')->count());
        $this->ledgerChecks = []; // the raw refund row above skipped recompute on purpose
    }
}
