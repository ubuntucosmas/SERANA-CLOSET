<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryImage;
use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    public function __construct(private ImageOptimizer $optimizer)
    {
    }

    public function update(Request $request, GalleryImage $image)
    {
        $validated = $request->validate([
            'client_name' => 'required|string',
            'garment_name' => 'required|string',
            'testimonial' => 'nullable|string',
            'image' => 'nullable|image|max:10240',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image
            Storage::disk(config('filesystems.public_disk'))->delete($image->image_path);

            $validated['image_path'] = $this->optimizer->store($request->file('image'), 'gallery');
        }

        $image->update($validated);

        return back()->with('success', 'Circle showcase updated.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|image|max:10240',
            'client_name' => 'required|string',
            'garment_name' => 'required|string',
            'testimonial' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            // Store optimized raw path; models resolve to full URL via dynamic disk logic.
            $validated['image_path'] = $this->optimizer->store($request->file('image'), 'gallery');
        }

        GalleryImage::create($validated);

        return back()->with('success', 'Circle showcase added successfully.');
    }

    public function destroy(GalleryImage $image)
    {
        $image->delete();

        return back()->with('success', 'Circle showcase moved to archive (Unpublished).');
    }

    public function restore(GalleryImage $image)
    {
        $image->restore();
        return back()->with('success', 'Circle showcase restored to gallery (Published).');
    }

    public function forceDelete(GalleryImage $image)
    {
        // Permanent asset removal
        Storage::disk(config('filesystems.public_disk'))->delete($image->image_path);
        
        $image->forceDelete();

        return back()->with('success', 'Showcase and asset permanently Deleted.');
    }
}
