<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One money movement on an order: a payment in or a refund out (design §2.1).
 * Append-only. Written only by OrderSettlementService; corrections are new
 * rows, never edits.
 */
class OrderPayment extends Model
{
    public const UPDATED_AT = null;

    public const PAYMENT = 'payment';
    public const REFUND = 'refund';

    public const METHODS = ['mpesa_stk', 'mpesa_manual', 'cash', 'card', 'bank', 'credit'];

    /**
     * The namespace a reference is unique in (owner decision, Phase C): one
     * M-Pesa code is one transaction however it reached the ledger. The
     * database computes the same value (reference_scope, generated column).
     */
    public static function scopeFor(string $method): string
    {
        return in_array($method, ['mpesa_stk', 'mpesa_manual'], true) ? 'mpesa' : $method;
    }

    protected $guarded = [];

    protected $casts = [
        'amount' => 'integer',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Ledger rows are append-only: book a reversing entry instead.'));
        static::deleting(fn () => throw new \LogicException('Ledger rows are append-only: book a reversing entry instead.'));
    }

    public function order()
    {
        return $this->belongsTo(CustomOrder::class, 'custom_order_id')->withTrashed();
    }

    public function attempt()
    {
        return $this->belongsTo(PaymentAttempt::class, 'payment_attempt_id');
    }
}
