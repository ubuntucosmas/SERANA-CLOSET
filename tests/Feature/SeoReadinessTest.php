<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Drop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_has_server_rendered_canonical_and_exact_price_structured_data(): void
    {
        config(['app.url' => 'https://shop.example']);
        $product = Product::create(['name' => 'Test Jacket', 'slug' => 'test-jacket', 'price' => 5000, 'in_stock' => true]);
        $this->get('/shop/test-jacket')->assertOk()
            ->assertSee('rel="canonical" href="https://shop.example/shop/test-jacket"', false)
            ->assertSee('application/ld+json', false)->assertSee('"priceCurrency":"KES"', false)
            ->assertSee('"price":"5000.00"', false);
    }

    public function test_sitemap_excludes_drafts_archives_and_private_pages(): void
    {
        Product::create(['name' => 'Visible', 'slug' => 'visible', 'price' => 5000]);
        Product::create(['name' => 'Archived', 'slug' => 'archived', 'price' => 5000])->delete();
        Drop::create(['title' => 'Draft', 'price' => 5000, 'target_quantity' => 10, 'status' => 'draft']);
        $this->get('/sitemap.xml')->assertOk()->assertSee('/shop/visible')
            ->assertDontSee('/shop/archived')->assertDontSee('/drops/draft')->assertDontSee('/profile');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /studio')->assertSee('Sitemap:');
        $this->get('/checkout')->assertOk()->assertSee('content="noindex,nofollow"', false);
    }
}
