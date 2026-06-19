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
use App\Models\Drop;
use App\Support\OrderStatus;
use Carbon\Carbon;

class StudioController extends Controller
{
    public function index(Request $request)
    {
        $weekAgo = Carbon::now()->subDays(7);

        // Production stages still being actively worked (not yet completed/shipped).
        $activeStages = array_values(array_diff(
            OrderStatus::PRODUCTION_STAGES,
            OrderStatus::PRODUCTION_TERMINAL
        ));

        // Lightweight, always-present payload for the Home screen.
        $stats = [
            'new_orders_week'   => CustomOrder::where('created_at', '>=', $weekAgo)->count(),
            'revenue_week'      => (float) CustomOrder::where('is_paid', true)
                                        ->where('created_at', '>=', $weekAgo)->sum('price_quoted'),
            'in_production'     => CustomOrder::whereIn('status', $activeStages)->count(),
            'subscribers_total' => Lead::count(),
        ];

        $attention = [
            'awaiting' => CustomOrder::where('status', OrderStatus::PENDING_PAYMENT)
                ->where('is_paid', false)->latest()->take(5)->get()
                ->map(fn ($o) => [
                    'id'        => $o->id,
                    'full_name' => $o->full_name,
                    'amount'    => $o->price_quoted,
                    'reference' => $o->cipher,
                ]),
            'low_stock' => Product::where('in_stock', false)->latest()->take(5)->get(['id', 'name']),
        ];

        return Inertia::render('Admin/StudioDashboard', [
            'stats'      => $stats,
            'attention'  => $attention,
            'active_tab' => $request->get('tab', 'home'),

            // Eager so every list stays in sync after a save/redirect. The dataset
            // is boutique-sized; paginate here if a section ever grows large.
            'orders'            => CustomOrder::with(['user', 'progressSnaps'])->latest()->get(),
            'archived_orders'   => CustomOrder::onlyTrashed()->with(['user', 'progressSnaps'])->latest()->get(),
            'products'          => Product::with('category')->latest()->get(),
            'archived_products' => Product::onlyTrashed()->with('category')->latest()->get(),
            'categories'        => Category::all(),
            'gallery'           => GalleryImage::latest()->get(),
            'archived_gallery'  => GalleryImage::onlyTrashed()->latest()->get(),
            'leads'             => Lead::latest()->get(),
            'drops'             => Drop::with(['reservations' => fn ($q) => $q->latest()])
                                        ->withCount('paidReservations')->latest()->get(),
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
            // Store optimized raw path; models resolve to full URL via dynamic disk logic.
            $validated['image_path'] = app(\App\Services\ImageOptimizer::class)
                ->store($request->file('image'), 'progress_snaps');
            $validated['custom_order_id'] = $order->id;
        }

        OrderProgressSnap::create($validated);

        return back()->with('success', 'Order photo uploaded.');
    }
}
