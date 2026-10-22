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

    /**
     * A prompt Safaricom may have sent although our request timed out: we never
     * got its CheckoutRequestID, so it waits under a placeholder until its
     * callback arrives (matched by phone and amount) or it lapses.
     */
    public const UNCONFIRMED_PREFIX = 'unconfirmed-';

    /** Minutes a placeholder waits for its callback before it counts as never sent. */
    public const UNCONFIRMED_MINUTES = 30;

    public static function recordUnconfirmed(CustomOrder $order, string $phone, int $amount): self
    {
        return $order->paymentAttempts()->create([
            'checkout_request_id' => self::UNCONFIRMED_PREFIX . \Illuminate\Support\Str::uuid(),
            'phone' => $phone, 'amount' => $amount, 'status' => self::PENDING,
            'result_desc' => 'Safaricom did not answer in time: the prompt may or may not have been sent.',
        ]);
    }

    /**
     * A successful callback whose CheckoutRequestID we never stored: the
     * placeholder for the same phone and amount takes the real id, so the
     * payment settles its order like any other.
     */
    public static function adoptUnconfirmed(string $checkoutRequestId, ?string $phone, mixed $amount): ?self
    {
        if (! $phone || ! is_numeric($amount)) {
            return null;
        }
        $attempt = self::pending()->where('checkout_request_id', 'like', self::UNCONFIRMED_PREFIX . '%')
            ->where('phone', \App\Support\Phone::normalize((string) $phone))->where('amount', (int) round((float) $amount))
            ->where('created_at', '>=', now()->subMinutes(self::UNCONFIRMED_MINUTES))->oldest('id')->first();
        $attempt?->update(['checkout_request_id' => $checkoutRequestId, 'result_desc' => null]);

        return $attempt;
    }

    public function isUnconfirmed(): bool
    {
        return str_starts_with((string) $this->checkout_request_id, self::UNCONFIRMED_PREFIX);
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
