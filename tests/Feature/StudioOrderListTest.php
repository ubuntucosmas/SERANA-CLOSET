<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudioOrderListTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attrs = []): CustomOrder
    {
        return CustomOrder::forceCreate(array_merge(['full_name' => 'Client', 'whatsapp_number' => '254700000000', 'phone' => '254700000000', 'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 1000, 'is_paid' => false], $attrs));
    }

    private function studio(array $query = []): Assert
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $captured = null;
        $this->actingAs($admin)->get(route('studio.index', $query))->assertOk()->assertInertia(function (Assert $page) use (&$captured) { $captured = $page; });
        return $captured;
    }

    public function test_orders_arrive_a_page_at_a_time_newest_first(): void
    {
        foreach (range(1, 45) as $i) $this->order(['full_name' => "Client {$i}", 'created_at' => now()->subMinutes(100 - $i)]);

        $this->studio()->has('orders.data', 30)->where('orders.total', 45)->where('orders.last_page', 2)
            ->where('orders.data.0.full_name', 'Client 45');
        $this->studio(['o_page' => 2])->has('orders.data', 15)->where('orders.page', 2);
    }

    public function test_search_finds_by_name_code_and_phone_however_it_is_typed(): void
    {
        $this->order(['full_name' => 'Achieng Otieno', 'phone' => '254712345678']);
        $target = $this->order(['full_name' => 'Kamau']);
        $this->order(['full_name' => 'Someone Else', 'phone' => '254799999999']);

        $this->studio(['o_q' => 'achieng'])->where('orders.total', 1);
        $this->studio(['o_q' => '0712 345 678'])->where('orders.total', 1)->where('orders.data.0.full_name', 'Achieng Otieno');
        $this->studio(['o_q' => $target->cipher])->where('orders.total', 1)->where('orders.data.0.id', $target->id);
        $this->studio(['o_q' => '100%_'])->where('orders.total', 0); // LIKE wildcards are literal
    }

    public function test_filters_and_archive_view(): void
    {
        $this->order(['type' => 'standard']);
        $this->order(['type' => 'Hoodie', 'status' => 'pending']);
        $this->order(['full_name' => 'Archived One'])->delete();

        $this->studio(['o_filter' => 'shop'])->where('orders.total', 1);
        $this->studio(['o_filter' => 'custom'])->where('orders.total', 1);
        $this->studio(['o_archived' => 1])->where('orders.total', 1)->where('orders.data.0.full_name', 'Archived One')->where('orders.archived', true);
    }

    public function test_manager_queue_counts_match_filtered_orders_and_oldest_first(): void
    {
        $old = $this->order(['full_name' => 'Old paid order', 'is_paid' => true, 'status' => OrderStatus::PAID, 'created_at' => now()->subDays(4)]);
        $this->order(['is_paid' => true, 'status' => 'pending']);
        $this->order(['is_paid' => true, 'status' => 'completed']);
        $this->order(['is_paid' => false, 'status' => 'completed']);
        $this->order(['is_paid' => false, 'status' => OrderStatus::AMOUNT_MISMATCH]);
        $this->order(['type' => 'Hoodie', 'status' => 'pending', 'price_quoted' => 0]);
        $this->order(['type' => 'Hoodie', 'status' => 'pending', 'price_quoted' => 1500]);
        $this->order(['is_paid' => true, 'status' => OrderStatus::PAID])->delete();

        $this->studio(['o_queue' => 'fulfilment', 'o_sort' => 'oldest'])
            ->where('workflow.fulfilment', 2)->where('orders.total', 2)
            ->where('orders.data.0.id', $old->id)->where('orders.queue', 'fulfilment');
        $this->studio(['o_queue' => 'dispatch'])->where('workflow.dispatch', 1)->where('orders.total', 1);
        $this->studio(['o_queue' => 'review'])->where('workflow.review', 1)->where('orders.total', 1);
        $this->studio(['o_queue' => 'quotes'])->where('workflow.quotes', 1)->where('orders.total', 1);
        $this->studio(['o_queue' => 'payment'])->where('workflow.payment', 1)->where('orders.total', 1);
    }

    public function test_any_order_can_be_opened_directly_even_if_old_or_archived(): void
    {
        $old = $this->order(['full_name' => 'Old Order', 'created_at' => now()->subYear()]);
        foreach (range(1, 40) as $i) $this->order();
        $old->delete();

        $this->studio(['order' => $old->id])->where('focus_order.id', $old->id)->where('focus_order.full_name', 'Old Order');
        $this->studio()->where('focus_order', null);
    }
}
