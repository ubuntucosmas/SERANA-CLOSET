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
        if (!$this->image_path) return null;
        if (str_starts_with($this->image_path, 'http')) return $this->image_path;

        // Dynamically select the target disk (local public vs cloud s3)
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
        $images = $this->secondary_images ?? [];
        $targetDisk = config('filesystems.public_disk');
        $driver = config("filesystems.disks.{$targetDisk}.driver", 'local');

        return array_map(function($path) use ($targetDisk, $driver) {
            if (!$path) return null;
            if (str_starts_with($path, 'http')) return $path;

            if ($driver === 'local') {
                $rawPath = ltrim(str_replace('/storage/', '/', $path), '/');
                return '/storage/' . $rawPath;
            }

            return Storage::disk($targetDisk)->url($path);
        }, $images);
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
