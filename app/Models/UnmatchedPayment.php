<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** M-Pesa money that matches no order yet; see the create_unmatched_payments migration. */
class UnmatchedPayment extends Model
{
    public const NEEDS_REVIEW = 'needs_review';
    public const MATCHED = 'matched';
    public const REFUND_REQUIRED = 'refund_required';
    public const REFUNDED = 'refunded';

    /** Still money someone must act on. */
    public const OPEN = [self::NEEDS_REVIEW, self::REFUND_REQUIRED];

    protected $fillable = ['source', 'method', 'checkout_request_id', 'receipt', 'amount', 'phone', 'paid_at', 'state',
        'custom_order_id', 'order_payment_id', 'notes', 'refund_reference', 'resolved_by', 'resolved_at', 'raw'];

    protected $casts = ['amount' => 'integer', 'paid_at' => 'datetime', 'resolved_at' => 'datetime', 'raw' => 'array'];

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('state', self::OPEN);
    }

    public function order()
    {
        return $this->belongsTo(CustomOrder::class, 'custom_order_id')->withTrashed();
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
