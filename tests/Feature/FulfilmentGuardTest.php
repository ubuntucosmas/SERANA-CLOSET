<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CustomOrder;
use App\Models\User;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batch 2 Phase B (design N7): bespoke work starts only once the deposit is
 * paid, and leaves the studio only once it is paid in full, unless the owner
 * overrides with a recorded reason.
 */
class FulfilmentGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => Role::OWNER, 'name' => 'Owner']);
    }

    private function bespoke(int $quote = 10000, string $status = 'pending'): CustomOrder
    {
        return CustomOrder::create(['full_name' => 'Client', 'whatsapp_number' => '254712345678', 'type' => 'Gown', 'status' => $status, 'price_quoted' => $quote]);
    }

    private function pay(CustomOrder $order, int $amount, string $reference): void
    {
        $this->actingAs($this->owner)->post(route('studio.order.record_payment', $order), ['amount' => $amount, 'method' => 'mpesa_manual', 'reference' => $reference])
            ->assertSessionHasNoErrors();
    }

    private function move(CustomOrder $order, string $status, array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->put(route('studio.update', $order), ['status' => $status, 'price_quoted' => (int) $order->fresh()->price_quoted] + $extra);
    }

    public function test_work_cannot_start_before_the_deposit_is_paid(): void
    {
        $order = $this->bespoke();

        $this->move($order, 'designing')->assertSessionHasErrors(['status' => 'Take the deposit (KSh 5,000) before work starts.']);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_work_cannot_start_without_a_quote(): void
    {
        $order = $this->bespoke(0);

        $this->move($order, 'designing')->assertSessionHasErrors('status');
    }

    public function test_once_the_deposit_is_paid_work_moves_through_production(): void
    {
        $order = $this->bespoke();
        $this->pay($order, 5000, 'QDEP');

        foreach (['designing', 'sewing', 'completed'] as $stage) {
            $this->move($order, $stage)->assertSessionHasNoErrors();
            $this->assertSame($stage, $order->fresh()->status);
        }
    }

    public function test_a_custom_deposit_is_the_bar_to_start(): void
    {
        $order = $this->bespoke();
        $this->actingAs($this->owner)->put(route('studio.order.deposit', $order), ['deposit' => 2000, 'reason' => 'Regular client']);
        $this->pay($order, 2000, 'QDEP');

        $this->move($order, 'designing')->assertSessionHasNoErrors();
    }

    public function test_an_order_with_a_balance_cannot_ship(): void
    {
        $order = $this->bespoke(10000, 'completed');
        $this->pay($order, 5000, 'QDEP');

        $this->move($order, 'shipped')->assertSessionHasErrors(['status' => 'KSh 5,000 is still owed. Take the balance before it leaves the studio.']);

        $this->pay($order, 5000, 'QBAL');
        $this->move($order, 'shipped')->assertSessionHasNoErrors();
        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_the_owner_may_override_with_a_reason_and_it_is_audited(): void
    {
        $order = $this->bespoke(10000, 'completed');
        $this->pay($order, 5000, 'QDEP');

        $this->move($order, 'shipped', ['override_reason' => 'Balance on delivery, agreed by phone'])->assertSessionHasNoErrors();

        $this->assertSame('shipped', $order->fresh()->status);
        $log = AuditLog::where('action', 'fulfilment.override')->sole();
        $this->assertSame('Balance on delivery, agreed by phone', $log->reason);
        $this->assertSame(['status' => 'completed', 'balance_due' => 5000], $log->before);
        $this->assertSame(['status' => 'shipped'], $log->after);
    }

    public function test_a_manager_cannot_override(): void
    {
        $order = $this->bespoke(10000);
        $manager = User::factory()->create(['role' => Role::MANAGER]);

        $this->move($order, 'designing', ['override_reason' => 'Trust me'], $manager)->assertSessionHasErrors('status');
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'fulfilment.override')->count());
    }

    public function test_moving_back_to_pending_is_always_allowed(): void
    {
        $order = $this->bespoke();
        $this->pay($order, 5000, 'QDEP');
        $this->move($order, 'designing');

        $this->move($order, 'pending')->assertSessionHasNoErrors();
        $this->assertSame('pending', $order->fresh()->status);
    }
}
