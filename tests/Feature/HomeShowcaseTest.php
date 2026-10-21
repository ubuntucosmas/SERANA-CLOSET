<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeShowcaseTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\StockFixtures; // the same showcase in either stock model (Phase D1)

    public function test_showcase_only_contains_available_photographed_products(): void
    {
        $make = fn (array $attributes = []) => Product::create(array_merge([
            'name' => 'Studio Piece', 'slug' => uniqid('piece-'), 'price' => 4500,
            'image_path' => 'products/piece.webp', 'in_stock' => true, 'garment_type' => 'hoodie', 'specifications' => ['sizes' => ['One size']],
        ], $attributes));
        $available = $make();
        $make(['in_stock' => false]);
        $make(['batch_limit' => 2, 'batch_sold' => 2]); // sold out: nothing on hand either
        $make(['image_path' => null]);
        $make(['image_path' => '']);
        $make()->delete();
        $held = $make(['batch_limit' => 1]);
        $this->setOnHand($held, 1);
        $order = CustomOrder::create([
            'full_name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '254700000000',
            'whatsapp_number' => '254700000000', 'type' => 'standard', 'status' => 'Pending Payment',
            'price_quoted' => 4500, 'is_paid' => false,
        ]);
        $hold = $this->holdFor($order, $held, 1, 'held', now()->addMinutes(15));
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Home')->has('showcaseProducts', 1)
            ->where('showcaseProducts.0.id', $available->id)
            ->where('showcaseProducts.0.price', '4500.00'));

        $hold->update(['expires_at' => now()->subMinute()]);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('showcaseProducts', 2));
    }

    public function test_empty_catalogue_has_an_empty_showcase(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->component('Home')->has('showcaseProducts', 0));
    }
}
