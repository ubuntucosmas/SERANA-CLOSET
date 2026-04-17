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
        $targetDisk = config('filesystems.public_disk');
        $driver = config("filesystems.disks.{$targetDisk}.driver", 'local');

        return array_map(function ($path) use ($targetDisk, $driver) {
            if (str_starts_with($path, 'http')) return $path;
            
            if ($driver === 'local') {
                $rawPath = ltrim(str_replace('/storage/', '/', $path), '/');
                return '/storage/' . $rawPath;
            }
            
            return Storage::disk($targetDisk)->url($path);
        }, $paths);
    }

    public function getItemsWithUrlsAttribute()
    {
        $items = $this->items_json ?: [];
        if (!is_array($items)) return [];

        $targetDisk = config('filesystems.public_disk');
        $driver = config("filesystems.disks.{$targetDisk}.driver", 'local');
        $processed = [];
        foreach ($items as $key => $item) {
            if ($key === 'precision_sizing') continue;
            
            if (is_array($item)) {
                $img = $item['image'] ?? $item['image_path'] ?? null;
                if ($img) {
                    if (str_starts_with($img, 'http')) {
                        $item['image_url'] = $img;
                    } elseif ($driver === 'local') {
                        $rawPath = ltrim(str_replace('/storage/', '/', $img), '/');
                        $item['image_url'] = '/storage/' . $rawPath;
                    } else {
                        $item['image_url'] = Storage::disk($targetDisk)->url($img);
                    }
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
