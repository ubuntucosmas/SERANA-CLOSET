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
            'products' => Product::with('category')->latest()->get(),
            'categories' => Category::all(),
            'gallery' => GalleryImage::latest()->get(),
            'leads' => Lead::latest()->get(),
            'stats' => [
                'total_revenue' => $total_revenue,
                'active_orders' => $active_orders,
                'total_leads' => $total_leads,
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

    public function storeProgressSnap(Request $request, CustomOrder $order)
    {
        $validated = $request->validate([
            'image' => 'required|image|max:10240',
            'description' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('progress_snaps', 'public');
            $validated['image_path'] = '/storage/' . $path;
            $validated['custom_order_id'] = $order->id;
        }

        OrderProgressSnap::create($validated);

        return back()->with('success', 'Order photo uploaded.');
    }
}
