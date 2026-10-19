<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ThemeSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Container key for this request's settings map (one query per request or job). */
    public const CACHE = 'theme.settings';

    protected static function booted(): void
    {
        // Any write invalidates the map so the same request sees fresh values.
        static::saved(fn () => app()->forgetInstance(self::CACHE));
        static::deleted(fn () => app()->forgetInstance(self::CACHE));
    }

    /** @return array<string, string|null> */
    public static function values(): array
    {
        return app(self::CACHE);
    }

    /**
     * Get a setting by key with an optional default.
     */
    public static function get($key, $default = null)
    {
        $value = self::values()[$key] ?? null;

        if (empty($value)) {
            return $default;
        }

        // If it looks like a file path (starts with themes/ or similar), return the full URL
        if (str_starts_with($value, 'themes/')) {
            $targetDisk = config('filesystems.public_disk');
            $driver = config("filesystems.disks.{$targetDisk}.driver", 'local');

            if ($driver === 'local') {
                return '/storage/' . ltrim($value, '/');
            }
            return Storage::disk($targetDisk)->url($value);
        }

        return $value;
    }

    /**
     * Set a setting value.
     */
    public static function set($key, $value)
    {
        return self::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
