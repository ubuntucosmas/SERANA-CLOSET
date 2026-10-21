<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\Product;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\StockService;
use App\Support\Actor;
use App\Support\Role;
use App\Support\StockMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase D1 step 3 (design §13.5): one switch decides which stock model the
 * storefront sells from. STOCK_V2=on/off in .env forces it; otherwise the
 * Studio setting decides (the cutover turns it on). With it on, product
 * payloads carry per-size availability from stock levels; with it off,
 * nothing changes.
 */
class StockModeTest extends TestCase
{
    use RefreshDatabase;

    private function hoodie(array $o = []): Product
    {
        return Product::create(array_merge(['name' => 'Linen Hoodie', 'slug' => 'linen-hoodie-' . uniqid(), 'price' => 5000, 'garment_type' => 'hoodie',
            'batch_limit' => 10, 'batch_sold' => 0, 'in_stock' => true, 'image_path' => 'products/x.webp'], $o));
    }

    private function stock(Product $p, array $bySize): void
    {
        $owner = Actor::user(User::factory()->create(['role' => Role::OWNER]));
        foreach ($bySize as $size => $n) {
            app(StockService::class)->receive($p->variants()->where('size', $size)->sole(), Location::sole(), $n, $owner, 'Batch');
        }
    }

    public function test_the_switch_follows_env_then_the_studio_setting(): void
    {
        config(['serana.stock_v2' => 'setting']);
        $this->assertFalse(StockMode::v2(), 'off until the cutover turns it on');
        ThemeSetting::set(StockMode::SETTING, '1');
        $this->assertTrue(StockMode::v2());

        config(['serana.stock_v2' => 'off']);
        $this->assertFalse(StockMode::v2(), '.env can force it off');
        config(['serana.stock_v2' => 'on']);
        ThemeSetting::set(StockMode::SETTING, '0');
        $this->assertTrue(StockMode::v2(), '.env can force it on (CI runs the suite both ways)');
    }

    public function test_with_v2_on_the_shop_reads_per_size_stock(): void
    {
        config(['serana.stock_v2' => 'on']);
        $hoodie = $this->hoodie();
        $this->stock($hoodie, ['M' => 2, 'L' => 1]);
        $gone = $this->hoodie(['name' => 'Gone Hoodie']);

        $shop = collect($this->get(route('shop'))->viewData('page')['props']['products'])->keyBy('name');
        $this->assertSame([3, 0], [$shop['Linen Hoodie']['available_stock'], $shop['Gone Hoodie']['available_stock']]);

        $detail = $this->get(route('shop.show', $hoodie))->viewData('page')['props'];
        $this->assertSame(3, $detail['product']['available_stock']);
        $this->assertSame(['S' => 0, 'M' => 2, 'L' => 1, 'XL' => 0, 'XXL' => 0], collect($detail['sizes'])->mapWithKeys(fn ($s) => [$s['size'] => $s['available']])->all());
        $this->assertSame('single', $detail['sizes'][0]['part']);

        $showcase = collect($this->get(route('home'))->viewData('page')['props']['showcaseProducts'])->pluck('name')->all();
        $this->assertContains('Linen Hoodie', $showcase);
        $this->assertNotContains('Gone Hoodie', $showcase, 'sold out in every size');
    }

    public function test_with_v2_on_a_hold_takes_its_size_out_of_availability(): void
    {
        config(['serana.stock_v2' => 'on']);
        $hoodie = $this->hoodie();
        $this->stock($hoodie, ['M' => 1]);
        $order = CustomOrder::create(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000]);
        $order->stockReservations()->create(['product_id' => $hoodie->id, 'product_variant_id' => $hoodie->variants()->where('size', 'M')->value('id'),
            'location_id' => Location::sole()->id, 'quantity' => 1, 'status' => 'held', 'expires_at' => now()->addMinutes(10)]);

        $this->assertSame(0, $hoodie->fresh()->available_stock);
    }

    public function test_made_to_order_pieces_stay_unlimited_in_both_modes(): void
    {
        $free = $this->hoodie(['batch_limit' => null]);
        foreach (['off', 'on'] as $mode) {
            config(['serana.stock_v2' => $mode]);
            $this->assertNull($free->fresh()->available_stock, $mode);
            $this->assertTrue(collect($this->get(route('shop.show', $free))->viewData('page')['props']['sizes'])->every(fn ($s) => $s['available'] === null), $mode);
        }
    }

    public function test_with_v2_off_nothing_changes(): void
    {
        config(['serana.stock_v2' => 'off']);
        $hoodie = $this->hoodie(['batch_limit' => 5, 'batch_sold' => 1]);
        $this->stock($hoodie, ['M' => 1]); // ignored while off

        $this->assertSame(4, $hoodie->fresh()->available_stock);
        $sizes = $this->get(route('shop.show', $hoodie))->viewData('page')['props']['sizes'];
        $this->assertSame(['S', 'M', 'L', 'XL', 'XXL'], array_column($sizes, 'size'));
        $this->assertSame([null], array_values(array_unique(array_column($sizes, 'available'))), 'no per-size counts from the old model');
    }
}
