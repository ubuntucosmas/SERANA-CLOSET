<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CustomOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'inspiration_image_paths' => 'array',
        'items_json' => 'array',
        'is_paid' => 'boolean',
    ];

    protected $appends = ['inspiration_urls', 'items_with_urls'];

    public function getInspirationUrlsAttribute()
    {
        $paths = $this->inspiration_image_paths ?: [];
        return array_map(function ($path) {
            if (str_starts_with($path, 'http')) return $path;
            $rawPath = ltrim(str_replace('/storage/', '/', $path), '/');
            return Storage::disk('public')->url($rawPath);
        }, $paths);
    }

    public function getItemsWithUrlsAttribute()
    {
        $items = $this->items_json ?: [];
        if (!is_array($items)) return [];

        $processed = [];
        foreach ($items as $key => $item) {
            // Skip the precision_sizing metadata key to keep this a clean sequential array for Vue
            if ($key === 'precision_sizing') continue;
            
            if (is_array($item)) {
                $img = $item['image'] ?? $item['image_path'] ?? null;
                if ($img && !str_starts_with($img, 'http')) {
                    $rawPath = ltrim(str_replace('/storage/', '/', $img), '/');
                    $item['image_url'] = Storage::disk('public')->url($rawPath);
                } else if ($img) {
                    $item['image_url'] = $img;
                }
                $processed[] = $item;
            }
        }
        return $processed;
    }

    protected static function booted()
    {
        static::creating(function ($order) {
            $order->cipher = 'SRN-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('Y');
        });
    }

    public function progressSnaps()
    {
        return $this->hasMany(OrderProgressSnap::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
