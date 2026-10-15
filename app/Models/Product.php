<?php

namespace App\Models;

use App\Support\PublicUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'category_id',
        'garment_type',
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
        'garment_type' => 'string',
        'is_customizable' => 'boolean',
        'in_stock' => 'boolean',
        'specifications' => 'array',
        'batch_limit' => 'integer',
        'batch_sold' => 'integer',
        'secondary_images' => 'array',
    ];

    public function getImageUrlAttribute()
    {
        return PublicUrl::resolve($this->image_path);
    }

    /**
     * Kept for frontend compatibility. Images are now optimized to WebP at upload
     * time (see App\Services\ImageOptimizer), so the stored asset is already the
     * optimized one — no runtime transform needed.
     */
    public function getOptimizedImageUrlAttribute()
    {
        return $this->image_url;
    }

    public function getSecondaryImageUrlsAttribute()
    {
        return array_map(
            fn ($path) => PublicUrl::resolve($path),
            $this->secondary_images ?? []
        );
    }

    public function getOptimizedSecondaryUrlsAttribute()
    {
        // Already optimized at upload time; return the resolved URLs as-is.
        return $this->secondary_image_urls;
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reservations()
    {
        return $this->hasMany(StockReservation::class);
    }

    /** Quantity currently held by unpaid, non-expired checkouts. */
    public function reservedQuantity(): int
    {
        return (int) $this->reservations()->active()->sum('quantity');
    }

    /**
     * Units a customer can still buy: batch cap minus sold minus active holds.
     * Returns null for products without a batch limit (treated as unlimited).
     */
    public function availableStock(): ?int
    {
        if ($this->batch_limit === null) {
            return null;
        }

        return max(0, $this->batch_limit - (int) $this->batch_sold - $this->reservedQuantity());
    }
}
