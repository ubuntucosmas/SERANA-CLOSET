<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A single STK push and its outcome. An order may accumulate several attempts
 * (retries, changed phone numbers); it is paid when any one of them succeeds.
 */
class PaymentAttempt extends Model
{
    public const PENDING = 'pending';
    public const PAID    = 'paid';
    public const FAILED  = 'failed';

    protected $fillable = [
        'custom_order_id',
        'checkout_request_id',
        'phone',
        'amount',
        'status',
        'result_code',
        'result_desc',
        'mpesa_receipt_number',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    /** Payment evidence must always resolve its order, even one Studio archived. */
    public function order()
    {
        return $this->belongsTo(CustomOrder::class, 'custom_order_id')->withTrashed();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }

    public function markPaid(?string $receiptNumber = null): void
    {
        $this->update([
            'status'               => self::PAID,
            'result_code'          => '0',
            'mpesa_receipt_number' => $receiptNumber ?? $this->mpesa_receipt_number,
        ]);
    }

    public function markFailed(?string $resultCode, ?string $resultDesc = null): void
    {
        $this->update([
            'status'      => self::FAILED,
            'result_code' => $resultCode,
            'result_desc' => $resultDesc ? mb_substr($resultDesc, 0, 255) : null,
        ]);
    }
}
