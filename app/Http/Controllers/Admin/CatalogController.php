<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CatalogController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/StudioDashboard', [
            'orders' => \App\Models\CustomOrder::with('user')->latest()->get(),
            'products' => Product::with('category')->latest()->get(),
            'categories' => Category::all(),
            'active_tab' => 'catalog'
        ]);
    }

    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255|unique:products,name',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'image' => 'nullable|image|max:5120',
            'is_customizable' => 'boolean',
            'in_stock' => 'boolean',
            'specifications' => 'nullable|array',
            'batch_limit' => 'nullable|integer',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image_path'] = $path; // Store raw path; models resolve to full URL via Storage::disk('public')->url()
        }

        $validated['slug'] = Str::slug($validated['name']);
        
        Product::create($validated);

        return back()->with('success', 'Product added to the shop.');
    }

    public function updateProduct(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255|unique:products,name,' . $product->id,
            'description' => 'required|string',
            'price' => 'required|numeric',
            'in_stock' => 'boolean',
            'batch_limit' => 'nullable|integer',
            'batch_sold' => 'nullable|integer',
        ]);

        $product->update($validated);

        return back()->with('success', 'Product updated.');
    }

    public function destroyProduct(Product $product)
    {
        if ($product->image_path) {
            // Handle both old /storage/-prefixed paths and new raw paths
            $rawPath = ltrim(str_replace('/storage/', '/', $product->image_path), '/');
            Storage::disk('public')->delete($rawPath);
        }

        $product->delete();
        return back()->with('success', 'Product removed from collection.');
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        Category::create($validated);

        return back()->with('success', 'Category created.');
    }
}
