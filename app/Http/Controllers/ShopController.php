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
        $query = Product::with('category')->withAvailability();
        
        if ($request->filled('category') && $request->category !== 'all') {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }
        
        // available_stock counts unpaid holds, so a piece held by another
        // checkout shows sold out here instead of failing at payment.
        $products = $query->get()->append('available_stock');
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
        return Inertia::render('ProductDetail', [
            'product' => $product->load('category')->append('available_stock')
        ]);
    }
}
