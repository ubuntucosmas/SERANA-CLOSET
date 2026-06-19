<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    protected $fillable = [
        'custom_order_id',
        'product_id',
        'quantity',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'quantity'   => 'integer',
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

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(CustomOrder::class, 'custom_order_id');
    }
}
