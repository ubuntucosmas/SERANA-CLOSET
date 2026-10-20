<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use App\Support\Actor;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase D1 step 2 (design §13.1, §13.3): StockService is the only writer of
 * stock levels and movements. Every human movement has an actor and a
 * reason and is audited; a level never goes below zero; availability per
 * size is on hand minus active holds at the locations that fulfil online.
 */
class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;
    private Actor $owner;
    private Location $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stock = app(StockService::class);
        $this->owner = Actor::user(User::factory()->create(['role' => Role::OWNER]));
        $this->shop = Location::sole();
    }

    private function variant(string $size = 'M', array $product = []): ProductVariant
    {
        $p = Product::create(array_merge(['name' => 'Hoodie', 'slug' => 'hoodie-' . uniqid(), 'price' => 5000, 'garment_type' => 'hoodie', 'batch_limit' => 10, 'in_stock' => true], $product));

        return $p->variants()->where('size', $size)->firstOrFail();
    }

    private function onHand(ProductVariant $v, ?Location $at = null): int
    {
        return (int) StockLevel::where('product_variant_id', $v->id)->where('location_id', ($at ?? $this->shop)->id)->value('on_hand');
    }

    public function test_receive_and_damage_move_stock_with_an_actor_and_a_reason(): void
    {
        $v = $this->variant();

        $this->stock->receive($v, $this->shop, 5, $this->owner, 'Batch from the workshop');
        $this->stock->damage($v, $this->shop, 2, $this->owner, 'Torn seam');

        $this->assertSame(3, $this->onHand($v));
        $moves = StockMovement::where('product_variant_id', $v->id)->orderBy('id')->get();
        $this->assertSame([['receive', 5, 'Batch from the workshop'], ['damage', -2, 'Torn seam']], $moves->map(fn ($m) => [$m->type, $m->quantity, $m->reason])->all());
        $this->assertSame(['user', $this->owner->id], [$moves[0]->actor_type, $moves[0]->actor_id]);
        $this->assertSame(2, AuditLog::where('action', 'stock.moved')->count());
    }

    public function test_stock_never_goes_below_zero(): void
    {
        $v = $this->variant();
        $this->stock->receive($v, $this->shop, 1, $this->owner, 'Batch');

        try {
            $this->stock->damage($v, $this->shop, 2, $this->owner, 'Lost');
            $this->fail('Damaged more than was on hand.');
        } catch (\DomainException $e) {
            $this->assertSame('Only 1 of Hoodie (Size M) is on hand at Serana Studio.', $e->getMessage());
        }
        $this->assertSame(1, $this->onHand($v));
        $this->assertSame(1, StockMovement::count());
    }

    public function test_a_count_sets_the_level_with_one_correction_and_stamps_the_count(): void
    {
        $v = $this->variant();
        $this->stock->receive($v, $this->shop, 5, $this->owner, 'Batch');

        $this->stock->count($v, $this->shop, 3, $this->owner, 'Opening stock take');
        $this->assertSame(3, $this->onHand($v));
        $this->assertSame(['count_correction', -2], [StockMovement::latest('id')->value('type'), StockMovement::latest('id')->value('quantity')]);

        // Counting the same number again writes nothing but still records the count.
        $this->travel(1)->hours();
        $this->stock->count($v, $this->shop, 3, $this->owner, 'Recount');
        $this->assertSame(2, StockMovement::count());
        $this->assertTrue(StockLevel::where('product_variant_id', $v->id)->value('counted_at') > now()->subMinute());
    }

    public function test_every_human_movement_needs_a_reason(): void
    {
        $v = $this->variant();
        $this->expectException(\DomainException::class);
        $this->stock->receive($v, $this->shop, 1, $this->owner, '  ');
    }

    public function test_the_level_always_equals_the_sum_of_its_movements(): void
    {
        $v = $this->variant();
        $this->stock->receive($v, $this->shop, 7, $this->owner, 'Batch');
        $this->stock->damage($v, $this->shop, 1, $this->owner, 'Stain');
        $this->stock->count($v, $this->shop, 4, $this->owner, 'Count');

        $this->assertSame((int) StockMovement::where('product_variant_id', $v->id)->sum('quantity'), $this->onHand($v));
        $this->assertSame([], $this->stock->integrityIssues());
    }

    public function test_a_transfer_moves_units_between_locations_as_a_pair(): void
    {
        $v = $this->variant();
        $second = Location::create(['name' => 'Second shop', 'fulfils_online' => false, 'accepts_pickup' => true, 'sort' => 1]);
        $this->stock->receive($v, $this->shop, 4, $this->owner, 'Batch');

        $this->stock->transfer($v, $this->shop, $second, 3, $this->owner, 'Stocking the new shop');

        $this->assertSame([1, 3], [$this->onHand($v), $this->onHand($v, $second)]);
        $pair = StockMovement::whereNotNull('transfer_id')->orderBy('id')->get();
        $this->assertSame([['transfer_out', -3], ['transfer_in', 3]], $pair->map(fn ($m) => [$m->type, $m->quantity])->all());
        $this->assertSame(1, $pair->pluck('transfer_id')->unique()->count());
    }

    public function test_online_availability_is_on_hand_less_active_holds_at_online_locations(): void
    {
        $v = $this->variant();
        $other = $v->product->variants()->where('size', 'L')->sole();
        $popup = Location::create(['name' => 'Pop-up', 'fulfils_online' => false, 'accepts_pickup' => true, 'sort' => 1]);
        $this->stock->receive($v, $this->shop, 3, $this->owner, 'Batch');
        $this->stock->receive($v, $popup, 5, $this->owner, 'Batch');
        $this->stock->receive($other, $this->shop, 2, $this->owner, 'Batch');

        $order = CustomOrder::create(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000]);
        $order->stockReservations()->create(['product_id' => $v->product_id, 'product_variant_id' => $v->id, 'location_id' => $this->shop->id, 'quantity' => 1, 'status' => 'held', 'expires_at' => now()->addMinutes(10)]);
        $order->stockReservations()->create(['product_id' => $v->product_id, 'product_variant_id' => $v->id, 'location_id' => $this->shop->id, 'quantity' => 1, 'status' => 'held', 'expires_at' => now()->subMinute()]); // lapsed

        $this->assertSame(2, $this->stock->availableOnline($v), 'the pop-up does not sell online; the lapsed hold no longer counts');
        $this->assertSame(5, $this->stock->availableAt($v, $popup));
        $this->assertSame(['L' => 2, 'M' => 2, 'S' => 0, 'XL' => 0, 'XXL' => 0], collect($this->stock->sizesOnline($v->product))->mapWithKeys(fn ($s) => [$s['size'] => $s['available']])->sortKeys()->all());
        $this->assertSame(4, $this->stock->productAvailableOnline($v->product));
    }

    public function test_a_count_below_what_is_held_floors_availability_at_zero(): void
    {
        $v = $this->variant();
        $this->stock->receive($v, $this->shop, 2, $this->owner, 'Batch');
        $order = CustomOrder::create(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000]);
        $order->stockReservations()->create(['product_id' => $v->product_id, 'product_variant_id' => $v->id, 'location_id' => $this->shop->id, 'quantity' => 2, 'status' => 'held', 'expires_at' => now()->addMinutes(10)]);

        $this->stock->count($v, $this->shop, 1, $this->owner, 'One was missing');

        $this->assertSame(0, $this->stock->availableOnline($v));
    }

    public function test_a_made_to_order_product_is_always_available(): void
    {
        $v = $this->variant('M', ['batch_limit' => null]);
        $this->assertNull($this->stock->availableOnline($v));
        $this->assertNull($this->stock->productAvailableOnline($v->product));
    }

    public function test_a_set_is_available_as_complete_sets(): void
    {
        $set = Product::create(['name' => 'Linen Set', 'slug' => 'linen-set', 'price' => 9000, 'garment_type' => 'set', 'batch_limit' => 10, 'in_stock' => true]);
        $top = fn ($s) => $set->variants()->where('part', 'top')->where('size', $s)->sole();
        $bottom = fn ($s) => $set->variants()->where('part', 'bottom')->where('size', $s)->sole();
        $this->stock->receive($top('M'), $this->shop, 3, $this->owner, 'Batch');
        $this->stock->receive($top('L'), $this->shop, 1, $this->owner, 'Batch');
        $this->stock->receive($bottom('M'), $this->shop, 2, $this->owner, 'Batch');

        $this->assertSame(2, $this->stock->productAvailableOnline($set), 'four tops but only two bottoms make two sets');
    }
}
