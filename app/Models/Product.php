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

    protected $appends = ['image_url', 'secondary_image_urls', 'optimized_image_url', 'optimized_secondary_urls', 'xray_specs'];

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
        if (!$this->batch_limit) {
            return null;
        }

        return max(0, $this->batch_limit - (int) $this->batch_sold - $this->reservedQuantity());
    }

    /**
     * Standardized X-Ray Overlay Specifications.
     * Provides structured data for the technical "X-Ray" UI overlay.
     */
    public function getXraySpecsAttribute()
    {
        $baseSpecs = $this->specifications ?? [];
        
        return [
            'fabric' => $baseSpecs['fabric'] ?? 'Industrial Cotton Hybrid',
            'weight' => $baseSpecs['fabric_weight'] ?? '420 GSM',
            'density' => $baseSpecs['stitch_density'] ?? '12 Stitches/Inch',
            'architecture' => $baseSpecs['architecture'] ?? 'Reinforced Double-Needle Stitching',
            'tech_specs' => $baseSpecs['tech_specs'] ?? [
                'Thermal Regulation Coating',
                'Water-Resistant Membrane',
                'Articulated Elbow Paneling'
            ],
            'ui' => $baseSpecs['ui'] ?? [
                'weight_index' => 85,
                'weight_label' => 'Optimized for structural integrity & thermal isolation'
            ]
        ];
    }


}
