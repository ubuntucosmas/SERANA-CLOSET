<?php

namespace Tests\Browser;

use App\Models\CustomOrder;
use App\Models\Lead;
use App\Models\Location;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\StockFixtures;
use Tests\TestCase;

/** Explicitly invoked preview fixture export; never uses the store database. */
class OperationalPreviewTest extends TestCase
{
    use RefreshDatabase, StockFixtures;

    public function test_export_preview_from_isolated_database(): void
    {
        Mail::fake();
        $dir = storage_path('app/ux-preview');
        if (! is_dir($dir)) mkdir($dir, 0755, true);
        $owner = User::factory()->create(['role' => 'owner', 'name' => 'Preview Owner', 'pos_pin' => Hash::make('2468')]);
        $product = $this->stockedProduct(2, ['name' => 'Linen Studio Hoodie', 'price' => 5000]);
        $set = \App\Models\Product::create(['name' => 'Linen Two-Piece Set', 'slug' => 'linen-two-piece-set', 'price' => 6000, 'garment_type' => 'set', 'batch_limit' => 8, 'in_stock' => true, 'specifications' => ['sizes' => ['M', 'L']]]);
        foreach ($set->variants as $variant) app(\App\Services\StockService::class)->count($variant, Location::query()->online()->first(), 4, Actor::user($owner), 'Opening count');
        $order = CustomOrder::create(['full_name' => 'Amina Preview', 'whatsapp_number' => '254712345678', 'phone' => '254712345678', 'type' => 'Hoodie', 'status' => 'pending', 'price_quoted' => 6000,
            'items_json' => ['occasion' => 'Wedding', 'deadline' => '2026-11-15', 'notes' => 'Relaxed fit in linen.']]);
        app(OrderSettlementService::class)->recordPayment($order, 7000, 'cash', Actor::user($owner));
        Lead::create(['email' => 'preview@example.test']);
        $manager = User::factory()->create(['role' => 'manager', 'name' => 'Preview Manager']);
        $cashier = User::factory()->create(['role' => 'cashier', 'name' => 'Preview Cashier', 'pos_pin' => Hash::make('1357')]);
        \App\Support\Audit::record('staff.added', $cashier, [], ['role' => 'cashier', 'email' => $cashier->email], 'New shop cashier', Actor::user($owner));
        \App\Models\ThemeSetting::set(\App\Support\StockMode::SETTING, '1');
        $this->actingAs($owner);
        foreach (['home', 'orders', 'bespoke', 'refunds', 'products', 'stock', 'staff', 'activity', 'settings', 'tills'] as $tab) {
            $response = $this->get('/studio?tab=' . $tab . ($tab === 'orders' ? '&order=' . $order->id : ''));
            $response->assertOk();
            file_put_contents($dir . '/' . $tab . '.html', $response->getContent());
            file_put_contents($dir . '/' . $tab . '.json', json_encode($response->viewData('page')));
        }
        file_put_contents($dir . '/staff-history.json', $this->getJson(route('studio.staff.history', $cashier))->assertOk()->getContent());
        $this->postJson(route('pos.unlock'), ['pin' => '2468'])->assertOk();
        file_put_contents($dir . '/pos-closed.html', $this->get('/pos')->getContent());
        $opened = $this->postJson(route('pos.till.open'), ['register' => 'Till 1', 'float' => 1000])->assertOk();
        file_put_contents($dir . '/opened.json', $opened->getContent());
        file_put_contents($dir . '/pos.html', $this->get('/pos')->getContent());
        file_put_contents($dir . '/catalogue.json', $this->getJson(route('pos.catalogue'))->getContent());
        file_put_contents($dir . '/report.json', $this->getJson(route('pos.till.report'))->getContent());
        $sale = $this->postJson(route('pos.sales.store'), ['items' => [['id' => $product->id, 'quantity' => 1, 'options' => ['top_size' => 'One size']]]])->assertOk();
        file_put_contents($dir . '/sale.json', $sale->getContent());
        file_put_contents($dir . '/paid.json', $this->postJson(route('pos.sales.tender', $sale->json('sale.id')), ['method' => 'cash', 'amount' => 5000, 'tendered' => 6000])->assertOk()->getContent());
        $pickup = CustomOrder::create(['full_name' => 'Pickup Preview', 'phone' => '254712345678', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 2000,
            'fulfilment_method' => 'pickup', 'pickup_location_id' => Location::query()->online()->value('id')]);
        app(OrderSettlementService::class)->recordPayment($pickup, 2000, 'cash', Actor::user($owner));
        $pickup->refresh()->update(['status' => 'ready_for_pickup']);
        file_put_contents($dir . '/pickup.json', $this->getJson(route('pos.orders.show', $pickup))->assertOk()->getContent());
        file_put_contents($dir . '/collected.json', $this->postJson(route('pos.collect.store', $pickup), ['phone' => '0712345678'])->assertOk()->getContent());
        file_put_contents($dir . '/closed.json', $this->postJson(route('pos.till.close'), ['counted' => 6000])->assertOk()->getContent());
        file_put_contents($dir . '/manager.html', $this->actingAs($manager)->get('/studio')->getContent());
        $this->actingAs($cashier)->postJson(route('pos.unlock'), ['pin' => '1357'])->assertOk();
        file_put_contents($dir . '/cashier.html', $this->get('/pos')->getContent());
        file_put_contents($dir . '/website-cashier.html', $this->get('/')->assertOk()->getContent());
        $this->actingAs(User::factory()->create(['role' => 'client']));
        file_put_contents($dir . '/website-client.html', $this->get('/')->assertOk()->getContent());
        $this->actingAs($owner)->post(route('studio.staff.reset_password', $cashier), ['password' => 'preview-reset-4821', 'password_confirmation' => 'preview-reset-4821', 'reason' => 'Forgot password'])->assertSessionHasNoErrors();
        $reset = $this->get('/studio?tab=staff'); $reset->assertOk();
        file_put_contents($dir . '/staff-reset.json', json_encode($reset->viewData('page')));

    }
}
