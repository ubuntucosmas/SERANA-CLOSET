<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ThemeSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting by key with an optional default.
     */
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        
        if (!$setting || empty($setting->value)) {
            return $default;
        }

        // If it looks like a file path (starts with themes/ or similar), return the full URL
        if (str_starts_with($setting->value, 'themes/')) {
            $targetDisk = env('FILESYSTEM_DISK_PUBLIC', 'public');
            $driver = config("filesystems.disks.{$targetDisk}.driver", 'local');

            if ($driver === 'local') {
                return '/storage/' . ltrim($setting->value, '/');
            }
            return Storage::disk($targetDisk)->url($setting->value);
        }

        return $setting->value;
    }

    /**
     * Set a setting value.
     */
    public static function set($key, $value)
    {
        return self::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
