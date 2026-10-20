<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Location;
use App\Models\TillSession;
use App\Models\User;
use App\Support\Role;
use App\Support\ShopPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase E-b (design §15.2, §15.3, D12): the till. The tablet is unlocked by a
 * staff PIN and every till action is taken as that person; it locks itself
 * after the idle minutes. One open session per register; every cash movement
 * is append-only; closing computes expected cash and the variance, and a
 * variance over the alert needs a reason and a manager's PIN.
 */
class TillTest extends TestCase
{
    use RefreshDatabase;

    private User $tablet;
    private User $cashier;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        // The shop tablet is signed in as a staff account; people take turns with their PINs.
        $this->tablet = User::factory()->create(['role' => Role::CASHIER, 'name' => 'Shop tablet', 'pos_pin' => Hash::make('1111')]);
        $this->cashier = User::factory()->create(['role' => Role::CASHIER, 'name' => 'Wanjiku', 'pos_pin' => Hash::make('2468')]);
        $this->manager = User::factory()->create(['role' => Role::MANAGER, 'name' => 'Akinyi', 'pos_pin' => Hash::make('9753')]);
    }

    private function unlock(string $pin = '2468')
    {
        return $this->actingAs($this->tablet)->postJson(route('pos.unlock'), ['pin' => $pin]);
    }

    private function open(int $float = 2000)
    {
        return $this->postJson(route('pos.till.open'), ['register' => 'Till 1', 'float' => $float]);
    }

    public function test_a_staff_pin_unlocks_the_till_and_wrong_or_suspended_pins_dont(): void
    {
        $this->unlock('0000')->assertUnprocessable()->assertJsonPath('errors.pin.0', 'That PIN doesn’t match anyone.');
        $this->cashier->forceFill(['suspended_at' => now()])->save();
        $this->unlock('2468')->assertUnprocessable();
        $this->unlock('9753')->assertOk()->assertJsonPath('operator.name', 'Akinyi');
    }

    public function test_till_actions_need_an_unlocked_till_and_lock_after_idle_minutes(): void
    {
        $this->actingAs($this->tablet)->open()->assertStatus(423)->assertJsonPath('message', 'Enter your PIN.');
        $this->unlock()->assertOk();
        $this->open()->assertOk();

        $this->travel(ShopPolicy::posIdleMinutes() + 1)->minutes();
        $this->postJson(route('pos.till.payout'), ['amount' => 100, 'reason' => 'x', 'approval_pin' => '9753'])->assertStatus(423);
    }

    public function test_the_tablet_must_be_staff(): void
    {
        $customer = User::factory()->create(['role' => Role::CLIENT]);
        $this->actingAs($customer)->postJson(route('pos.unlock'), ['pin' => '2468'])->assertForbidden();
    }

    public function test_opening_records_the_float_as_the_pin_holder_and_one_session_per_register(): void
    {
        $this->unlock();
        $this->open(2500)->assertOk();

        $session = TillSession::sole();
        $this->assertSame([Location::sole()->id, 'Till 1', $this->cashier->id, 2500, 'open'], [$session->location_id, $session->register, $session->opened_by, $session->opening_float, $session->status]);
        $this->assertSame([['float', 2500, $this->cashier->id]], CashMovement::all()->map(fn ($m) => [$m->type, $m->amount, $m->actor_id])->all());
        $this->open(100)->assertUnprocessable()->assertJsonPath('errors.register.0', 'Till 1 is already open.');
    }

    public function test_a_payout_needs_a_reason_and_a_manager_pin(): void
    {
        $this->unlock();
        $this->open();

        $this->postJson(route('pos.till.payout'), ['amount' => 300, 'reason' => 'Transport for a delivery'])->assertUnprocessable()->assertJsonValidationErrors('approval_pin');
        $this->postJson(route('pos.till.payout'), ['amount' => 300, 'reason' => 'Transport for a delivery', 'approval_pin' => '2468'])
            ->assertUnprocessable()->assertJsonPath('errors.approval_pin.0', 'A manager or the owner must approve with their PIN.');
        $this->postJson(route('pos.till.payout'), ['amount' => 300, 'reason' => 'Transport for a delivery', 'approval_pin' => '9753'])->assertOk();

        $payout = CashMovement::where('type', 'payout')->sole();
        $this->assertSame([-300, $this->cashier->id, $this->manager->id], [$payout->amount, $payout->actor_id, $payout->approved_by]);
        $this->assertSame($this->manager->id, AuditLog::where('action', 'till.payout')->sole()->after['approved_by']);
    }

    public function test_closing_computes_expected_cash_and_the_variance(): void
    {
        $this->unlock();
        $this->open(2000);
        $session = TillSession::sole();
        // Cash taken and given back during the day (sales and refunds write these; see CounterSaleTest).
        CashMovement::create(['till_session_id' => $session->id, 'type' => 'sale', 'amount' => 5000, 'actor_type' => 'user', 'actor_id' => $this->cashier->id, 'occurred_at' => now()]);
        CashMovement::create(['till_session_id' => $session->id, 'type' => 'refund', 'amount' => -1000, 'actor_type' => 'user', 'actor_id' => $this->cashier->id, 'occurred_at' => now()]);

        $this->postJson(route('pos.till.close'), ['counted' => 5900])->assertOk()
            ->assertJsonPath('report.expected_cash', 6000)->assertJsonPath('report.variance', -100);

        $closed = $session->fresh();
        $this->assertSame(['closed', 6000, 5900, -100, $this->cashier->id], [$closed->status, $closed->expected_cash, $closed->counted_cash, $closed->variance, $closed->closed_by]);
    }

    public function test_a_variance_over_the_alert_needs_a_reason_and_a_manager(): void
    {
        $this->unlock();
        $this->open(2000);

        $this->postJson(route('pos.till.close'), ['counted' => 1500])->assertUnprocessable()
            ->assertJsonValidationErrors(['variance_reason', 'approval_pin']);
        $this->postJson(route('pos.till.close'), ['counted' => 1500, 'variance_reason' => 'Gave wrong change once', 'approval_pin' => '9753'])->assertOk();

        $closed = TillSession::sole();
        $this->assertSame([-500, 'Gave wrong change once', $this->manager->id], [$closed->variance, $closed->variance_reason, $closed->acknowledged_by]);
    }

    public function test_cash_movements_are_append_only(): void
    {
        $this->unlock();
        $this->open();
        $this->expectException(\LogicException::class);
        CashMovement::sole()->update(['amount' => 1]);
    }

    public function test_the_owner_sets_the_shop_policy(): void
    {
        $owner = User::factory()->create(['role' => Role::OWNER]);
        $this->assertSame([10, 500, 200, 30, 10], [ShopPolicy::cashierDiscountPercent(), ShopPolicy::cashierDiscountCap(), ShopPolicy::tillVarianceAlert(), ShopPolicy::posHoldMinutes(), ShopPolicy::posIdleMinutes()]);

        $this->actingAs($this->manager)->put(route('admin.shop_policy.update'), ['cashier_discount_percent' => 50])->assertForbidden();
        $this->actingAs($owner)->put(route('admin.shop_policy.update'), ['cashier_discount_percent' => 15, 'cashier_discount_cap' => 800, 'till_variance_alert' => 100,
            'pos_hold_minutes' => 20, 'pos_idle_minutes' => 5, 'receipt_footer' => 'Exchanges within 7 days with this receipt.'])->assertSessionHasNoErrors();
        $this->assertSame([15, 800, 100, 20, 5, 'Exchanges within 7 days with this receipt.'], [ShopPolicy::cashierDiscountPercent(), ShopPolicy::cashierDiscountCap(),
            ShopPolicy::tillVarianceAlert(), ShopPolicy::posHoldMinutes(), ShopPolicy::posIdleMinutes(), ShopPolicy::receiptFooter()]);
        $this->actingAs($owner)->put(route('admin.shop_policy.update'), ['cashier_discount_percent' => 101])->assertSessionHasErrors('cashier_discount_percent');
    }
}
