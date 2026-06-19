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

        $targetDisk = config('filesystems.public_disk');
        $disk = Storage::disk($targetDisk);
        $driver = config("filesystems.disks.{$targetDisk}.driver", 'local');

        if ($driver === 'local') {
            $rawPath = ltrim(str_replace('/storage/', '/', $this->image_path), '/');
            return '/storage/' . $rawPath;
        }

        return $disk->url($this->image_path);
    }

    /**
     * Kept for frontend compatibility. Images are optimized to WebP at upload
     * time (see App\Services\ImageOptimizer), so the stored asset is already
     * optimized — no runtime transform needed.
     */
    public function getOptimizedImageUrlAttribute()
    {
        return $this->image_url;
    }
}
