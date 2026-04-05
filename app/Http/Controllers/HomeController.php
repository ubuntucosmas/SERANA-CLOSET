<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ThemeSetting;
use Inertia\Inertia;

class HomeController extends Controller
{
    /**
     * Display the cinematic Serana Home Experience.
     */
    public function index()
    {
        return Inertia::render('Home', [
            'featuredProducts' => Product::with('category')
                ->where('in_stock', true)
                ->latest()
                ->take(3)
                ->get(),
        ]);
    }
}
