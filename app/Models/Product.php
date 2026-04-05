<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
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
    ];

    protected $appends = ['image_url'];

    protected $casts = [
        'price' => 'decimal:2',
        'is_customizable' => 'boolean',
        'in_stock' => 'boolean',
        'specifications' => 'array',
        'batch_limit' => 'integer',
        'batch_sold' => 'integer',
    ];

    public function getImageUrlAttribute()
    {
        if (!$this->image_path) return null;
        if (str_starts_with($this->image_path, 'http')) return $this->image_path;
        $path = str_replace('/storage/', '', $this->image_path);
        return asset('storage/' . $path);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
