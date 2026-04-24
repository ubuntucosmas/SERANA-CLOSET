<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'banner_url'];

    public function getBannerUrlAttribute($value)
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;

        // Dynamically select the target disk (local public vs cloud s3)
        $targetDisk = config('filesystems.public_disk', 's3');
        $disk = \Illuminate\Support\Facades\Storage::disk($targetDisk);
        $driver = config("filesystems.disks.{$targetDisk}.driver", 'local');

        if ($driver === 'local') {
            $rawPath = ltrim(str_replace('/storage/', '/', $value), '/');
            return '/storage/' . $rawPath;
        }

        return $disk->url($value);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

}
