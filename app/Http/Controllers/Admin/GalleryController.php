<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    public function update(Request $request, GalleryImage $image)
    {
        $validated = $request->validate([
            'client_name' => 'required|string',
            'garment_name' => 'required|string',
            'testimonial' => 'nullable|string',
            'image' => 'nullable|image|max:10240',
        ]);

        if ($request->hasFile('image')) {
            $targetDisk = env('FILESYSTEM_DISK_PUBLIC', 'public');
            // Delete old image
            Storage::disk($targetDisk)->delete($image->image_path);
            
            $validated['image_path'] = $request->file('image')->store('gallery', $targetDisk);
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
            $targetDisk = config('filesystems.public_disk');
            $path = $request->file('image')->store('gallery', $targetDisk);
            $validated['image_path'] = $path; // Store raw path; models resolve to full URL via dynamic disk logic
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
        $targetDisk = env('FILESYSTEM_DISK_PUBLIC', 'public');
        // Permanent asset removal
        Storage::disk($targetDisk)->delete($image->image_path);
        
        $image->forceDelete();

        return back()->with('success', 'Showcase and asset permanently Deleted.');
    }
}
