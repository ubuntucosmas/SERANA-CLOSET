<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use Inertia\Inertia;

class CircleController extends Controller
{
    /**
     * Display the Serana Circle Gallery.
     */
    public function index()
    {
        return Inertia::render('Circle', [
            'images' => GalleryImage::latest()->get()
        ]);
    }
}
