<?php

namespace App\Support;

/**
 * How the legacy overloaded columns (status, type, payment_method) map to the
 * split model (design §2.3). One mapping for the backfill, for new orders
 * during the dual-run, for every legacy status write, and for verify.
 */
final class LegacyOrderMap
{
    /** standard → shop, drop → drop, anything else is a bespoke garment label. */
    public static function kind(?string $type): string
    {
        return match ($type) {
            'standard' => 'shop',
            'drop' => 'drop',
            default => 'bespoke',
        };
    }

    public static function garment(?string $type): ?string
    {
        return self::kind($type) === 'bespoke' && $type !== null && $type !== 'custom' ? $type : null;
    }

    public static function channel(?string $paymentMethod): string
    {
        return match ($paymentMethod) {
            PaymentSettings::WHATSAPP => 'whatsapp',
            'counter' => 'counter', // sold at the till (Phase E)
            default => 'online',
        };
    }

    public static function orderState(?string $status): string
    {
        return match ($status) {
            OrderStatus::EXPIRED => 'expired',
            OrderStatus::CANCELLED => 'cancelled',
            default => 'open',
        };
    }

    public static function fulfilment(?string $status): string
    {
        return in_array($status, OrderStatus::PRODUCTION_STAGES, true) ? $status : 'pending';
    }

    public static function resolution(?string $status): string
    {
        return $status === OrderStatus::AMOUNT_MISMATCH ? 'amount_mismatch' : 'none';
    }

    /**
     * Dual-write (design §2.3, A3): every legacy status write merges this into
     * the same UPDATE, so the split columns can never lag behind, including
     * on bulk and conditional updates that bypass model events.
     */
    public static function forStatus(string $status): array
    {
        return [
            'status' => $status,
            'order_state' => self::orderState($status),
            'fulfilment_status' => self::fulfilment($status),
            'resolution' => self::resolution($status),
            'payment_failed' => $status === OrderStatus::PAYMENT_FAILED,
        ];
    }

    /** All split columns implied by a legacy row. */
    public static function columns(?string $type, ?string $status, ?string $paymentMethod): array
    {
        return [
            'kind' => self::kind($type),
            'garment' => self::garment($type),
            'channel' => self::channel($paymentMethod),
            'order_state' => self::orderState($status),
            'fulfilment_status' => self::fulfilment($status),
            'resolution' => self::resolution($status),
            'payment_failed' => $status === OrderStatus::PAYMENT_FAILED,
        ];
    }
}
