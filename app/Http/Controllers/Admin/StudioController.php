<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use App\Models\OrderProgressSnap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

use App\Models\Product;
use App\Models\Category;
use App\Models\GalleryImage;

use App\Models\Lead;
use App\Models\ThemeSetting;
use Carbon\Carbon;

class StudioController extends Controller
{
    public function index(Request $request)
    {
        $total_revenue = CustomOrder::where('is_paid', true)->sum('price_quoted');
        $active_orders = CustomOrder::whereNotIn('status', ['completed', 'shipped'])->count();
        $total_leads   = Lead::count();
        
        // Simple growth metric (leads in last 7 days)
        $recent_leads = Lead::where('created_at', '>=', Carbon::now()->subDays(7))->count();

        return Inertia::render('Admin/StudioDashboard', [
            'orders' => CustomOrder::with(['user', 'progressSnaps'])->latest()->get(),
            'archived_orders' => CustomOrder::onlyTrashed()->with(['user', 'progressSnaps'])->latest()->get(),
            'products' => Product::with('category')->latest()->get(),
            'archived_products' => Product::onlyTrashed()->with('category')->latest()->get(),
            'categories' => Category::all(),
            'gallery' => GalleryImage::latest()->get(),
            'archived_gallery' => GalleryImage::onlyTrashed()->latest()->get(),
            'leads' => Lead::latest()->get(),
            'stats' => [
                'total_revenue' => $total_revenue,
                'active_orders' => $active_orders,
                'total_orders'  => CustomOrder::withTrashed()->count(),
                'total_leads'   => $total_leads,
                'recent_leads_growth' => $recent_leads
            ],
            'active_tab' => $request->get('tab', 'overview')
        ]);
    }

    public function update(Request $request, CustomOrder $order)
    {
        $validated = $request->validate([
            'status' => 'required|string',
            'internal_notes' => 'nullable|string',
            'price_quoted' => 'nullable|numeric',
            'is_paid' => 'nullable|boolean',
        ]);

        $order->update($validated);

        return back()->with('success', 'Order updated successfully.');
    }

    public function destroy(CustomOrder $order)
    {
        $order->delete();
        return back()->with('success', 'Order moved to the archive.');
    }

    public function restore($id)
    {
        $order = CustomOrder::withTrashed()->findOrFail($id);
        $order->restore();
        return back()->with('success', 'Order restored to active registry.');
    }

    public function forceDelete($id)
    {
        $order = CustomOrder::withTrashed()->findOrFail($id);
        
        // Clean up linked progress snaps imagery if they exist
        foreach ($order->progressSnaps as $snap) {
            if ($snap->image_path) {
                Storage::disk(config('filesystems.public_disk'))->delete($snap->image_path);
            }
        }
        
        $order->forceDelete();
        return back()->with('success', 'Order and associated visual assets permanently purged.');
    }

    public function storeProgressSnap(Request $request, CustomOrder $order)
    {
        $validated = $request->validate([
            'image' => 'required|image|max:10240',
            'description' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            $targetDisk = config('filesystems.public_disk');
            $path = $request->file('image')->store('progress_snaps', $targetDisk);
            $validated['image_path'] = $path; // Store raw path; models resolve to full URL via dynamic disk logic
            $validated['custom_order_id'] = $order->id;
        }

        OrderProgressSnap::create($validated);

        return back()->with('success', 'Order photo uploaded.');
    }
}
