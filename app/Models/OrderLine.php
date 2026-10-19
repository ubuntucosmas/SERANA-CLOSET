<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One priced line of an order: an item, the delivery fee, a discount, an
 * adjustment or a retained deposit (design §2.1). The order's total is the
 * sum of its lines. Append-only: a change is a new (reversing) line.
 */
class OrderLine extends Model
{
    public const UPDATED_AT = null;

    public const ITEM = 'item';
    public const DELIVERY = 'delivery';
    public const DISCOUNT = 'discount';
    public const ADJUSTMENT = 'adjustment';
    public const RETAINED_DEPOSIT = 'retained_deposit';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'unit_amount' => 'integer',
        'amount' => 'integer',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Order lines are append-only: add a reversing line instead.'));
        static::deleting(fn () => throw new \LogicException('Order lines are append-only: add a reversing line instead.'));
    }

    public function order()
    {
        return $this->belongsTo(CustomOrder::class, 'custom_order_id')->withTrashed();
    }
}
