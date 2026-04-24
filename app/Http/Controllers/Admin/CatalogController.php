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
            $targetDisk = config('filesystems.public_disk');
            $path = $request->file('image')->store('products', $targetDisk);
            $validated['image_path'] = $path;
        }

        // Handle up to 2 secondary images
        $secondaryPaths = [];
        $targetDisk = config('filesystems.public_disk');
        if ($request->hasFile('secondary_image_1')) {
            $secondaryPaths[] = $request->file('secondary_image_1')->store('products', $targetDisk);
        }
        if ($request->hasFile('secondary_image_2')) {
            $secondaryPaths[] = $request->file('secondary_image_2')->store('products', $targetDisk);
        }
        
        if (!empty($secondaryPaths)) {
            $validated['secondary_images'] = $secondaryPaths;
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
            'is_customizable' => 'boolean',
            'specifications' => 'nullable|array',
        ]);

        if ($request->hasFile('image')) {
            $targetDisk = config('filesystems.public_disk');
            // Delete old primary image if it exists
            if ($product->image_path) {
                Storage::disk($targetDisk)->delete($product->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('products', $targetDisk);
        }

        // Handle Secondary Image Replacements
        $secondaryImages = $product->secondary_images ?? [];
        
        $targetDisk = config('filesystems.public_disk');
        if ($request->hasFile('secondary_image_1')) {
            // Delete old slot 1 if it exists
            if (isset($secondaryImages[0])) {
                Storage::disk($targetDisk)->delete($secondaryImages[0]);
            }
            $secondaryImages[0] = $request->file('secondary_image_1')->store('products', $targetDisk);
        }

        if ($request->hasFile('secondary_image_2')) {
            // Delete old slot 2 if it exists
            if (isset($secondaryImages[1])) {
                Storage::disk($targetDisk)->delete($secondaryImages[1]);
            }
            $secondaryImages[1] = $request->file('secondary_image_2')->store('products', $targetDisk);
        }

        if (!empty($secondaryImages)) {
            $validated['secondary_images'] = array_values($secondaryImages); // Ensure clean array keys
        }

        $validated['slug'] = Str::slug($validated['name']);
        $product->update($validated);

        return back()->with('success', 'Collection piece updated successfully.');
    }

    public function destroyProduct(Product $product)
    {
        $product->delete();
        return back()->with('success', 'Product moved to archive (Unpublished).');
    }

    public function restoreProduct(Product $product)
    {
        $product->restore();
        return back()->with('success', 'Product restored to collection (Published).');
    }

    public function forceDeleteProduct(Product $product)
    {
        $targetDisk = env('FILESYSTEM_DISK_PUBLIC', 'public');
        // Permanent asset removal on force delete
        if ($product->image_path) {
            Storage::disk($targetDisk)->delete($product->image_path);
        }

        if ($product->secondary_images && is_array($product->secondary_images)) {
            foreach ($product->secondary_images as $path) {
                Storage::disk($targetDisk)->delete($path);
            }
        }

        $product->forceDelete();
        return back()->with('success', 'Product and all associated assets permanently purged.');
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        Category::create($validated);

        return back()->with('success', 'Collection segment created.');
    }

    public function updateCategory(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
            'banner' => 'nullable|image|max:5120', // Max 5MB
        ]);

        if ($request->hasFile('banner')) {
            $targetDisk = config('filesystems.public_disk', 's3');
            
            // Delete old banner if exists (assuming it's a relative path now)
            if ($category->getRawOriginal('banner_url')) {
                Storage::disk($targetDisk)->delete($category->getRawOriginal('banner_url'));
            }
            
            // Store on the configured public disk
            $path = $request->file('banner')->store('collections', $targetDisk);
            $validated['banner_url'] = $path; // Store the relative path, accessor handles the URL
        } elseif ($request->boolean('remove_banner')) {
            $targetDisk = config('filesystems.public_disk', 's3');
            if ($category->getRawOriginal('banner_url')) {
                Storage::disk($targetDisk)->delete($category->getRawOriginal('banner_url'));
            }
            $validated['banner_url'] = null;
        }

        $validated['slug'] = Str::slug($validated['name']);
        $category->update($validated);

        return back()->with('success', 'Collection segment updated.');
    }

    public function destroyCategory(Category $category)
    {
        // Check if category has products
        if ($category->products()->count() > 0) {
            return back()->with('error', 'Cannot delete a collection that contains active pieces. Please reassign the pieces first.');
        }

        $category->delete();
        return back()->with('success', 'Collection segment removed.');
    }
}
