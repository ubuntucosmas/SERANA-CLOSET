<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class OrderProgressSnap extends Model
{
    protected $fillable = [
        'custom_order_id',
        'image_path',
        'description'
    ];

    protected $appends = ['image_url'];

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

    public function customOrder()
    {
        return $this->belongsTo(CustomOrder::class);
    }
}
