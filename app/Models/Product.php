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
        'track_stock',
        'secondary_images',
    ];

    /** Mirrors the column default, so variants are built for the garment the row will actually be. */
    protected $attributes = ['garment_type' => 'set'];

    protected $appends = ['image_url', 'secondary_image_urls', 'optimized_image_url', 'optimized_secondary_urls'];

    protected $casts = [
        'price' => 'decimal:2',
        'garment_type' => 'string',
        'is_customizable' => 'boolean',
        'in_stock' => 'boolean',
        'specifications' => 'array',
        'batch_limit' => 'integer',
        'batch_sold' => 'integer',
        'track_stock' => 'boolean',
        // Present only after withAvailability(); MariaDB sums arrive as strings.
        'reserved_quantity' => 'integer',
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

    protected static function booted(): void
    {
        // D8: a batch limit means stocked, none means made to order. While the old model
        // is live, track_stock follows the limit unless it is set explicitly.
        static::saving(function (Product $product) {
            if ($product->isDirty('batch_limit') && ! $product->isDirty('track_stock')) {
                $product->track_stock = $product->batch_limit !== null;
            }
        });
        // Every product has a variant per offered size (design §13.1).
        static::saved(function (Product $product) {
            if ($product->wasRecentlyCreated || $product->wasChanged(['garment_type', 'specifications'])) {
                ProductVariant::ensureFor($product);
            }
        });
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reservations()
    {
        return $this->hasMany(StockReservation::class);
    }

    /**
     * Load active holds in the same query, so listings can show true
     * availability (sold + held) without a query per product.
     */
    public function scopeWithAvailability($query)
    {
        return $query->withSum(['reservations as reserved_quantity' => fn ($q) => $q->active()->countsForProduct()], 'quantity');
    }

    /**
     * What a shopper sees and the Studio reports: null when unlimited.
     * Listings must append() it after withAvailability() (see ShopController).
     */
    /** What the storefront shows: per-size stock when v2 is on (design §13.3), else the batch numbers. Null = unlimited. */
    public function getAvailableStockAttribute(): ?int
    {
        return \App\Support\StockMode::v2() ? app(\App\Services\StockService::class)->productAvailableOnline($this) : $this->availableStock();
    }

    /** Quantity currently held by unpaid, non-expired checkouts. */
    public function reservedQuantity(): int
    {
        if (array_key_exists('reserved_quantity', $this->attributes)) {
            return (int) $this->attributes['reserved_quantity'];
        }

        return (int) $this->reservations()->active()->countsForProduct()->sum('quantity');
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
