<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\ActivityLabels;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The first-principles pass: products are set up in one step, and history reads as plain events. */
class FirstPrinciplesPassTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => Role::OWNER]);
    }

    private function create(array $extra = []): \Illuminate\Testing\TestResponse
    {
        $category = Category::firstOrCreate(['name' => 'Sets'], ['slug' => 'sets']);

        return $this->actingAs($this->owner())->post(route('admin.catalog.store_product'), $extra + [
            'category_id' => $category->id, 'name' => 'Linen Two-Piece Set', 'description' => 'Breathable linen.', 'price' => 6000,
            'garment_type' => 'set', 'in_stock' => true,
        ]);
    }

    public function test_a_new_product_gets_only_the_sizes_on_offer_and_is_counted_by_default_with_stock_per_size(): void
    {
        config(['serana.stock_v2' => 'on']);

        $this->create(['sizes' => ['S', ' M ', 'L', ''], 'track_stock' => true])->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'sold out until you count'));

        $product = Product::sole();
        $this->assertTrue($product->track_stock, 'counted, never silently unlimited');
        $this->assertSame(['Top S', 'Top M', 'Top L', 'Bottom S', 'Bottom M', 'Bottom L'],
            $product->variants()->where('active', true)->orderBy('sort')->get()->map->label()->all());
        // Nothing counted yet: the shop can't sell it.
        $this->get(route('shop.show', $product->slug))->assertOk();
        $this->assertSame(0, app(\App\Services\StockService::class)->productAvailableOnline($product));
    }

    /** Both stock models agree whenever a product's stock mode changes, so switching per-size stock off never sells blind. */
    public function test_the_old_model_follows_a_product_becoming_counted_or_made_to_order(): void
    {
        config(['serana.stock_v2' => 'on']);
        $owner = $this->owner();
        $this->create(['garment_type' => 'hoodie', 'sizes' => ['M'], 'track_stock' => false]);
        $product = Product::sole();
        $this->artisan('serana:stock:reconcile')->assertSuccessful();

        $this->actingAs($owner)->post(route('studio.stock.track', $product), ['track_stock' => true, 'reason' => 'Limited batch'])->assertSessionHasNoErrors();
        $this->assertSame(0, $product->fresh()->batch_limit, 'counted but not yet counted: sold out in both models');
        $this->artisan('serana:stock:reconcile')->assertSuccessful();

        $this->actingAs($owner)->post(route('studio.stock.track', $product), ['track_stock' => false, 'reason' => 'Now made to order'])->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->batch_limit, 'made to order: unlimited in both models');
        $this->artisan('serana:stock:reconcile')->assertSuccessful();
    }

    public function test_a_new_counted_product_keeps_both_models_in_step(): void
    {
        config(['serana.stock_v2' => 'on']);
        $this->create(['sizes' => ['S', 'M'], 'track_stock' => true]);

        $this->assertSame(0, Product::sole()->batch_limit);
        $this->artisan('serana:stock:reconcile')->assertSuccessful();
    }

    public function test_made_to_order_is_an_explicit_choice(): void
    {
        config(['serana.stock_v2' => 'on']);

        $this->create(['garment_type' => 'hoodie', 'sizes' => ['One size'], 'track_stock' => false])->assertSessionHasNoErrors();

        $product = Product::sole();
        $this->assertFalse($product->track_stock);
        $this->assertSame([ProductVariant::ONE_SIZE], $product->variants()->pluck('size')->all());
    }

    public function test_sizes_are_validated(): void
    {
        $this->create(['sizes' => ['M', 'm']])->assertSessionHasErrors('sizes.1');
        $this->assertSame(0, Product::count());
    }

    public function test_without_sizes_the_default_list_still_applies(): void
    {
        $this->create(['garment_type' => 'jogger'])->assertSessionHasNoErrors();

        $this->assertSame(['S', 'M', 'L', 'XL', 'XXL'], Product::sole()->variants()->orderBy('sort')->pluck('size')->all());
    }

    /** Every action the app records has words for Activity history, so no raw key reaches staff. */
    public function test_every_recorded_action_has_a_plain_language_label(): void
    {
        $actions = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path())) as $file) {
            if ($file->getExtension() === 'php' && preg_match_all("/Audit::(?:record|changes)\\('([a-z_.]+)'/", file_get_contents($file->getPathname()), $m)) {
                array_push($actions, ...$m[1]);
            }
        }
        $actions = [...array_unique($actions), 'till.payout', 'till.pickup'];
        $raw = array_values(array_filter($actions, fn ($a) => str_contains(ActivityLabels::action($a), '.') || ActivityLabels::action($a) === ucfirst(str_replace(['.', '_'], ' ', $a))));

        $this->assertSame([], $raw, 'actions without a label: ' . implode(', ', $raw));
        $this->assertSame('Received stock', ActivityLabels::action('receive', true));
        $this->assertSame('Sales Desk', ActivityLabels::where('pos.sale_started'));
    }

    public function test_activity_history_names_the_product_and_size_not_a_class_and_id(): void
    {
        config(['serana.stock_v2' => 'on']);
        $owner = $this->owner();
        $this->create(['sizes' => ['M'], 'track_stock' => true]);
        $product = Product::sole();
        $this->actingAs($owner)->post(route('studio.stock.sizes', $product), ['size' => 'L', 'part' => 'top', 'reason' => 'New size'])->assertSessionHasNoErrors();

        // A record about a person (not an order or product) names the person.
        $this->actingAs($owner)->post(route('studio.staff.store'), ['name' => 'Wanjiku Kamau', 'email' => 'wanjiku@serana.test', 'role' => Role::CASHIER, 'reason' => 'New cashier'])->assertSessionHasNoErrors();

        $rows = $this->actingAs($owner)->get(route('studio.index', ['tab' => 'activity']))->assertOk()->viewData('page')['props']['activity']['rows'];
        $labels = collect($rows)->pluck('label')->all();

        $this->assertContains('Created a product', $labels);
        $this->assertContains('Changed the sizes on offer', $labels);
        $this->assertSame('Wanjiku Kamau', collect($rows)->firstWhere('label', 'Added a staff member')['subject'] ?? null);
        foreach ($rows as $row) {
            $this->assertDoesNotMatchRegularExpression('/#\d+$|ProductVariant|TillSession/', (string) $row['subject']);
            $this->assertContains($row['where'], ['Studio', 'Sales Desk']);
        }
    }
}
