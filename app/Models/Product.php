<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'image_path',
        'is_customizable',
        'in_stock',
        'specifications',
        'batch_limit',
        'batch_sold',
        'secondary_images',
    ];

    protected $appends = ['image_url', 'secondary_image_urls', 'optimized_image_url', 'optimized_secondary_urls'];

    protected $casts = [
        'price' => 'decimal:2',
        'is_customizable' => 'boolean',
        'in_stock' => 'boolean',
        'specifications' => 'array',
        'batch_limit' => 'integer',
        'batch_sold' => 'integer',
        'secondary_images' => 'array',
    ];

    public function getImageUrlAttribute()
    {
        if (!$this->image_path) return null;
        if (str_starts_with($this->image_path, 'http')) return $this->image_path;

        // Dynamically select the target disk (local public vs cloud s3)
        $targetDisk = env('FILESYSTEM_DISK_PUBLIC', 'public');
        $disk = Storage::disk($targetDisk);
        $driver = config("filesystems.disks.{$targetDisk}.driver", 'local');

        if ($driver === 'local') {
            $rawPath = ltrim(str_replace('/storage/', '/', $this->image_path), '/');
            return '/storage/' . $rawPath;
        }

        return $disk->url($this->image_path);
    }

    /**
     * Supabase Image Intelligence: Serve optimized, resized WebP assets.
     */
    public function getOptimizedImageUrlAttribute()
    {
        $url = $this->image_url;
        
        // Only attempt optimization if enabled and URL is from Supabase
        if (!env('SUPABASE_IMAGE_OPTIMIZATION', true) || 
            !$url || 
            str_contains($url, 'localhost') || 
            !str_contains($url, 'supabase.co')) {
            return $url;
        }

        // Supabase Transformation API: /render/image/public/
        return str_replace('/object/public/', '/render/image/public/', $url) . '?width=1000&quality=80&format=webp';
    }

    public function getSecondaryImageUrlsAttribute()
    {
        $images = $this->secondary_images ?? [];
        $targetDisk = env('FILESYSTEM_DISK_PUBLIC', 'public');
        $driver = config("filesystems.disks.{$targetDisk}.driver", 'local');

        return array_map(function($path) use ($targetDisk, $driver) {
            if (!$path) return null;
            if (str_starts_with($path, 'http')) return $path;

            if ($driver === 'local') {
                $rawPath = ltrim(str_replace('/storage/', '/', $path), '/');
                return '/storage/' . $rawPath;
            }

            return Storage::disk($targetDisk)->url($path);
        }, $images);
    }

    public function getOptimizedSecondaryUrlsAttribute()
    {
        return array_map(function($url) {
            if (!$url || str_contains($url, 'localhost') || !str_contains($url, 'supabase.co')) {
                return $url;
            }
            return str_replace('/object/public/', '/render/image/public/', $url) . '?width=800&quality=75&format=webp';
        }, $this->secondary_image_urls);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }


}
