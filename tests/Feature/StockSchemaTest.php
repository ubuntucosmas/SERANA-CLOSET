<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\StockBackfill;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase D1 step 1 (design §13.1, §13.5.1): the stock-per-size model exists
 * beside the old one and nothing reads it yet. Every product has a variant
 * per offered size and part; SKU and barcode are generated once per variant;
 * one location is seeded; stock levels can never go negative and stock
 * movements are append-only.
 */
class StockSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $o = []): Product
    {
        return Product::create(array_merge(['name' => 'Linen Hoodie', 'slug' => 'linen-' . uniqid(), 'price' => 5000, 'garment_type' => 'hoodie', 'in_stock' => true], $o));
    }

    private function variants(Product $p): array
    {
        return $p->variants()->orderBy('sort')->get()->map(fn ($v) => "{$v->part}:{$v->size}" . ($v->active ? '' : ' (inactive)'))->all();
    }

    public function test_one_location_is_seeded_for_online_orders_and_pickup(): void
    {
        $location = Location::sole();
        $this->assertSame([true, true, true], [$location->fulfils_online, $location->accepts_pickup, $location->active]);
        $this->assertNotSame('', $location->name);
    }

    public function test_each_product_gets_a_variant_per_offered_size_and_part(): void
    {
        $this->assertSame(['single:S', 'single:M', 'single:L', 'single:XL', 'single:XXL'], $this->variants($this->product()));
        $this->assertSame(['bottom:S', 'bottom:M', 'bottom:L', 'bottom:XL', 'bottom:XXL'], $this->variants($this->product(['garment_type' => 'jogger'])));
        $set = $this->variants($this->product(['garment_type' => 'set']));
        $this->assertSame(['top:S', 'top:M', 'top:L', 'top:XL', 'top:XXL', 'bottom:S', 'bottom:M', 'bottom:L', 'bottom:XL', 'bottom:XXL'], $set, 'a set is stocked as two components (D7 a)');
        $this->assertSame(['single:M', 'single:L'], $this->variants($this->product(['specifications' => ['ui' => ['sizes' => ['M', 'L']]]])));
        $this->assertSame(['single:One size'], $this->variants($this->product(['specifications' => ['sizes' => ['One size']]])));
    }

    public function test_a_product_created_without_a_garment_type_gets_the_variants_the_database_says(): void
    {
        $product = Product::create(['name' => 'No Type', 'slug' => 'no-type', 'price' => 5000, 'in_stock' => true, 'specifications' => ['sizes' => ['M']]]);

        $this->assertSame('set', $product->fresh()->garment_type);
        $this->assertSame(['top:M', 'bottom:M'], $this->variants($product));
    }

    public function test_a_garment_that_changes_type_withdraws_its_old_parts(): void
    {
        $product = $this->product(['garment_type' => 'set', 'specifications' => ['sizes' => ['M']]]);

        $product->update(['garment_type' => 'hoodie']);

        $this->assertEqualsCanonicalizing(['top:M (inactive)', 'bottom:M (inactive)', 'single:M'], $this->variants($product));
    }

    public function test_a_product_with_a_batch_limit_is_tracked_and_one_without_is_made_to_order(): void
    {
        $this->assertTrue($this->product(['batch_limit' => 10])->fresh()->track_stock);
        $this->assertFalse($this->product()->fresh()->track_stock);
    }

    public function test_sku_and_barcode_are_generated_once_and_scan_as_ean13(): void
    {
        $product = $this->product(['garment_type' => 'set']);
        $topM = $product->variants()->where('part', 'top')->where('size', 'M')->sole();

        $this->assertSame(sprintf('SRN-%04d-T-M', $product->id), $topM->sku);
        $this->assertMatchesRegularExpression('/^2\d{12}$/', $topM->barcode, 'GS1 in-store range');
        $this->assertTrue(ProductVariant::validEan13($topM->barcode), 'check digit');
        $this->assertSame(ProductVariant::count(), ProductVariant::distinct()->count('barcode'));
        $this->assertSame(ProductVariant::count(), ProductVariant::distinct()->count('sku'));

        $before = [$topM->sku, $topM->barcode];
        $product->update(['name' => 'Renamed Set']);
        $this->assertSame($before, [$topM->fresh()->sku, $topM->fresh()->barcode], 'never regenerated');
    }

    public function test_the_database_refuses_a_negative_stock_level(): void
    {
        $variant = $this->product(['batch_limit' => 5])->variants()->first();
        $location = Location::sole();
        DB::table('stock_levels')->insert(['product_variant_id' => $variant->id, 'location_id' => $location->id, 'on_hand' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(QueryException::class);
        DB::table('stock_levels')->where('product_variant_id', $variant->id)->update(['on_hand' => -1]);
    }

    public function test_a_new_level_cannot_start_negative_either(): void
    {
        $variant = $this->product(['batch_limit' => 5])->variants()->first();

        $this->expectException(QueryException::class);
        DB::table('stock_levels')->insert(['product_variant_id' => $variant->id, 'location_id' => Location::sole()->id, 'on_hand' => -2, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_stock_movements_are_append_only(): void
    {
        $variant = $this->product(['batch_limit' => 5])->variants()->first();
        $movement = StockMovement::create(['product_variant_id' => $variant->id, 'location_id' => Location::sole()->id, 'type' => StockMovement::RECEIVE,
            'quantity' => 3, 'actor_type' => 'system', 'reason' => 'test', 'occurred_at' => now()]);

        foreach ([fn () => $movement->update(['quantity' => 4]), fn () => $movement->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('A stock movement was changed.');
            } catch (\LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_backfill_adds_sizes_seen_on_past_orders_and_maps_live_holds(): void
    {
        $product = $this->product(['garment_type' => 'set', 'batch_limit' => 5, 'specifications' => ['ui' => ['sizes' => ['M', 'L']]]]);
        $order = CustomOrder::create(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000,
            'items_json' => [['id' => $product->id, 'name' => 'Set', 'price' => 5000, 'quantity' => 1, 'options' => ['top_size' => 'M', 'bottom_size' => 'XS']]]]);
        $order->stockReservations()->create(['product_id' => $product->id, 'quantity' => 1, 'status' => 'held', 'expires_at' => now()->addMinutes(10)]);

        $before = [$product->fresh()->reservedQuantity(), $product->fresh()->availableStock(), Product::withAvailability()->find($product->id)->reserved_quantity];
        $report = app(StockBackfill::class)->run();
        $this->assertSame($before, [$product->fresh()->reservedQuantity(), $product->fresh()->availableStock(), Product::withAvailability()->find($product->id)->reserved_quantity],
            'the old model, still live, sees the same holds: a set is counted once');

        $this->assertContains('bottom:XS (inactive)', $this->variants($product), 'a size sold before but no longer offered');
        $holds = $order->stockReservations()->orderBy('product_variant_id')->get();
        $this->assertSame(
            [[$product->variants()->where('part', 'top')->where('size', 'M')->value('id'), 1], [$product->variants()->where('part', 'bottom')->where('size', 'XS')->value('id'), 1]],
            $holds->map(fn ($h) => [$h->product_variant_id, $h->quantity])->sortBy(0)->values()->all(),
            'a set hold becomes one hold per part'
        );
        $this->assertSame([Location::sole()->id], $holds->pluck('location_id')->unique()->values()->all());
        $this->assertSame(0, $report['unmapped_holds']);

        $again = app(StockBackfill::class)->run();
        $this->assertSame(2, $order->stockReservations()->count(), 'idempotent');
        $this->assertSame(0, $again['variants_created']);
    }

    public function test_a_hold_whose_size_is_unknown_is_reported_not_guessed(): void
    {
        $product = $this->product(['batch_limit' => 5]);
        $order = CustomOrder::create(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000,
            'items_json' => [['id' => $product->id, 'name' => 'Hoodie', 'price' => 5000, 'quantity' => 1]]]);
        $order->stockReservations()->create(['product_id' => $product->id, 'quantity' => 1, 'status' => 'held', 'expires_at' => now()->addMinutes(10)]);

        $this->assertSame(1, app(StockBackfill::class)->run()['unmapped_holds']);
        $this->assertNull($order->stockReservations()->value('product_variant_id'));
    }

    public function test_paying_a_set_whose_hold_was_split_sells_one_set_in_the_old_model(): void
    {
        $product = $this->product(['garment_type' => 'set', 'batch_limit' => 5]);
        $order = CustomOrder::create(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000,
            'items_json' => [['id' => $product->id, 'name' => 'Set', 'price' => 5000, 'quantity' => 1, 'options' => ['top_size' => 'M', 'bottom_size' => 'L']]]]);
        $order->stockReservations()->create(['product_id' => $product->id, 'quantity' => 1, 'status' => 'held', 'expires_at' => now()->addMinutes(10)]);
        app(StockBackfill::class)->run();

        app(\App\Services\OrderSettlementService::class)->recordPayment($order, 5000, 'mpesa_stk', \App\Support\Actor::safaricom(), 'QSET1');

        $this->assertSame([1, 4], [$product->fresh()->batch_sold, $product->fresh()->availableStock()]);
        $this->assertSame(['committed'], $order->stockReservations()->pluck('status')->unique()->values()->all());
    }

    public function test_a_late_set_payment_after_the_split_is_checked_once_against_the_old_limit(): void
    {
        $product = $this->product(['garment_type' => 'set', 'batch_limit' => 1]);
        $order = CustomOrder::create(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000,
            'items_json' => [['id' => $product->id, 'name' => 'Set', 'price' => 5000, 'quantity' => 1, 'options' => ['top_size' => 'M', 'bottom_size' => 'L']]]]);
        $order->stockReservations()->create(['product_id' => $product->id, 'quantity' => 1, 'status' => 'released', 'expires_at' => now()->subMinute()]);
        app(StockBackfill::class)->run();

        // The last set is still free: the lapsed (split) hold is taken once, not refused for counting twice.
        app(\App\Services\OrderSettlementService::class)->recordPayment($order, 5000, 'mpesa_stk', \App\Support\Actor::safaricom(), 'QSET2');

        $this->assertSame(['paid', 1], [$order->fresh()->payment_status, $product->fresh()->batch_sold]);
    }
}
