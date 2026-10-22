<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\PaymentAttempt;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Services\ShopDeskService;
use App\Support\Actor;
use App\Support\Audit;
use App\Support\WhatsAppOrderMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\StockFixtures;
use Tests\TestCase;

class StudioWorkflowCompletionTest extends TestCase
{
    use RefreshDatabase, StockFixtures, \Tests\Concerns\DeliveryFixtures;

    private function operator(): User
    {
        $user = User::factory()->create(['role' => 'manager', 'pos_pin' => Hash::make('2468')]);
        $this->actingAs($user)->postJson(route('pos.unlock'), ['pin' => '2468'])->assertOk();
        $this->postJson(route('pos.till.open'), ['float' => 0, 'register' => 'Till 1'])->assertOk();
        return $user;
    }

    private function pickupPayload(): array
    {
        Location::firstOrFail()->update(['address' => 'Shop 4, Nairobi']);
        $product = $this->stockedProduct(5);
        return ['full_name' => 'Pickup Buyer', 'email' => 'pickup@example.test', 'phone' => '0712345678',
            // Phase D2: pickup is a delivery option at the shop.
            'delivery_option_id' => \App\Models\DeliveryOption::where('type', 'pickup')->value('id'),
            'items' => [['id' => $product->id, 'quantity' => 1, 'options' => ['top_size' => 'One size']]],
        ];
    }

    public function test_pickup_snapshots_shop_without_address_and_remains_visible_everywhere(): void
    {
        Mail::fake();
        $shop = Location::firstOrFail(); $shop->update(['name' => 'Collection Shop', 'address' => 'Shop 4, Nairobi']);
        $this->postJson(route('checkout.process'), $this->pickupPayload())->assertOk()->assertJsonPath('handover.method', 'pickup');
        $order = CustomOrder::sole();
        $this->assertNull($order->address);
        $this->assertSame(5000, $order->chargeable_total);
        $shop->update(['name' => 'Changed later']);
        $this->assertSame('Collection Shop', $order->handoverDetails()['location']);
        $this->assertStringContainsString('Collection Shop', WhatsAppOrderMessage::text($order));
        $this->assertStringNotContainsString('*Deliver to*', WhatsAppOrderMessage::text($order));
        $this->postJson(route('track.lookup'), ['reference' => $order->cipher, 'phone' => '0712345678'])->assertOk()->assertJsonPath('order.handover.location', 'Collection Shop');
        $this->assertStringContainsString('Collection Shop', view('receipts.luxury', \App\Mail\OrderReceiptMail::viewData($order, null))->render());
    }

    public function test_pickup_rejects_closed_shop_and_delivery_still_requires_address(): void
    {
        $payload = $this->pickupPayload();
        Location::firstOrFail()->update(['accepts_pickup' => false]);
        $this->postJson(route('checkout.process'), $payload)->assertUnprocessable()->assertJsonValidationErrors('delivery_option_id');
        $payload['delivery_option_id'] = $this->freeDelivery();
        $this->postJson(route('checkout.process'), $payload)->assertUnprocessable()->assertJsonValidationErrors(['address', 'city']);
        $this->assertSame(0, CustomOrder::count());
    }

    public function test_recovered_custom_order_uses_live_balance_and_pending_prompt_without_recreating_order(): void
    {
        Mail::fake(); $operator = $this->operator();
        $order = CustomOrder::create(['full_name' => 'Recover Client', 'phone' => '254712345678', 'whatsapp_number' => '254712345678', 'type' => 'Hoodie', 'status' => 'pending', 'price_quoted' => 8000]);
        $this->getJson(route('pos.orders.search', ['q' => 'Recover']))->assertOk()->assertJsonPath('orders.0.reference', $order->cipher);
        $this->getJson(route('pos.orders.show', $order))->assertOk()->assertJsonPath('order.balance_due', 8000)->assertJsonPath('order.deposit_due', 4000);
        $this->postJson(route('pos.sales.tender', $order), ['method' => 'cash', 'amount' => 3000, 'tendered' => 3000])->assertOk();
        $this->getJson(route('pos.orders.show', $order))->assertOk()->assertJsonPath('order.balance_due', 5000)->assertJsonPath('order.deposit_due', 1000);
        PaymentAttempt::create(['custom_order_id' => $order->id, 'checkout_request_id' => 'recover-pending', 'merchant_request_id' => 'recover-merchant', 'phone' => '254712345678', 'amount' => 1000, 'status' => 'pending']);
        $this->getJson(route('pos.orders.show', $order))->assertOk()->assertJsonPath('order.prompt.status', 'pending');
        $this->assertSame(1, CustomOrder::count());
        $this->postJson(route('pos.lock'))->assertOk();
        $this->getJson(route('pos.orders.search', ['q' => 'Recover']))->assertStatus(423);
        $this->getJson(route('pos.orders.show', $order))->assertStatus(423);
    }

    public function test_pickup_needs_ready_state_correct_shop_and_phone_before_handover(): void
    {
        Mail::fake(); $operator = $this->operator();
        $order = CustomOrder::create(['full_name' => 'Collector', 'phone' => '254712345678', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Paid', 'price_quoted' => 1000, 'is_paid' => true,
            'fulfilment_method' => 'pickup', 'pickup_location_id' => Location::firstOrFail()->id, 'fulfilment_snapshot' => ['name' => 'Shop']]);
        $this->postJson(route('pos.collect.store', $order), ['phone' => '0712345678'])->assertUnprocessable();
        $this->put(route('studio.update', $order), ['status' => 'shipped'])->assertSessionHasErrors('status');
        $this->put(route('studio.update', $order), ['status' => 'ready_for_pickup'])->assertSessionDoesntHaveErrors();
        $this->postJson(route('pos.collect.store', $order), ['phone' => '0799999999'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson(route('pos.collect.store', $order), ['phone' => '0712345678', 'note' => 'Buyer verified at shop'])->assertOk()->assertJsonPath('order.fulfilment', 'collected');
        $this->postJson(route('pos.collect.store', $order), ['phone' => '0712345678'])->assertUnprocessable();
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.collected', 'actor_id' => $operator->id, 'reason' => 'Buyer verified at shop']);
    }

    public function test_manager_can_investigate_actions_without_receiving_credentials(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Audit::record('staff.role_changed', $manager, ['role' => 'cashier', 'password' => 'secret-before'], ['role' => 'manager', 'password' => 'secret-after'], 'Promotion', Actor::user($manager));
        $response = $this->actingAs($manager)->get('/studio?tab=activity&h_q=Promotion');
        $response->assertOk();
        $activity = $response->viewData('page')['props']['activity'];
        $this->assertSame(1, $activity['total']);
        $this->assertSame('Promotion', $activity['rows'][0]['reason']);
        $this->assertSame('role', $activity['rows'][0]['changes'][0]['field']);
        $this->assertStringNotContainsString('secret-', json_encode($activity));
        $client = User::factory()->create(['role' => 'client']);
        $this->actingAs($client)->get('/studio?tab=activity')->assertRedirect('/');
    }

    public function test_stock_investigation_reaches_beyond_the_product_history_limit(): void
    {
        $manager = User::factory()->create(['role' => 'manager']); $product = $this->stockedProduct(1);
        $variant = $this->oneSize($product);
        for ($i = 0; $i < 51; $i++) StockMovement::create(['product_variant_id' => $variant->id, 'location_id' => Location::firstOrFail()->id,
            'type' => 'count_correction', 'quantity' => 0, 'actor_type' => 'user', 'actor_id' => $manager->id, 'reason' => 'Review-count', 'occurred_at' => now()]);
        $response = $this->actingAs($manager)->get('/studio?tab=activity&h_type=stock&h_q=Review-count&h_page=2');
        $response->assertOk(); $activity = $response->viewData('page')['props']['activity'];
        $this->assertSame(51, $activity['total']); $this->assertCount(21, $activity['rows']);
        $this->assertSame($manager->name, $activity['rows'][0]['actor']);
    }

    public function test_offered_swap_sizes_include_availability_and_are_not_available_to_cashiers(): void
    {
        config(['serana.stock_v2' => 'on']);
        $manager = User::factory()->create(['role' => 'manager']); $product = $this->stockedProduct(3);
        $this->actingAs($manager)->getJson(route('studio.stock.options', $product))->assertOk()->assertJsonPath('sizes.0.size', 'One size')->assertJsonPath('sizes.0.available', 3);
        $cashier = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($cashier)->getJson(route('studio.stock.options', $product))->assertRedirect(route('pos.index'));
    }
    public function test_pickup_payment_does_not_silently_move_goods_to_another_shop(): void
    {
        Mail::fake(); config(['serana.stock_v2' => 'on']);
        $payload = $this->pickupPayload();
        $this->postJson(route('checkout.process'), $payload)->assertOk();
        $order = CustomOrder::sole();
        $product = \App\Models\Product::findOrFail($payload['items'][0]['id']);
        $order->releaseReservations();
        $this->setOnHand($product, 0);
        $elsewhere = Location::create(['name' => 'Another shop', 'active' => true, 'fulfils_online' => true, 'accepts_pickup' => true, 'sort' => 1]);
        app(\App\Services\StockService::class)->count($this->oneSize($product), $elsewhere, 5, Actor::system(), 'Other shop count');
        app(OrderSettlementService::class)->markPaid($order, 'PICKUP-ONLY');
        $this->assertSame('needs_resolution', $order->fresh()->resolution);
        $this->assertSame(5, app(\App\Services\StockService::class)->availableAt($this->oneSize($product), $elsewhere));
    }

    public function test_pickup_cannot_be_handed_over_at_the_wrong_shop(): void
    {
        $this->operator();
        $other = Location::create(['name' => 'Other collection shop', 'active' => true, 'accepts_pickup' => true, 'fulfils_online' => true, 'sort' => 1]);
        $order = CustomOrder::create(['full_name' => 'Collector', 'phone' => '254712345678', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'ready_for_pickup', 'is_paid' => true, 'price_quoted' => 1000,
            'fulfilment_method' => 'pickup', 'pickup_location_id' => $other->id]);
        $this->postJson(route('pos.collect.store', $order), ['phone' => '0712345678'])->assertUnprocessable()->assertJsonValidationErrors('order');
        $this->assertNotSame('collected', $order->fresh()->fulfilment_status);
    }

    public function test_till_history_filters_dates_and_prints_a_retained_session(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $till = \App\Models\TillSession::create(['location_id' => Location::firstOrFail()->id, 'register' => 'Test register', 'open_marker' => null,
            'status' => 'closed', 'opened_by' => $manager->id, 'closed_by' => $manager->id, 'opened_at' => now()->subDays(5), 'closed_at' => now()->subDays(5)->addHour(), 'opening_float' => 100, 'counted_cash' => 100, 'expected_cash' => 100, 'variance' => 0]);
        $response = $this->actingAs($manager)->get('/studio?tab=tills&till_q=Test&till_to=' . now()->subDays(4)->format('Y-m-d'));
        $response->assertOk(); $this->assertSame(1, $response->viewData('page')['props']['tills']['total']);
        $response = $this->get('/studio?tab=tills&till_from=' . now()->subDay()->format('Y-m-d'));
        $this->assertSame(0, $response->viewData('page')['props']['tills']['total']);
        $this->get(route('studio.till.report', $till))->assertOk()->assertSee('Closing report')->assertSee($manager->name);
        $cashier = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($cashier)->get(route('studio.till.report', $till))->assertRedirect(route('pos.index'));
    }

    public function test_owner_configures_collection_address_before_pickup_is_offered(): void
    {
        $location = Location::firstOrFail(); $location->update(['address' => null]);
        $page = $this->get(route('checkout'))->assertOk();
        $this->assertCount(0, collect($page->viewData('page')['props']['delivery_options'])->where('type', 'pickup'));
        $manager = User::factory()->create(['role' => 'manager']);
        $data = ['name' => 'Main shop', 'address' => 'Building A, shop 4, Nairobi', 'accepts_pickup' => true, 'reason' => 'Enable customer collection'];
        $this->actingAs($manager)->put(route('studio.handover.update', $location), $data)->assertForbidden();
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->put(route('studio.handover.update', $location), $data)->assertSessionDoesntHaveErrors();
        $this->assertSame($data['address'], $location->fresh()->address);
        $page = $this->get(route('checkout'))->assertOk();
        $this->assertCount(1, collect($page->viewData('page')['props']['delivery_options'])->where('type', 'pickup'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'handover.shop_updated', 'reason' => $data['reason']]);
    }

}
