<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    protected $fillable = [
        'custom_order_id',
        'product_id',
        'product_variant_id',
        'location_id',
        'second_part',
        'quantity',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'second_part' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /** Holds that still count against availability: not yet committed/released and not expired. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'held')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /** Rows the old product-level model counts: a set's bottom row rides with its top. */
    public function scopeCountsForProduct(Builder $query): Builder
    {
        return $query->where('second_part', false);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function order()
    {
        return $this->belongsTo(CustomOrder::class, 'custom_order_id');
    }
}
