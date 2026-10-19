<?php

namespace App\Services;

/**
 * The pure rule from an order's lines and ledger to its money figures and
 * payment_status (design §2.2, invariant N1). Stored copies on the order are
 * always recomputed from this, never written by hand.
 *
 *   amount_paid  = payments − refunds
 *   balance_due  = chargeable_total − amount_paid   (negative = we owe the customer)
 *
 * Status by the numbers: refund_due (more paid than owed), paid (exactly what
 * is owed, and something is owed), deposit_paid (part of it), refunded
 * (nothing owed any more and the money went back), unpaid. A resolution flag
 * (needs_resolution, amount_mismatch, waiting_restock) takes precedence.
 */
final class OrderLedger
{
    public const RESOLUTION_NONE = 'none';

    /** @return array{chargeable_total: int, amount_paid: int, balance_due: int, payment_status: string, is_paid: bool} */
    public static function derive(int $chargeable, int $payments, int $refunds, string $resolution = self::RESOLUTION_NONE): array
    {
        if ($refunds > $payments) {
            throw new \DomainException('Refunds can never exceed payments (N2).');
        }

        $net = $payments - $refunds;
        $status = match (true) {
            $net > $chargeable => 'refund_due',
            $chargeable > 0 && $net === $chargeable => 'paid',
            $net > 0 => 'deposit_paid',
            $refunds > 0 && $chargeable === 0 => 'refunded',
            default => 'unpaid',
        };

        return [
            'chargeable_total' => $chargeable,
            'amount_paid' => $net,
            'balance_due' => $chargeable - $net,
            'payment_status' => $resolution !== self::RESOLUTION_NONE ? $resolution : $status,
            // The legacy flag, kept in sync until reads cut over: fully paid,
            // nothing in dispute.
            'is_paid' => $resolution === self::RESOLUTION_NONE && $chargeable > 0 && $net >= $chargeable,
        ];
    }
}
