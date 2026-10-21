<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\Category;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $v2 = \App\Support\StockMode::v2();
        $query = Product::with('category')->when(! $v2, fn ($q) => $q->withAvailability());
        
        if ($request->filled('category') && $request->category !== 'all') {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }
        
        // available_stock counts unpaid holds, so a piece held by another
        // checkout shows sold out here instead of failing at payment.
        $products = $query->get();
        if ($v2) {
            app(\App\Services\StockService::class)->preloadAvailability($products); // a few queries for the whole list
        }
        $products = $products->append('available_stock');
        $categories = Category::all();

        return Inertia::render('Shop', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $request->only(['category', 'sort'])
        ]);
    }

    public function show(Product $product)
    {
        // Load related cross-sells logic later if needed
        $v2 = \App\Support\StockMode::v2();

        return Inertia::render('ProductDetail', [
            'product' => $product->load('category')->append('available_stock'),
            // The sizes on offer, per part, with what is left of each once stock is per size (null = no count).
            'sizes' => $v2
                ? app(\App\Services\StockService::class)->sizesOnline($product)
                : $product->variants()->where('active', true)->orderBy('sort')->get()
                    ->map(fn ($v) => ['id' => $v->id, 'part' => $v->part, 'size' => $v->size, 'available' => null])->all(),
        ]);
    }
}
