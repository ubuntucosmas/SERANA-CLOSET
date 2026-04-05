<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|image|max:10240',
            'client_name' => 'required|string',
            'garment_name' => 'required|string',
            'testimonial' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('gallery', 'public');
            $validated['image_path'] = '/storage/' . $path;
        }

        GalleryImage::create($validated);

        return back()->with('success', 'Circle showcase added successfully.');
    }

    public function destroy(GalleryImage $image)
    {
        // Delete physical file
        $path = str_replace('/storage/', '', $image->image_path);
        Storage::disk('public')->delete($path);
        
        $image->delete();

        return back()->with('success', 'Circle showcase removed.');
    }
}
