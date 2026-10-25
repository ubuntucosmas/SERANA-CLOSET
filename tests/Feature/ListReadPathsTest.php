<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\StockService;
use App\Support\Actor;
use App\Support\OrderStatus;
use App\Support\Role;
use App\Support\StudioStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lists (Shop, Studio, Stock, Sales Desk search) read stock and orders in a
 * few whole-list queries. They must give the same answers as the one-item
 * rules, and their query count must not grow with the catalogue or the
 * order book (500 products once took ~17,000 queries on the Stock screen).
 */
class ListReadPathsTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;
    private Actor $owner;
    private Location $shop;

    protected function setUp(): void
    {
        parent::setUp();
        config(['serana.stock_v2' => 'on']);
        $this->stock = app(StockService::class);
        $this->owner = Actor::user(User::factory()->create(['role' => Role::OWNER]));
        $this->shop = Location::sole();
    }

    private function product(string $type, array $attrs = []): Product
    {
        return Product::create(array_merge(['name' => ucfirst($type) . ' ' . uniqid(), 'slug' => $type . '-' . uniqid(), 'price' => 5000,
            'garment_type' => $type, 'track_stock' => true, 'in_stock' => true], $attrs));
    }

    private function sizeOf(Product $p, string $part, string $size): ProductVariant
    {
        return $p->variants()->where('part', $part)->where('size', $size)->firstOrFail();
    }

    private function hold(ProductVariant $v, int $quantity, bool $lapsed = false): void
    {
        $order = CustomOrder::create(['full_name' => 'Hold', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 5000]);
        $order->stockReservations()->create(['product_id' => $v->product_id, 'product_variant_id' => $v->id, 'location_id' => $this->shop->id,
            'quantity' => $quantity, 'status' => 'held', 'expires_at' => $lapsed ? now()->subMinute() : now()->addMinutes(10)]);
    }

    /** A catalogue with every case: sizes, sets with an uneven part, holds, made to order, nothing on offer. */
    private function catalogue(): array
    {
        $hoodie = $this->product('hoodie');
        $this->stock->receive($this->sizeOf($hoodie, 'single', 'M'), $this->shop, 3, $this->owner, 'In');
        $this->hold($this->sizeOf($hoodie, 'single', 'M'), 1);
        $this->hold($this->sizeOf($hoodie, 'single', 'M'), 2, lapsed: true);

        $set = $this->product('set');
        $this->stock->receive($this->sizeOf($set, 'top', 'M'), $this->shop, 2, $this->owner, 'In');
        $this->stock->receive($this->sizeOf($set, 'bottom', 'M'), $this->shop, 5, $this->owner, 'In');
        $this->stock->receive($this->sizeOf($set, 'bottom', 'L'), $this->shop, 1, $this->owner, 'In');

        $madeToOrder = $this->product('hoodie', ['track_stock' => false]);
        $retired = $this->product('jogger');
        $retired->variants()->update(['active' => false]);

        return [$hoodie, $set, $madeToOrder, $retired];
    }

    public function test_bulk_availability_matches_the_one_product_and_one_size_rules(): void
    {
        $this->catalogue();
        $products = Product::with('category')->get();
        $this->stock->preloadAvailability($products);

        foreach ($products as $product) {
            $this->assertSame($this->stock->productAvailableOnline(Product::find($product->id)), $product->preloadedAvailable, $product->name);
        }
        $this->assertSame([2, 2, null, 0], $products->pluck('preloadedAvailable')->all(), 'hoodie 3−1 held; set limited by its top; made to order; nothing on offer');

        $variants = ProductVariant::with('product')->get();
        $many = $this->stock->availableAtMany($variants, $this->shop);
        foreach ($variants as $variant) {
            $this->assertSame($this->stock->availableAt($variant, $this->shop), $many[$variant->id], $variant->label());
        }
    }

    public function test_the_stock_screen_and_the_shop_do_not_query_per_product(): void
    {
        $queries = function (callable $fn): int {
            $n = 0;
            DB::listen(function () use (&$n) { $n++; });
            $fn();
            DB::getEventDispatcher()->forget(\Illuminate\Database\Events\QueryExecuted::class);

            return $n;
        };
        $this->catalogue();
        $small = [$queries(fn () => StudioStock::forStudio(false)), $queries(fn () => $this->stock->preloadAvailability(Product::all()))];
        foreach (range(1, 8) as $i) $this->catalogue();
        $large = [$queries(fn () => StudioStock::forStudio(false)), $queries(fn () => $this->stock->preloadAvailability(Product::all()))];

        $this->assertSame($small, $large, '4 products and 36 products take the same queries');
    }

    public function test_the_studio_order_list_does_not_query_per_order(): void
    {
        $admin = User::factory()->create(['role' => Role::OWNER]);
        $page = function () use ($admin): int {
            DB::enableQueryLog();
            $this->actingAs($admin)->get(route('studio.index', ['tab' => 'orders']), ['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Admin/StudioDashboard', 'X-Inertia-Partial-Data' => 'orders',
                'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(tap(request(), fn ($r) => $r->setUserResolver(fn () => $admin)))])->assertOk();
            $n = count(DB::getQueryLog());
            DB::flushQueryLog();
            DB::disableQueryLog();

            return $n;
        };
        $order = fn () => CustomOrder::forceCreate(['full_name' => 'Client', 'whatsapp_number' => '254700000000', 'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 1000, 'is_paid' => false]);
        foreach (range(1, 3) as $i) $order();
        $page(); // warm settings caches
        $few = $page();
        foreach (range(1, 20) as $i) $order();

        $this->assertSame($few, $page(), '3 and 23 orders on the page take the same queries');
    }
}
