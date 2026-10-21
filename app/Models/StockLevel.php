<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Units on hand of one variant at one location (design §13.1): a cached sum
 * of its stock movements, written only by StockService in the same
 * transaction as the movement. The database refuses a negative value.
 */
class StockLevel extends Model
{
    protected $guarded = [];

    protected $casts = ['on_hand' => 'integer', 'counted_at' => 'datetime'];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
