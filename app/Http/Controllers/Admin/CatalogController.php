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
            // Set once at creation; afterwards both live in Studio → Stock.
            'sizes' => 'nullable|array|max:20',
            'sizes.*' => 'nullable|string|max:20|distinct:ignore_case',
            'track_stock' => 'nullable|boolean',
        ], [
            'price.regex' => 'Use whole shillings, e.g. 1500. M-Pesa cannot charge cents.',
            'price.min' => 'Price must be at least KSh 1.',
            'sizes.*.distinct' => 'Each size can be listed only once.',
        ]);
        $sizes = array_values(array_filter(array_map(fn ($size) => trim((string) $size), $validated['sizes'] ?? []), fn ($size) => $size !== ''));
        unset($validated['sizes']);
        if ($sizes !== []) {
            $validated['specifications'] = array_merge($validated['specifications'] ?? [], ['sizes' => $sizes]);
        }
        if (array_key_exists('track_stock', $validated) && $validated['track_stock'] === null) {
            unset($validated['track_stock']);
        }

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
        
        $product = Product::create($validated);
        \App\Support\Audit::record('product.created', $product, [], $product->only(['name', 'price', 'batch_limit', 'in_stock', 'track_stock']));

        return back()->with('success', $product->track_stock && \App\Support\StockMode::v2()
            ? 'Product saved. It shows as sold out until you count its stock in Studio → Stock.'
            : 'Product added to the shop.');
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
            // batch_sold is never taken from a form: only payment settlement
            // moves it, so a stale Studio tab can't overwrite real sales.
            'is_customizable' => 'boolean',
            'specifications' => 'nullable|array',
        ], [
            'price.regex' => 'Use whole shillings, e.g. 1500. M-Pesa cannot charge cents.',
            'price.min' => 'Price must be at least KSh 1.',
        ]);

        // With stock per size on, stock lives in Studio → Stock: the batch numbers follow it.
        if (\App\Support\StockMode::v2()) {
            unset($validated['batch_limit']);
        }

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
        // Price and stock are money/stock-sensitive: audit what changed.
        \App\Support\Audit::changes('product.updated', $product, array_intersect_key($validated, array_flip(['price', 'batch_limit', 'in_stock'])));
        $product->update($validated);

        return back()->with('success', 'Collection piece updated successfully.');
    }

    public function destroyProduct(Product $product)
    {
        $product->delete();
        \App\Support\Audit::record('product.archived', $product);
        return back()->with('success', 'Product moved to archive (Unpublished).');
    }

    public function restoreProduct(Product $product)
    {
        $product->restore();
        \App\Support\Audit::record('product.restored', $product);
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

        \App\Support\Audit::record('product.purged', $product, $product->only(['name', 'slug', 'price', 'batch_limit', 'batch_sold']));
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
