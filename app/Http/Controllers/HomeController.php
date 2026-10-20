<?php

namespace App\Http\Controllers;

use App\Models\Drop;
use App\Models\Product;
use Inertia\Inertia;

class HomeController extends Controller
{
    /**
     * Home: hero, trust bar, categories, four featured pieces and the bespoke invitation.
     */
    public function index()
    {
        return Inertia::render('Home', [
            'featuredProducts' => Product::with('category')->withAvailability()
                ->where('in_stock', true)
                ->latest()
                ->take(4)
                ->get()
                ->append('available_stock'),
            'showcaseProducts' => Product::with('category:id,name')
                ->where('in_stock', true)
                ->whereNotNull('image_path')
                ->where('image_path', '!=', '')
                ->when(! \App\Support\StockMode::v2(), fn ($q) => $q->where(function ($query) {
                    $query->whereNull('batch_limit')->orWhereRaw(
                        'batch_limit > COALESCE(batch_sold, 0) + (SELECT COALESCE(SUM(quantity), 0) FROM stock_reservations WHERE stock_reservations.product_id = products.id AND second_part = 0 AND status = ? AND (expires_at IS NULL OR expires_at > ?))',
                        ['held', now()]
                    );
                }))
                ->latest()
                ->limit(\App\Support\StockMode::v2() ? 48 : 12)
                ->get()
                // Stock per size: a piece sold out in every size leaves the showcase (boutique-sized catalogue).
                ->when(\App\Support\StockMode::v2(), fn ($products) => $products->filter(fn (Product $p) => $p->available_stock !== 0)->take(12)->values())
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'price' => $product->price,
                    'image_url' => $product->image_url,
                    'category' => $product->category?->name,
                ]),
            // The home page doubles as a launchpad for the current live drop.
            'featuredDrop'   => ! config('services.drops.enabled') ? null : Drop::where('status', 'live')
                ->withCount('paidReservations')
                ->latest()
                ->first(),
        ]);
    }
}
