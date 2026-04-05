<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ThemeController extends Controller
{
    /**
     * Update a theme setting (including file uploads).
     */
    public function update(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'nullable', // Can be string or file
        ]);

        $key = $request->key;
        $value = $request->value;

        // Handle File Uploads
        if ($request->hasFile('value')) {
            // Delete old file if exists
            $oldSetting = ThemeSetting::where('key', $key)->first();
            if ($oldSetting && !empty($oldSetting->value) && str_starts_with($oldSetting->value, 'themes/')) {
                Storage::disk('public')->delete($oldSetting->value);
            }

            $path = $request->file('value')->store('themes', 'public');
            $value = $path;
        }

        ThemeSetting::set($key, $value);

        return back()->with('success', "Theme asset [$key] updated successfully.");
    }

    /**
     * Bulk update theme settings.
     */
    public function bulkUpdate(Request $request)
    {
        $settings = $request->input('settings', []);
        
        foreach ($settings as $key => $value) {
            ThemeSetting::set($key, $value);
        }

        return back()->with('success', 'Theme settings updated.');
    }

    /**
     * Delete a theme setting and its associated file.
     */
    public function destroy($key)
    {
        $setting = ThemeSetting::where('key', $key)->first();
        
        if ($setting) {
            // Delete file if it exists in storage
            if (!empty($setting->value) && str_starts_with($setting->value, 'themes/')) {
                Storage::disk('public')->delete($setting->value);
            }
            
            $setting->delete();
        }

        return back()->with('success', "Asset [$key] has been reset to default.");
    }
}
