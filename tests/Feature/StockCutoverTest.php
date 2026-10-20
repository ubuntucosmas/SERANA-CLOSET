<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Services\StockCutover;
use App\Services\StockReconciler;
use App\Services\StockService;
use App\Support\Actor;
use App\Support\Role;
use App\Support\StockMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase D1 step 6 (design §13.2, §13.5.3–6): the switch to stock per size.
 * Reconcile compares the two models and checks the new one; confirm refuses
 * until every tracked size is counted and every live hold has a size,
 * replays the old model's sales made after a count, re-baselines the batch
 * numbers and turns v2 on. Afterwards every stock change keeps the old
 * model's totals equal, so switching back is safe; preflight fails on any
 * mismatch while v2 is on.
 */
class StockCutoverTest extends TestCase
{
    use RefreshDatabase;

    private Actor $owner;
    private Location $shop;

    protected function setUp(): void
    {
        parent::setUp();
        config(['serana.stock_v2' => 'setting']);
        $this->owner = Actor::user(User::factory()->create(['role' => Role::OWNER]));
        $this->shop = Location::sole();
    }

    private function hoodie(?int $limit = 10, int $sold = 0, array $o = []): Product
    {
        return Product::create(array_merge(['name' => 'Linen Hoodie', 'slug' => 'linen-hoodie-' . uniqid(), 'price' => 5000, 'garment_type' => 'hoodie',
            'batch_limit' => $limit, 'batch_sold' => $sold, 'in_stock' => true, 'specifications' => ['sizes' => ['M', 'L']]], $o));
    }

    private function stockTake(Product $p, array $bySize): void
    {
        foreach ($bySize as $key => $n) {
            [$part, $size] = str_contains($key, ':') ? explode(':', $key) : ['single', $key];
            app(StockService::class)->count($p->variants()->where('part', $part)->where('size', $size)->sole(), $this->shop, $n, $this->owner, 'Opening stock take');
        }
    }

    private function order(Product $p, string $size, string $status = 'held'): CustomOrder
    {
        $order = CustomOrder::create(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000,
            'items_json' => [['id' => $p->id, 'name' => $p->name, 'price' => 5000, 'quantity' => 1, 'options' => ['top_size' => $size]]]]);
        $order->stockReservations()->create(['product_id' => $p->id, 'quantity' => 1, 'status' => $status, 'expires_at' => now()->addMinutes(10)]);

        return $order;
    }

    public function test_confirm_is_refused_until_every_tracked_size_is_counted(): void
    {
        $hoodie = $this->hoodie();
        $this->hoodie(null, 0, ['name' => 'Made To Order']); // untracked: never needs a count
        $this->stockTake($hoodie, ['M' => 3]);

        $preview = app(StockCutover::class)->preview();
        $this->assertSame(['Linen Hoodie (Size L)'], $preview['uncounted']);
        try {
            app(StockCutover::class)->confirm($this->owner);
            $this->fail('Switched on with a size uncounted.');
        } catch (\DomainException $e) {
            $this->assertSame('Count every size first: Linen Hoodie (Size L).', $e->getMessage());
        }
        $this->assertFalse(StockMode::v2());
    }

    public function test_confirm_switches_on_and_rebaselines_the_old_model(): void
    {
        $hoodie = $this->hoodie(10, 4);
        $this->stockTake($hoodie, ['M' => 2, 'L' => 1]);

        $result = app(StockCutover::class)->confirm($this->owner);

        $this->assertTrue(StockMode::v2());
        $this->assertSame(3 + 4, $hoodie->fresh()->batch_limit, 'batch_limit = sold + counted, so switching back shows the same 3');
        $this->assertSame([], app(StockReconciler::class)->failures(app(StockReconciler::class)->report()));
        $this->assertSame(0, $result['replayed']);
        $this->assertSame(1, AuditLog::where('action', 'stock.cutover')->count());
        $this->artisan('serana:stock:reconcile')->assertSuccessful();
    }

    public function test_sales_made_by_the_old_model_after_a_count_are_replayed_once(): void
    {
        $hoodie = $this->hoodie(10, 0);
        $before = $this->order($hoodie, 'M');
        app(OrderSettlementService::class)->recordPayment($before, 5000, 'mpesa_stk', Actor::safaricom(), 'QBEFORE');
        $this->travel(1)->minutes();
        $this->stockTake($hoodie, ['M' => 3, 'L' => 2]); // the count already excludes the earlier sale
        $this->travel(1)->minutes();
        $after = $this->order($hoodie, 'M');
        app(OrderSettlementService::class)->recordPayment($after, 5000, 'mpesa_stk', Actor::safaricom(), 'QAFTER'); // old model, after the count

        $this->assertSame(['Linen Hoodie (Size M): order ' . $after->cipher], app(StockCutover::class)->preview()['to_replay']);
        $result = app(StockCutover::class)->confirm($this->owner);

        $this->assertSame(1, $result['replayed']);
        $this->assertSame(2, app(StockService::class)->availableOnline($hoodie->variants()->where('size', 'M')->sole()));
        $this->assertSame(1, StockMovement::where('type', 'sale')->where('custom_order_id', $after->id)->count());
        $this->assertSame(0, StockMovement::where('type', 'sale')->where('custom_order_id', $before->id)->count());
        $this->assertSame([], app(StockReconciler::class)->failures(app(StockReconciler::class)->report()));
    }

    public function test_a_replay_that_would_go_below_zero_stops_the_switch_and_changes_nothing(): void
    {
        $hoodie = $this->hoodie();
        $this->stockTake($hoodie, ['M' => 0, 'L' => 1]);
        $this->travel(1)->minutes();
        app(OrderSettlementService::class)->recordPayment($this->order($hoodie, 'M'), 5000, 'mpesa_stk', Actor::safaricom(), 'QAFTER');

        try {
            app(StockCutover::class)->confirm($this->owner);
            $this->fail('Switched on with a negative replay.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Linen Hoodie (Size M)', $e->getMessage());
            $this->assertStringContainsString('Recount', $e->getMessage());
        }
        $this->assertFalse(StockMode::v2());
        $this->assertSame(0, StockMovement::where('type', 'sale')->count());
        $this->assertSame(10, $hoodie->fresh()->batch_limit);
    }

    public function test_a_live_hold_without_a_readable_size_stops_the_switch(): void
    {
        $hoodie = $this->hoodie();
        $this->stockTake($hoodie, ['M' => 1, 'L' => 1]);
        $order = CustomOrder::create(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000,
            'items_json' => [['id' => $hoodie->id, 'name' => 'Hoodie', 'price' => 5000, 'quantity' => 1]]]);
        $order->stockReservations()->create(['product_id' => $hoodie->id, 'quantity' => 1, 'status' => 'held', 'expires_at' => now()->addMinutes(10)]);

        $this->expectExceptionMessage("hold(s) on order {$order->cipher}");
        app(StockCutover::class)->confirm($this->owner);
    }

    public function test_an_old_released_hold_without_a_size_doesnt_block_the_switch_and_never_sells_blind(): void
    {
        $hoodie = $this->hoodie(10, 0);
        $this->stockTake($hoodie, ['M' => 1, 'L' => 1]);
        $old = CustomOrder::create(['full_name' => 'Old Buyer', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Expired', 'price_quoted' => 5000,
            'items_json' => [['id' => $hoodie->id, 'name' => 'Hoodie', 'price' => 5000, 'quantity' => 1]]]); // no size recorded
        $old->stockReservations()->create(['product_id' => $hoodie->id, 'quantity' => 1, 'status' => 'released', 'expires_at' => now()->subDays(20)]);

        $preview = app(StockCutover::class)->preview();
        $this->assertSame([], $preview['unmapped_holds']);
        $this->assertSame(["Order {$old->cipher}: a released hold with no size (a late payment would wait for the studio)"], $preview['unmapped_released']);
        app(StockCutover::class)->confirm($this->owner);

        // Weeks later its payment arrives anyway: the studio decides, nothing is sold blind.
        $result = app(OrderSettlementService::class)->recordPayment($old->fresh(), 5000, 'mpesa_stk', Actor::safaricom(), 'QVERYLATE');
        $this->assertSame(['Linen Hoodie'], $result['stock_gone']);
        $this->assertSame(0, StockMovement::where('type', 'sale')->count());
        $this->assertSame(2, (int) \App\Models\StockLevel::sum('on_hand'));
    }

    public function test_after_the_switch_every_stock_change_keeps_switching_back_safe(): void
    {
        $hoodie = $this->hoodie(10, 0);
        $set = Product::create(['name' => 'Linen Set', 'slug' => 'linen-set', 'price' => 9000, 'garment_type' => 'set', 'batch_limit' => 5, 'in_stock' => true, 'specifications' => ['sizes' => ['M']]]);
        $this->stockTake($hoodie, ['M' => 2, 'L' => 2]);
        $this->stockTake($set, ['top:M' => 3, 'bottom:M' => 2]);
        app(StockCutover::class)->confirm($this->owner);

        $m = $hoodie->variants()->where('size', 'M')->sole();
        app(StockService::class)->receive($m, $this->shop, 5, $this->owner, 'New batch');
        app(StockService::class)->damage($hoodie->variants()->where('size', 'L')->sole(), $this->shop, 1, $this->owner, 'Torn');
        app(StockService::class)->receive($set->variants()->where('part', 'bottom')->sole(), $this->shop, 4, $this->owner, 'Bottoms arrived');
        $this->postJson(route('checkout.process'), ['full_name' => 'B', 'email' => 'b@example.com', 'address' => '1 St', 'city' => 'Nairobi', 'phone' => '0712345678',
            'items' => [['id' => $hoodie->id, 'quantity' => 1, 'options' => ['top_size' => 'M']], ['id' => $set->id, 'quantity' => 1, 'options' => ['top_size' => 'M', 'bottom_size' => 'M']]]])->assertOk();
        app(OrderSettlementService::class)->recordPayment(CustomOrder::latest('id')->first(), null, 'mpesa_stk', Actor::safaricom(), 'QV2SALE');

        $this->assertSame([], app(StockReconciler::class)->failures(app(StockReconciler::class)->report()));
        $newHoodie = $hoodie->fresh()->available_stock;
        $newSet = $set->fresh()->available_stock;

        app(StockCutover::class)->rollback($this->owner, 'Testing the way back');
        $this->assertFalse(StockMode::v2());
        $this->assertSame([$newHoodie, $newSet], [$hoodie->fresh()->available_stock, $set->fresh()->available_stock], 'the old model shows the same totals');
        $this->assertSame([7, 2], [$newHoodie, $newSet]);
    }

    public function test_reconcile_fails_on_a_level_that_no_longer_matches_its_movements(): void
    {
        $hoodie = $this->hoodie();
        $this->stockTake($hoodie, ['M' => 2, 'L' => 0]);
        app(StockCutover::class)->confirm($this->owner);
        DB::table('stock_levels')->where('product_variant_id', $hoodie->variants()->where('size', 'M')->value('id'))->update(['on_hand' => 5]);

        $this->artisan('serana:stock:reconcile')->expectsOutputToContain('movements add up to 2')->assertFailed();
    }

    public function test_preflight_fails_when_v2_is_on_and_the_models_disagree(): void
    {
        $hoodie = $this->hoodie();
        $this->stockTake($hoodie, ['M' => 2, 'L' => 0]);
        app(StockCutover::class)->confirm($this->owner);
        DB::table('products')->where('id', $hoodie->id)->update(['batch_sold' => 9]); // the old model drifted

        $this->artisan('serana:preflight')->expectsOutputToContain('serana:stock:reconcile finds')->assertFailed();
    }

    public function test_the_command_previews_without_changing_anything(): void
    {
        $hoodie = $this->hoodie();
        $this->stockTake($hoodie, ['M' => 1]);

        $this->artisan('serana:stock:cutover')->expectsOutputToContain('Linen Hoodie (Size L)')->assertFailed();
        $this->assertFalse(StockMode::v2());
    }
}
