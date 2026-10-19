<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudioStatsTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attrs): CustomOrder
    {
        return CustomOrder::forceCreate(array_merge(['full_name' => 'Client', 'whatsapp_number' => '254700000000', 'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 1000, 'is_paid' => false], $attrs));
    }

    private function stats(): array
    {
        $admin = User::factory()->create(['role' => 'owner']);
        $stats = null;
        $this->actingAs($admin)->get(route('studio.index'))->assertInertia(function (Assert $page) use (&$stats) {
            $stats = $page->toArray()['props']['stats'];
        });
        return $stats;
    }

    public function test_paid_this_week_counts_when_money_arrived_not_when_the_order_was_placed(): void
    {
        // Placed 10 days ago, paid today: counts.
        $this->order(['created_at' => now()->subDays(10), 'is_paid' => true, 'status' => OrderStatus::PAID, 'price_quoted' => 3000, 'paid_at' => now()]);
        // Placed and paid 10 days ago: does not count.
        $this->order(['created_at' => now()->subDays(10), 'is_paid' => true, 'status' => OrderStatus::PAID, 'price_quoted' => 9000, 'paid_at' => now()->subDays(10)]);
        // Paid this week, then archived after dispatch: still money received.
        $this->order(['is_paid' => true, 'status' => 'shipped', 'price_quoted' => 500, 'paid_at' => now()->subDay()])->delete();
        // Unpaid: never counts.
        $this->order(['price_quoted' => 7000]);

        $this->assertSame(3500.0, (float) $this->stats()['revenue_week']);
    }

    public function test_in_production_excludes_new_briefs_awaiting_a_quote(): void
    {
        $this->order(['type' => 'Hoodie', 'status' => 'pending']);
        $this->order(['type' => 'Hoodie', 'status' => 'designing']);
        $this->order(['type' => 'Hoodie', 'status' => 'sewing']);
        $this->order(['type' => 'Hoodie', 'status' => 'shipped']);

        $this->assertSame(2, $this->stats()['in_production']);
    }

    public function test_m_pesa_settlement_and_studio_payments_record_the_payment_time(): void
    {
        Carbon::setTestNow('2026-10-16 10:00:00');
        $shop = $this->order([]);
        app(OrderSettlementService::class)->markPaid($shop, 'QK1');
        $this->assertEquals('2026-10-16 10:00:00', $shop->fresh()->paid_at->toDateTimeString());

        $brief = $this->order(['type' => 'Hoodie', 'status' => 'designing', 'price_quoted' => 0]);
        Carbon::setTestNow('2026-10-17 09:30:00');
        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->post(route('studio.order.record_payment', $brief), ['amount' => 4500, 'reference' => 'QK2']);
        $this->assertEquals('2026-10-17 09:30:00', $brief->fresh()->paid_at->toDateTimeString());

        // A later stage change must not move the payment date.
        Carbon::setTestNow('2026-10-20 12:00:00');
        $brief->update(['status' => 'sewing']);
        $this->assertEquals('2026-10-17 09:30:00', $brief->fresh()->paid_at->toDateTimeString());
        Carbon::setTestNow();
    }
}
