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
        // Live studio metrics — active orders in progress
        $studioLoad     = CustomOrder::whereIn('status', ['pending', 'in_progress'])->count();
        $studioCapacity = min(100, (int) round(($studioLoad / max(1, 20)) * 100));

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
