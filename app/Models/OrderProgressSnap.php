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
        $rawPath = ltrim(str_replace('/storage/', '/', $this->image_path), '/');
        return Storage::disk('public')->url($rawPath);
    }

    public function customOrder()
    {
        return $this->belongsTo(CustomOrder::class);
    }
}
