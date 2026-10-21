<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One physical change to stock (design §13.1): append-only, like the money
 * ledger. A mistake is corrected by another movement. Holds are not
 * movements: committing one writes a sale, releasing one writes nothing.
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    public const RECEIVE = 'receive';
    public const SALE = 'sale';
    public const RETURN = 'return';
    public const COUNT_CORRECTION = 'count_correction';
    public const DAMAGE = 'damage';
    public const TRANSFER_OUT = 'transfer_out';
    public const TRANSFER_IN = 'transfer_in';

    public const TYPES = [self::RECEIVE, self::SALE, self::RETURN, self::COUNT_CORRECTION, self::DAMAGE, self::TRANSFER_OUT, self::TRANSFER_IN];

    protected $guarded = [];

    protected $casts = ['quantity' => 'integer', 'occurred_at' => 'datetime', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Stock movements are append-only: record a correcting movement instead.'));
        static::deleting(fn () => throw new \LogicException('Stock movements are append-only: record a correcting movement instead.'));
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
