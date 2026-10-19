<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CustomOrder;
use App\Models\User;
use App\Support\OrderPolicy;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batch 2 Phase B (design §2.4, D14, D15): the deposit, pay-link and deposit
 * policy are Studio-editable defaults; a bespoke order's deposit can be set
 * per order. Deposits are bespoke-only: shop and drop orders pay in full.
 */
class OrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): User
    {
        return User::factory()->create(['role' => $role, 'name' => ucfirst($role)]);
    }

    private function bespoke(int $quote): CustomOrder
    {
        return CustomOrder::create(['full_name' => 'Client', 'whatsapp_number' => '254712345678', 'type' => 'Gown', 'status' => 'pending', 'price_quoted' => $quote]);
    }

    private function policy(array $o = []): array
    {
        return array_merge(['deposit_percent' => 50, 'pay_link_days' => 14, 'deposit_policy' => 'Deposit refundable until work starts.'], $o);
    }

    public function test_defaults_are_half_the_quote_two_weeks_and_a_refund_rule(): void
    {
        $this->assertSame(50, OrderPolicy::depositPercent());
        $this->assertSame(14, OrderPolicy::payLinkDays());
        $this->assertSame('Deposit refundable until work starts (designing); non-refundable after.', OrderPolicy::depositPolicy());
    }

    public function test_only_the_owner_changes_the_order_policy(): void
    {
        $this->actingAs($this->as(Role::MANAGER))->put(route('admin.order_policy.update'), $this->policy(['deposit_percent' => 30]))->assertForbidden();
        $this->assertSame(50, OrderPolicy::depositPercent());

        $this->actingAs($this->as(Role::OWNER))->put(route('admin.order_policy.update'), $this->policy(['deposit_percent' => 30, 'pay_link_days' => 7]))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame([30, 7], [OrderPolicy::depositPercent(), OrderPolicy::payLinkDays()]);
        $this->assertSame(30, AuditLog::where('action', 'settings.order_policy.updated')->sole()->after['deposit_percent']);
    }

    public function test_the_policy_is_validated(): void
    {
        $owner = $this->as(Role::OWNER);
        foreach ([['deposit_percent' => 101], ['deposit_percent' => -1], ['deposit_percent' => 12.5], ['pay_link_days' => 0], ['pay_link_days' => 91], ['deposit_policy' => str_repeat('x', 301)]] as $bad) {
            $this->actingAs($owner)->put(route('admin.order_policy.update'), $this->policy($bad))->assertSessionHasErrors(array_key_first($bad));
        }
    }

    public function test_a_bespoke_deposit_is_the_policy_share_of_the_quote(): void
    {
        $this->assertSame(5000, $this->bespoke(10000)->fresh()->depositRequired());
        $this->assertSame(1667, $this->bespoke(3333)->fresh()->depositRequired(), 'rounded to whole shillings');
        $this->assertSame(0, $this->bespoke(0)->fresh()->depositRequired(), 'nothing until there is a quote');

        OrderPolicy::save($this->policy(['deposit_percent' => 30]));
        $this->assertSame(3000, $this->bespoke(10000)->fresh()->depositRequired());
    }

    public function test_shop_and_drop_orders_pay_in_full(): void
    {
        $shop = CustomOrder::create(['full_name' => 'S', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000]);
        $drop = CustomOrder::create(['full_name' => 'D', 'whatsapp_number' => '254712345678', 'type' => 'drop', 'status' => 'Pending Payment', 'price_quoted' => 3500]);

        $this->assertSame([5000, 3500], [$shop->fresh()->depositRequired(), $drop->fresh()->depositRequired()]);
    }

    public function test_owner_or_manager_sets_one_orders_deposit_and_it_is_audited(): void
    {
        $order = $this->bespoke(10000);

        $this->actingAs($this->as(Role::MANAGER))->put(route('studio.order.deposit', $order), ['deposit' => 4000, 'reason' => 'Regular client'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(4000, $order->fresh()->depositRequired());
        $log = AuditLog::where('action', 'order.deposit_set')->sole();
        $this->assertSame([['deposit' => 5000], ['deposit' => 4000], 'Regular client'], [$log->before, $log->after, $log->reason]);

        // Clearing it returns to the policy share.
        $this->put(route('studio.order.deposit', $order), ['deposit' => null, 'reason' => 'Back to standard'])->assertSessionHasNoErrors();
        $this->assertSame(5000, $order->fresh()->depositRequired());
    }

    public function test_a_deposit_override_must_fit_the_quote_and_carry_a_reason(): void
    {
        $order = $this->bespoke(10000);
        $owner = $this->as(Role::OWNER);

        $this->actingAs($owner)->put(route('studio.order.deposit', $order), ['deposit' => 12000, 'reason' => 'x'])->assertSessionHasErrors('deposit');
        $this->actingAs($owner)->put(route('studio.order.deposit', $order), ['deposit' => 3000])->assertSessionHasErrors('reason');
        $this->actingAs($this->as(Role::CASHIER))->put(route('studio.order.deposit', $order), ['deposit' => 3000, 'reason' => 'x'])->assertRedirect('/');

        $shop = CustomOrder::create(['full_name' => 'S', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000]);
        $this->actingAs($owner)->put(route('studio.order.deposit', $shop), ['deposit' => 1000, 'reason' => 'x'])->assertSessionHas('error');
        $this->assertSame(5000, $shop->fresh()->depositRequired());
    }
}
