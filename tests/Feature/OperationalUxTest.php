<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CustomOrder;
use App\Models\Lead;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\StockFixtures;
use Tests\TestCase;

class OperationalUxTest extends TestCase
{
    use RefreshDatabase, StockFixtures;

    private function order(array $attributes = []): CustomOrder
    {
        return CustomOrder::create(array_merge(['full_name' => 'Test Client', 'phone' => '254712345678', 'whatsapp_number' => '254712345678', 'type' => 'Hoodie', 'status' => 'pending', 'price_quoted' => 6000], $attributes));
    }

    public function test_bespoke_workspace_and_partial_refresh_keep_only_bespoke_jobs(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $bespoke = $this->order();
        $this->order(['type' => 'standard', 'status' => 'Pending Payment']);
        $this->actingAs($owner)->get('/studio?tab=bespoke')->assertInertia(fn ($p) => $p
            ->where('orders.total', 1)->where('orders.filter', 'bespoke')->where('orders.data.0.id', $bespoke->id));
        $this->get('/studio?tab=bespoke&o_filter=all', ['X-Inertia' => 'true', 'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()), 'X-Inertia-Partial-Component' => 'Admin/StudioDashboard', 'X-Inertia-Partial-Data' => 'orders'])
            ->assertOk()->assertJsonPath('props.orders.total', 1)->assertJsonPath('props.orders.filter', 'bespoke');
    }

    public function test_next_action_explains_deposit_block_and_updates_after_server_records_payment(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['role' => 'owner']);
        $order = $this->order();
        $url = '/studio?tab=orders&order=' . $order->id;
        $this->actingAs($owner)->get($url)->assertInertia(fn ($p) => $p
            ->where('focus_order.next_action.stage', 'designing')
            ->where('focus_order.next_action.blocker', 'Take the deposit (KSh 3,000) before work starts.'));
        app(OrderSettlementService::class)->recordPayment($order, 3000, 'cash', Actor::user($owner));
        $this->get($url)->assertInertia(fn ($p) => $p->where('focus_order.next_action.label', 'Start designing')->where('focus_order.next_action.blocker', null));
        $this->assertSame('pending', $order->fresh()->fulfilment_status, 'Presentation must not progress the order');
    }

    public function test_attention_includes_refund_owed_and_ready_to_ship_excludes_pickup(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['role' => 'owner']);
        $refund = $this->order();
        app(OrderSettlementService::class)->recordPayment($refund, 7000, 'cash', Actor::user($owner));
        $this->actingAs($owner)->get('/studio?tab=orders&o_queue=attention')->assertInertia(fn ($p) => $p->where('orders.total', 1)->where('orders.data.0.next_action.label', 'Review refund'));
        $this->get('/studio?tab=orders&o_queue=dispatch')->assertInertia(fn ($p) => $p->where('orders.total', 0));
        $delivery = $this->order(['type' => 'standard', 'status' => 'Pending Payment', 'fulfilment_method' => 'delivery']);
        $pickup = $this->order(['type' => 'standard', 'status' => 'Pending Payment', 'fulfilment_method' => 'pickup']);
        foreach ([$delivery, $pickup] as $ready) {
            app(OrderSettlementService::class)->recordPayment($ready, 6000, 'cash', Actor::user($owner));
            $ready->refresh()->update(['status' => 'completed']);
        }
        $this->get('/studio?tab=orders&o_queue=dispatch')->assertInertia(fn ($p) => $p->where('orders.total', 1)->where('orders.data.0.id', $delivery->id));

    }

    public function test_subscriber_data_loads_only_in_its_workspace(): void
    {
        Lead::create(['email' => 'private@example.test']);
        $this->actingAs(User::factory()->create(['role' => 'manager']))->get('/studio')->assertInertia(fn ($p) => $p->where('leads', []));
        $this->get('/studio?tab=subscribers')->assertInertia(fn ($p) => $p->has('leads', 1));
    }

    public function test_catalogue_searches_size_partial_sku_category_and_filters_categories(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'pos_pin' => Hash::make('2468')]);
        $this->actingAs($cashier)->postJson(route('pos.unlock'), ['pin' => '2468'])->assertOk();
        $category = Category::create(['name' => 'Outerwear', 'slug' => 'outerwear']);
        $coat = $this->stockedProduct(3, ['name' => 'Linen coat', 'category_id' => $category->id]);
        $this->oneSize($coat)->update(['sku' => 'SRN-LINEN-001']);
        $this->stockedProduct(2, ['name' => 'Other item']);
        foreach (['Outerwear', 'LINEN-00'] as $term) {
            $this->getJson(route('pos.catalogue', ['q' => $term]))->assertOk()->assertJsonCount(1, 'products')->assertJsonPath('products.0.id', $coat->id);
        }
        $this->getJson(route('pos.catalogue', ['category_id' => $category->id, 'q' => 'One size']))->assertOk()->assertJsonCount(1, 'products')->assertJsonPath('products.0.id', $coat->id);
        $this->getJson(route('pos.catalogue', ['q' => 'SRN-LINEN-001']))->assertOk()->assertJsonPath('exact.product_id', $coat->id);
        $this->getJson(route('pos.catalogue', ['q' => '%']))->assertOk()->assertJsonCount(0, 'products');
    }
}
