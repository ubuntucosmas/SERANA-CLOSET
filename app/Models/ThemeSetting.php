<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ThemeSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Container key for this request's settings map (one query per request or job). */
    public const CACHE = 'theme.settings';

    /**
     * The settings every visitor's page may carry (storefront text, contacts,
     * images). Anything not listed stays on the server: owner inboxes, policies,
     * payment switches, stock mode. Add a key here only when a public page reads it.
     */
    public const PUBLIC_KEYS = [
        'site_name', 'site_tagline', 'site_headline', 'footer_copyright', 'currency',
        'whatsapp_number', 'care_phone', 'contact_email', 'contact_address',
        'instagram_url', 'tiktok_url', 'facebook_url', 'x_url',
        'focal_1_label', 'focal_2_label', 'focal_3_label',
        'site_logo', 'site_favicon', 'hero_bg', 'hero_detail_bg', 'focal_1_bg', 'focal_2_bg', 'focal_3_bg',
    ];

    /** Studio-only text the Settings screen edits (sent only to staff with Studio access). */
    public const STUDIO_KEYS = ['notification_email'];

    /**
     * The settings for the page props, resolved like get(): the public ones
     * (plus legacy category banners, cat_*_bg), and the Studio's own only for staff.
     *
     * @return array<string, mixed>
     */
    public static function forPage(bool $studio): array
    {
        $allowed = array_merge(self::PUBLIC_KEYS, $studio ? self::STUDIO_KEYS : []);

        return collect(self::values())
            ->filter(fn ($value, $key) => in_array($key, $allowed, true) || preg_match('/^cat_[a-z]+_bg$/', $key))
            ->mapWithKeys(fn ($value, $key) => [$key => self::get($key)])
            ->all();
    }

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
