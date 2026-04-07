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

    protected $appends = ['image_url', 'secondary_image_urls'];

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
        $rawPath = ltrim(str_replace('/storage/', '/', $this->image_path), '/');
        return Storage::disk('public')->url($rawPath);
    }

    public function getSecondaryImageUrlsAttribute()
    {
        $images = $this->secondary_images ?? [];
        return array_map(function($path) {
            if (!$path) return null;
            if (str_starts_with($path, 'http')) return $path;
            $rawPath = ltrim(str_replace('/storage/', '/', $path), '/');
            return Storage::disk('public')->url($rawPath);
        }, $images);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
