<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Role;
use App\Support\StockMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase D1 step 7 (design §13.5.2, P4): Studio → Stock. Owner and manager
 * count, receive and write off stock per size, each with a reason; they see
 * on hand, held and available per size and every movement with who and why;
 * they choose which sizes a product offers and whether it is made to order.
 * Only the owner switches stock per size on or off.
 */
class StudioStockTest extends TestCase
{
    use RefreshDatabase;

    private Product $hoodie;

    protected function setUp(): void
    {
        parent::setUp();
        config(['serana.stock_v2' => 'setting']);
        $this->hoodie = Product::create(['name' => 'Linen Hoodie', 'slug' => 'linen-hoodie', 'price' => 5000, 'garment_type' => 'hoodie', 'batch_limit' => 10, 'in_stock' => true,
            'specifications' => ['sizes' => ['M', 'L']]]);
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role' => $role, 'name' => ucfirst($role)]);
    }

    private function variantOf(string $size)
    {
        return $this->hoodie->variants()->where('size', $size)->sole();
    }

    private function move(User $as, string $action, string $size, int $quantity, string $reason = 'Stock take')
    {
        return $this->actingAs($as)->post(route('studio.stock.move'), ['variant_id' => $this->variantOf($size)->id, 'action' => $action, 'quantity' => $quantity, 'reason' => $reason]);
    }

    private function stockProp(User $as): array
    {
        return $this->actingAs($as)->get(route('studio.index', ['tab' => 'stock']))->viewData('page')['props']['stock'];
    }

    public function test_a_manager_counts_receives_and_writes_off_with_a_reason(): void
    {
        $manager = $this->as(Role::MANAGER);

        $this->move($manager, 'count', 'M', 4, 'Opening stock take')->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->move($manager, 'receive', 'M', 2, 'From the workshop')->assertSessionHasNoErrors();
        $this->move($manager, 'damage', 'M', 1, 'Torn seam')->assertSessionHasNoErrors();

        $this->assertSame([['count_correction', 4], ['receive', 2], ['damage', -1]],
            StockMovement::orderBy('id')->get()->map(fn ($m) => [$m->type, $m->quantity])->all());
        $this->assertSame([$manager->id], StockMovement::pluck('actor_id')->unique()->values()->all());
        $this->assertSame(3, AuditLog::where('action', 'like', 'stock.%')->count());
    }

    public function test_every_stock_action_is_validated(): void
    {
        $owner = $this->as(Role::OWNER);
        $this->move($owner, 'receive', 'M', 1, '')->assertSessionHasErrors('reason');
        $this->move($owner, 'receive', 'M', 0)->assertSessionHasErrors('quantity');
        $this->move($owner, 'count', 'M', -1)->assertSessionHasErrors('quantity');
        $this->move($owner, 'damage', 'M', 1, 'Lost')->assertSessionHasErrors(['quantity' => 'Only 0 of Linen Hoodie (Size M) is on hand at Serana Studio.']);
        $this->actingAs($owner)->post(route('studio.stock.move'), ['variant_id' => $this->variantOf('M')->id, 'action' => 'sale', 'quantity' => 1, 'reason' => 'x'])->assertSessionHasErrors('action');
        $this->assertSame(0, StockMovement::count());
    }

    public function test_only_owner_and_manager_touch_stock(): void
    {
        $this->move($this->as(Role::CASHIER), 'receive', 'M', 5)->assertRedirect('/');
        $this->assertSame(0, StockMovement::count());
    }

    public function test_the_screen_shows_each_size_and_its_history(): void
    {
        $owner = $this->as(Role::OWNER);
        $this->move($owner, 'count', 'M', 3);
        $this->move($owner, 'count', 'L', 1);
        $order = CustomOrder::create(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000]);
        $order->stockReservations()->create(['product_id' => $this->hoodie->id, 'product_variant_id' => $this->variantOf('M')->id, 'location_id' => Location::sole()->id,
            'quantity' => 1, 'status' => 'held', 'expires_at' => now()->addMinutes(10)]);

        $stock = $this->stockProp($owner);
        $product = collect($stock['products'])->firstWhere('id', $this->hoodie->id);
        $this->assertTrue($product['track_stock']);
        $this->assertSame([['M', 3, 1, 2, true], ['L', 1, 0, 1, true]],
            collect($product['sizes'])->map(fn ($s) => [$s['size'], $s['on_hand'], $s['held'], $s['available'], $s['counted_at'] !== null])->all());
        $this->assertSame($this->variantOf('M')->sku, $product['sizes'][0]['sku']);
        $this->assertSame([['count_correction', 'Size L', 1, 'Owner', 'Stock take'], ['count_correction', 'Size M', 3, 'Owner', 'Stock take']],
            collect($product['history'])->map(fn ($m) => [$m['type'], $m['size'], $m['quantity'], $m['by'], $m['reason']])->all(), 'newest first');
        $this->assertSame(['v2' => false, 'uncounted' => 0], ['v2' => $stock['mode']['v2'], 'uncounted' => count($stock['mode']['preview']['uncounted'])]);
    }

    public function test_sizes_on_offer_can_be_added_and_withdrawn(): void
    {
        $manager = $this->as(Role::MANAGER);

        $this->actingAs($manager)->post(route('studio.stock.sizes', $this->hoodie), ['size' => 'XS', 'reason' => 'New petite cut'])->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('studio.stock.sizes', $this->hoodie), ['size' => 'L', 'active' => false, 'reason' => 'Discontinued'])->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('studio.stock.sizes', $this->hoodie), ['size' => 'M', 'reason' => 'dup'])->assertSessionHasErrors(['size' => 'Linen Hoodie already offers Size M.']);

        $sizes = $this->hoodie->variants()->orderBy('sort')->get()->map(fn ($v) => "{$v->size}" . ($v->active ? '' : ' (off)'))->all();
        $this->assertSame(['M', 'L (off)', 'XS'], $sizes);
        $this->assertSame(2, AuditLog::where('action', 'stock.sizes')->count());
    }

    public function test_a_product_is_made_to_order_or_stocked_with_a_reason(): void
    {
        $owner = $this->as(Role::OWNER);

        $this->actingAs($owner)->post(route('studio.stock.track', $this->hoodie), ['track_stock' => false, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($owner)->post(route('studio.stock.track', $this->hoodie), ['track_stock' => false, 'reason' => 'We make these to order now'])->assertSessionHasNoErrors();

        $this->assertFalse($this->hoodie->fresh()->track_stock);
        $this->assertSame('We make these to order now', AuditLog::where('action', 'stock.tracking')->sole()->reason);
    }

    public function test_only_the_owner_switches_stock_per_size_on_and_off(): void
    {
        $owner = $this->as(Role::OWNER);
        $this->move($owner, 'count', 'M', 2);
        $this->move($owner, 'count', 'L', 1);

        $this->actingAs($this->as(Role::MANAGER))->post(route('studio.stock.cutover'))->assertForbidden();
        $this->actingAs($owner)->post(route('studio.stock.cutover'))->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertTrue(StockMode::v2());

        $this->actingAs($owner)->post(route('studio.stock.rollback'), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($owner)->post(route('studio.stock.rollback'), ['reason' => 'Back to batch numbers for the sale weekend'])->assertSessionHasNoErrors();
        $this->assertFalse(StockMode::v2());
    }

    public function test_a_refused_switch_says_why(): void
    {
        $owner = $this->as(Role::OWNER);
        $this->move($owner, 'count', 'M', 2);

        $this->actingAs($owner)->post(route('studio.stock.cutover'))->assertSessionHasErrors(['cutover' => 'Count every size first: Linen Hoodie (Size L).']);
        $this->assertFalse(StockMode::v2());
    }

    public function test_with_stock_per_size_on_the_product_form_no_longer_sets_batch_numbers(): void
    {
        config(['serana.stock_v2' => 'on']);
        $owner = $this->as(Role::OWNER);

        $category = \App\Models\Category::create(['name' => 'Tops', 'slug' => 'tops']);
        $this->actingAs($owner)->put(route('admin.catalog.update_product', $this->hoodie), ['category_id' => $category->id, 'name' => 'Linen Hoodie', 'description' => 'x', 'price' => 5000, 'batch_limit' => 99])
            ->assertSessionHasNoErrors();

        $this->assertSame(10, $this->hoodie->fresh()->batch_limit, 'stock is managed in Studio → Stock');
    }
}
