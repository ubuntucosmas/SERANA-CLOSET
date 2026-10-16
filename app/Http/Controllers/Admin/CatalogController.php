<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CatalogController extends Controller
{
    public function __construct(private ImageOptimizer $optimizer)
    {
    }

    public function index()
    {
        return redirect()->route('studio.index', ['tab' => 'products']);
    }

    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'secondary_image_1' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'secondary_image_2' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'name' => 'required|string|max:255|unique:products,name',
            'description' => 'required|string',
            // Whole shillings only: M-Pesa cannot charge cents, and checkout refuses fractional totals.
            'price' => ['required', 'numeric', 'min:1', 'regex:/^\d+(\.0{1,2})?$/'],
            'garment_type' => 'sometimes|in:set,hoodie,jogger',
            'is_customizable' => 'boolean',
            'in_stock' => 'boolean',
            'specifications' => 'nullable|array',
            'batch_limit' => 'nullable|integer|min:0',
        ], [
            'price.regex' => 'Use whole shillings, e.g. 1500. M-Pesa cannot charge cents.',
            'price.min' => 'Price must be at least KSh 1.',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $this->optimizer->store($request->file('image'), 'products');
        }

        // Handle up to 2 secondary images
        $secondaryPaths = [];
        if ($request->hasFile('secondary_image_1')) {
            $secondaryPaths[] = $this->optimizer->store($request->file('secondary_image_1'), 'products');
        }
        if ($request->hasFile('secondary_image_2')) {
            $secondaryPaths[] = $this->optimizer->store($request->file('secondary_image_2'), 'products');
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
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'secondary_image_1' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'secondary_image_2' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'name' => 'required|string|max:255|unique:products,name,' . $product->id,
            'description' => 'required|string',
            // Whole shillings only: M-Pesa cannot charge cents, and checkout refuses fractional totals.
            'price' => ['required', 'numeric', 'min:1', 'regex:/^\d+(\.0{1,2})?$/'],
            'garment_type' => 'sometimes|in:set,hoodie,jogger',
            'in_stock' => 'boolean',
            'batch_limit' => 'nullable|integer|min:0',
            'batch_sold' => 'nullable|integer|min:0',
            'is_customizable' => 'boolean',
            'specifications' => 'nullable|array',
        ], [
            'price.regex' => 'Use whole shillings, e.g. 1500. M-Pesa cannot charge cents.',
            'price.min' => 'Price must be at least KSh 1.',
        ]);

        $targetDisk = config('filesystems.public_disk');
        if ($request->hasFile('image')) {
            // Delete old primary image if it exists
            if ($product->image_path) {
                Storage::disk($targetDisk)->delete($product->image_path);
            }
            $validated['image_path'] = $this->optimizer->store($request->file('image'), 'products');
        }

        // Handle Secondary Image Replacements
        $secondaryImages = $product->secondary_images ?? [];

        if ($request->hasFile('secondary_image_1')) {
            // Delete old slot 1 if it exists
            if (isset($secondaryImages[0])) {
                Storage::disk($targetDisk)->delete($secondaryImages[0]);
            }
            $secondaryImages[0] = $this->optimizer->store($request->file('secondary_image_1'), 'products');
        }

        if ($request->hasFile('secondary_image_2')) {
            // Delete old slot 2 if it exists
            if (isset($secondaryImages[1])) {
                Storage::disk($targetDisk)->delete($secondaryImages[1]);
            }
            $secondaryImages[1] = $this->optimizer->store($request->file('secondary_image_2'), 'products');
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
        $targetDisk = config('filesystems.public_disk');
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

            // Optimize + store on the configured public disk; accessor handles the URL.
            $validated['banner_url'] = $this->optimizer->store($request->file('banner'), 'collections');
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
