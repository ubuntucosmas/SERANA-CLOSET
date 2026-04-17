<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class GalleryImage extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'image_path',
        'client_name',
        'garment_name',
        'testimonial',
        'is_featured'
    ];

    protected $appends = ['image_url', 'optimized_image_url'];

    public function getImageUrlAttribute()
    {
        if (!$this->image_path) return null;
        if (str_starts_with($this->image_path, 'http')) return $this->image_path;

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
     * Supabase Image Intelligence optimization.
     */
    public function getOptimizedImageUrlAttribute()
    {
        $url = $this->image_url;
        if (!env('SUPABASE_IMAGE_OPTIMIZATION', true) || 
            !$url || 
            str_contains($url, 'localhost') || 
            !str_contains($url, 'supabase.co')) {
            return $url;
        }

        return str_replace('/object/public/', '/render/image/public/', $url) . '?width=1000&quality=80&format=webp';
    }
}
