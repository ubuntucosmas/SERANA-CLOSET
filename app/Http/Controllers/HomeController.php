<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use App\Models\CustomOrder;
use App\Models\ThemeSetting;
use Inertia\Inertia;

class HomeController extends Controller
{
    /**
     * Display the cinematic Serana Home Experience.
     */
    public function index()
    {
        // Live studio metrics — active orders in progress. The capacity target
        // (how many concurrent pieces the studio considers "full") is admin-managed.
        $studioLoad      = CustomOrder::whereIn('status', ['pending', 'in_progress'])->count();
        $capacityTarget  = max(1, (int) ThemeSetting::get('studio_capacity', 20));
        $studioCapacity  = min(100, (int) round(($studioLoad / $capacityTarget) * 100));

        return Inertia::render('Home', [
            'featuredProducts' => Product::with('category')
                ->where('in_stock', true)
                ->latest()
                ->take(3)
                ->get(),
            'studioLoad'     => $studioLoad,
            'studioCapacity' => $studioCapacity,
        ]);
    }
}
