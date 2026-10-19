<?php

namespace Tests\Feature;

use App\Mail\BespokeCancelledMail;
use App\Models\AuditLog;
use App\Models\CustomOrder;
use App\Models\OrderLine;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\OrderStatus;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase C item 6 (rule N8): cancelling a bespoke order. Before designing, the
 * deposit is refunded. From designing on, the deposit is kept as a retained
 * line with the reason, and anything paid above it is owed back. The
 * customer is told which applied.
 */
class BespokeCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.store.notification_email' => 'owner@serana.test']);
        $this->owner = User::factory()->create(['role' => Role::OWNER, 'name' => 'Owner']);
    }

    /** A gown quoted 10,000 (deposit 5,000) at $stage with $paid received. */
    private function gown(string $stage, int $paid): CustomOrder
    {
        $order = CustomOrder::create(['full_name' => 'Amina Otieno', 'email' => 'amina@example.com', 'whatsapp_number' => '254712345678',
            'type' => 'Gown', 'status' => $stage, 'price_quoted' => 10000]);
        if ($paid > 0) {
            app(OrderSettlementService::class)->recordPayment($order, $paid, 'mpesa_manual', Actor::user($this->owner), 'QDEP' . $paid);
        }
        Mail::fake();

        return $order->fresh();
    }

    private function cancel(CustomOrder $order, string $reason = 'Customer changed plans', ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->post(route('studio.order.cancel_bespoke', $order), ['reason' => $reason]);
    }

    public function test_before_designing_the_deposit_is_refunded(): void
    {
        $order = $this->gown('pending', 5000);

        $this->cancel($order)->assertSessionHasNoErrors()->assertSessionHas('success');

        $fresh = $order->fresh();
        Mail::assertQueued(BespokeCancelledMail::class, fn (BespokeCancelledMail $mail) => $mail->assertDontSeeInHtml('Cancelled at:') !== null);
        $this->assertSame(['cancelled', 0, -5000, 'refund_due', 'Cancelled before work started', OrderStatus::CANCELLED],
            [$fresh->order_state, $fresh->chargeable_total, $fresh->balance_due, $fresh->payment_status, $fresh->refund_cause, $fresh->status]);
        Mail::assertQueued(BespokeCancelledMail::class, function (BespokeCancelledMail $mail) {
            $mail->assertSeeInHtml('Your deposit of KSh 5,000 will be refunded to you within 2 working days');

            return $mail->hasTo('amina@example.com');
        });
        $this->assertSame(['stage' => 'pending', 'retained' => 0, 'owed_back' => 5000], AuditLog::where('action', 'order.cancelled')->sole()->after);
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }

    public function test_from_designing_the_deposit_is_retained_and_any_excess_owed_back(): void
    {
        $order = $this->gown('designing', 6000);

        $this->cancel($order, 'Customer cancelled after the toile')->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame(['cancelled', 5000, 6000, -1000, 'refund_due'], [$fresh->order_state, $fresh->chargeable_total, $fresh->amount_paid, $fresh->balance_due, $fresh->payment_status]);
        $retained = $order->lines()->where('line_type', OrderLine::RETAINED_DEPOSIT)->sole();
        $this->assertSame([5000, 'Customer cancelled after the toile'], [$retained->amount, $retained->reason]);
        $this->assertSame(['designing', OrderStatus::CANCELLED], [$fresh->fulfilment_status, $fresh->status], 'the stage it reached is kept');
        Mail::assertQueued(BespokeCancelledMail::class, function (BespokeCancelledMail $mail) {
            $mail->assertSeeInHtml('your deposit of KSh 5,000 is kept');
            $mail->assertSeeInHtml('Cancelled at: Designing');
            $mail->assertSeeInHtml('KSh 1,000 you paid above the deposit will be refunded to you within 2 working days');

            return true;
        });
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }

    public function test_from_designing_with_only_the_deposit_paid_nothing_is_owed(): void
    {
        $order = $this->gown('sewing', 5000);

        $this->cancel($order)->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame([5000, 0, 'cancelled'], [$fresh->chargeable_total, $fresh->balance_due, $fresh->order_state]);
        $this->assertNull($fresh->refund_cause);
        Mail::assertQueued(BespokeCancelledMail::class, function (BespokeCancelledMail $mail) {
            $mail->assertSeeInHtml('your deposit of KSh 5,000 is kept');
            $mail->assertDontSeeInHtml('will be refunded');

            return true;
        });
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }

    public function test_an_unpaid_brief_just_closes(): void
    {
        $order = $this->gown('pending', 0);

        $this->cancel($order)->assertSessionHasNoErrors();
        $this->assertSame([0, 0, 'cancelled'], [$order->fresh()->chargeable_total, $order->fresh()->balance_due, $order->fresh()->order_state]);
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }

    public function test_cancelling_needs_a_reason_and_a_bespoke_order_that_has_not_left(): void
    {
        $order = $this->gown('pending', 5000);
        $this->cancel($order, '')->assertSessionHasErrors('reason');

        $shipped = $this->gown('shipped', 10000);
        $this->cancel($shipped)->assertSessionHasErrors(['reason' => 'This order has already left the studio.']);

        $this->cancel($order)->assertSessionHasNoErrors();
        $this->cancel($order->fresh())->assertSessionHasErrors(['reason' => 'This order is already cancelled.']);

        $cashier = User::factory()->create(['role' => Role::CASHIER]);
        $this->cancel($this->gown('pending', 0), 'x', $cashier)->assertRedirect('/');
    }
}
