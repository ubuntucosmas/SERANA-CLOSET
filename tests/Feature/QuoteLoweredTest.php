<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CustomOrder;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase C item 5: a bespoke quote lowered below what the customer has paid.
 * The Studio asks for a reason first; the excess is owed back (Refunds); the
 * pay page says so; the piece can't leave the studio until the refund is
 * recorded (owner override with a reason); the ledger check agrees.
 */
class QuoteLoweredTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->owner = User::factory()->create(['role' => Role::OWNER, 'name' => 'Owner']);
    }

    /** A gown quoted 10,000 with 6,000 paid, being sewn. */
    private function gown(int $paid = 6000): CustomOrder
    {
        $order = CustomOrder::create(['full_name' => 'Amina Otieno', 'email' => 'amina@example.com', 'whatsapp_number' => '254712345678',
            'type' => 'Gown', 'status' => 'sewing', 'price_quoted' => 10000]);
        app(OrderSettlementService::class)->recordPayment($order, $paid, 'mpesa_manual', Actor::user($this->owner), 'QDEP1');

        return $order->fresh();
    }

    private function save(CustomOrder $order, array $data)
    {
        return $this->actingAs($this->owner)->put(route('studio.update', $order), array_merge(['status' => $order->legacyStatus(), 'internal_notes' => null], $data));
    }

    public function test_lowering_below_what_was_paid_needs_a_reason(): void
    {
        $order = $this->gown();

        $this->save($order, ['price_quoted' => 5000])
            ->assertSessionHasErrors(['price_quoted' => 'KSh 6,000 is already paid, so KSh 1,000 would be owed back to the customer. Add a reason to lower the quote.']);
        $this->assertSame(10000, $order->fresh()->chargeable_total);

        $this->save($order, ['price_quoted' => 5000, 'quote_reason' => 'Simpler beading agreed'])->assertSessionHasNoErrors();
        $fresh = $order->fresh();
        $this->assertSame([5000, -1000, 'refund_due', 'Quote lowered below the amount paid'], [$fresh->chargeable_total, $fresh->balance_due, $fresh->payment_status, $fresh->refund_cause]);
        $this->assertSame('Simpler beading agreed', $order->lines()->latest('id')->value('reason'));
        $this->assertSame('Simpler beading agreed', AuditLog::where('action', 'order.quote_lowered')->sole()->reason);
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }

    public function test_a_paid_in_full_quote_may_be_lowered_but_not_raised(): void
    {
        $order = $this->gown(10000);

        $this->save($order, ['price_quoted' => 12000])->assertSessionHasErrors('price_quoted');
        $this->save($order, ['price_quoted' => 8000, 'quote_reason' => 'Discount agreed after a delay'])->assertSessionHasNoErrors();
        $this->assertSame([-2000, 'refund_due'], [$order->fresh()->balance_due, $order->fresh()->payment_status]);
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }

    public function test_the_pay_page_says_what_will_be_refunded(): void
    {
        $order = $this->gown();
        $this->save($order, ['price_quoted' => 5000, 'quote_reason' => 'Simpler beading agreed']);

        $this->get($order->fresh()->pay_link)->assertOk()->assertInertia(fn ($page) => $page->component('Pay')
            ->where('order.balance_due', 0)->where('order.refund_due', 1000)->where('order.can_pay', false));
    }

    public function test_it_cannot_ship_until_the_refund_is_recorded_unless_the_owner_overrides(): void
    {
        $order = $this->gown();
        $this->save($order, ['price_quoted' => 5000, 'quote_reason' => 'Simpler beading agreed']);
        $manager = User::factory()->create(['role' => Role::MANAGER]);

        $this->actingAs($manager)->put(route('studio.update', $order), ['status' => 'shipped', 'override_reason' => 'please'])
            ->assertSessionHasErrors(['status' => 'KSh 1,000 is owed back to the customer. Record the refund before it leaves the studio.']);

        $this->actingAs($this->owner)->put(route('studio.update', $order), ['status' => 'shipped', 'override_reason' => 'Refund sent from the personal line; recording tomorrow'])
            ->assertSessionHasNoErrors();
        $this->assertSame('shipped', $order->fresh()->fulfilment_status);
    }

    public function test_once_refunded_it_ships(): void
    {
        $order = $this->gown();
        $this->save($order, ['price_quoted' => 5000, 'quote_reason' => 'Simpler beading agreed']);
        $this->actingAs($this->owner)->post(route('studio.order.refund', $order), ['amount' => 1000, 'method' => 'mpesa_manual', 'reference' => 'QREF9', 'reason' => 'Quote lowered']);

        $this->save($order->fresh(), ['status' => 'shipped'])->assertSessionHasNoErrors();
        $this->assertSame(['shipped', 'paid'], [$order->fresh()->fulfilment_status, $order->fresh()->payment_status]);
        $this->artisan('serana:ledger-check')->assertSuccessful();
    }
}
