<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cash into or out of a till drawer (design §15.2): append-only, like the
 * money and stock ledgers. Positive is into the drawer.
 */
class CashMovement extends Model
{
    public const UPDATED_AT = null;

    public const FLOAT = 'float';
    public const SALE = 'sale';
    public const REFUND = 'refund';
    public const PAYOUT = 'payout';
    public const PICKUP = 'pickup';

    protected $guarded = [];

    protected $casts = ['amount' => 'integer', 'occurred_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Cash movements are append-only: record a correcting movement instead.'));
        static::deleting(fn () => throw new \LogicException('Cash movements are append-only: record a correcting movement instead.'));
    }

    public function session()
    {
        return $this->belongsTo(TillSession::class, 'till_session_id');
    }
}
