<?php

namespace Tests\Feature;

use App\Mail\RefundReceiptMail;
use App\Models\AuditLog;
use App\Models\CustomOrder;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase C item 1: the Studio Refunds queue (owner and manager) and Mark
 * refunded, which writes a refund ledger row (method, code unique like any
 * payment code, reason) and emails a refund receipt. Partial refunds keep the
 * order refund_due until it is fully refunded. No automatic money-out.
 */
class RefundQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.store.notification_email' => 'owner@serana.test']);
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role' => $role, 'name' => ucfirst($role)]);
    }

    /** A shop order paid twice: KSh 3,000 owed back. */
    private function paidTwice(): CustomOrder
    {
        $order = CustomOrder::create(['full_name' => 'Jane Wanjiru', 'email' => 'jane@example.com', 'whatsapp_number' => '254712345678', 'phone' => '254712345678',
            'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 3000, 'items_json' => [['name' => 'Cap', 'price' => 3000, 'quantity' => 1]]]);
        app(OrderSettlementService::class)->recordPayment($order, 3000, 'mpesa_stk', Actor::safaricom(), 'QPAY1');
        app(OrderSettlementService::class)->recordPayment($order->fresh(), 3000, 'mpesa_stk', Actor::safaricom(), 'QPAY2');
        Mail::fake(); // only what the refund sends

        return $order->fresh();
    }

    private function queue(User $as): array
    {
        return $this->actingAs($as)->get(route('studio.index', ['tab' => 'refunds']))->viewData('page')['props']['refunds'];
    }

    public function test_the_queue_lists_what_is_owed_why_since_when_and_how_to_reach_the_customer(): void
    {
        $order = $this->paidTwice();
        $this->travel(2)->days();

        $row = collect($this->queue($this->as(Role::MANAGER)))->firstWhere('reference', $order->cipher);
        $this->assertSame(3000, $row['owed']);
        $this->assertSame('Paid twice', $row['cause']);
        $this->assertSame(2, $row['age_days']);
        $this->assertSame(['Jane Wanjiru', '254712345678', 'jane@example.com'], [$row['customer'], $row['phone'], $row['email']]);
    }

    public function test_only_owner_and_manager_see_the_queue_and_mark_refunds(): void
    {
        $order = $this->paidTwice();
        $this->actingAs($this->as(Role::CASHIER))->post(route('studio.order.refund', $order), ['amount' => 3000, 'method' => 'mpesa_manual', 'reference' => 'QREF1', 'reason' => 'x'])
            ->assertRedirect(route('pos.index'));
        $this->assertSame(0, $order->payments()->where('type', 'refund')->count());
    }

    public function test_mark_refunded_needs_method_code_and_reason_and_no_more_than_is_owed(): void
    {
        $order = $this->paidTwice();
        $owner = $this->as(Role::OWNER);

        $this->actingAs($owner)->post(route('studio.order.refund', $order), ['amount' => 3000])->assertSessionHasErrors(['method', 'reference', 'reason']);
        $this->post(route('studio.order.refund', $order), ['amount' => 3500, 'method' => 'mpesa_manual', 'reference' => 'QREF1', 'reason' => 'Paid twice'])
            ->assertSessionHasErrors(['amount' => 'Only KSh 3,000 is owed to the customer.']);
        // A code already used for a payment (any order) is refused, as everywhere.
        $this->post(route('studio.order.refund', $order), ['amount' => 3000, 'method' => 'mpesa_manual', 'reference' => 'QPAY1', 'reason' => 'Paid twice'])
            ->assertSessionHasErrors(['reference' => "This M-Pesa code is already recorded on order {$order->cipher}."]);
        $this->assertSame(0, $order->payments()->where('type', 'refund')->count());
    }

    public function test_a_partial_refund_keeps_the_order_in_the_queue_until_fully_refunded(): void
    {
        $order = $this->paidTwice();
        $manager = $this->as(Role::MANAGER);

        $this->actingAs($manager)->post(route('studio.order.refund', $order), ['amount' => 1000, 'method' => 'mpesa_manual', 'reference' => 'QREF1', 'reason' => 'Paid twice; first part sent'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $fresh = $order->fresh();
        $this->assertSame(['refund_due', -2000], [$fresh->payment_status, $fresh->balance_due]);
        $this->assertSame(2000, collect($this->queue($manager))->firstWhere('reference', $order->cipher)['owed']);

        $this->post(route('studio.order.refund', $order), ['amount' => 2000, 'method' => 'cash', 'reference' => 'Cash at the studio', 'reason' => 'Rest in cash'])->assertSessionHasNoErrors();
        $fresh = $order->fresh();
        $this->assertSame(['paid', 0, null, null], [$fresh->payment_status, $fresh->balance_due, $fresh->refund_cause, $fresh->refund_due_since]);
        $this->assertNull(collect($this->queue($manager))->firstWhere('reference', $order->cipher));

        $refunds = $order->payments()->where('type', OrderPayment::REFUND)->orderBy('id')->get();
        $this->assertSame([[1000, 'mpesa_manual', 'QREF1', 'user', $manager->id], [2000, 'cash', 'Cash at the studio', 'user', $manager->id]],
            $refunds->map(fn ($r) => [$r->amount, $r->method, $r->external_reference, $r->actor_type, $r->actor_id])->all());
        $this->assertSame(2, AuditLog::where('action', 'refund.recorded')->count());
    }

    public function test_each_refund_emails_a_refund_receipt_with_a_pdf(): void
    {
        $order = $this->paidTwice();

        $this->actingAs($this->as(Role::OWNER))->post(route('studio.order.refund', $order), ['amount' => 1000, 'method' => 'mpesa_manual', 'reference' => 'QREF1', 'reason' => 'Paid twice']);

        Mail::assertQueued(RefundReceiptMail::class, function (RefundReceiptMail $mail) use ($order) {
            $mail->assertHasSubject("Refund sent — {$order->cipher}");
            $mail->assertSeeInHtml('KSh 1,000');
            $mail->assertSeeInHtml('Still to refund');
            $mail->assertSeeInHtml('KSh 2,000');
            $mail->assertSeeInHtml('QREF1');

            return $mail->hasTo('jane@example.com') && $mail->hasBcc('owner@serana.test') && count($mail->attachments()) === 1;
        });
    }
}
