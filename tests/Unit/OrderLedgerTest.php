<?php

namespace Tests\Unit;

use App\Services\OrderLedger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The pure rule that turns an order's lines and ledger into its money figures
 * and payment_status (design §2.2, N1). Everything stored is recomputed from this.
 */
class OrderLedgerTest extends TestCase
{
    /** [chargeable, payments, refunds, resolution, expected payment_status, amount_paid, balance_due, legacy is_paid] */
    public static function cases(): array
    {
        return [
            'nothing yet' => [5000, 0, 0, 'none', 'unpaid', 0, 5000, false],
            'quote not set yet' => [0, 0, 0, 'none', 'unpaid', 0, 0, false],
            'deposit' => [10000, 5000, 0, 'none', 'deposit_paid', 5000, 5000, false],
            'paid exactly' => [5000, 5000, 0, 'none', 'paid', 5000, 0, true],
            'duplicate payment' => [5000, 10000, 0, 'none', 'refund_due', 10000, -5000, true],
            'duplicate refunded' => [5000, 10000, 5000, 'none', 'paid', 5000, 0, true],
            'cancelled after paying' => [0, 5000, 0, 'none', 'refund_due', 5000, -5000, false],
            'cancelled and refunded' => [0, 5000, 5000, 'none', 'refunded', 0, 0, false],
            'deposit retained on cancel' => [5000, 5000, 0, 'none', 'paid', 5000, 0, true],
            'legacy mismatch, nothing booked' => [5000, 0, 0, 'amount_mismatch', 'amount_mismatch', 0, 5000, false],
            'mismatch with money booked' => [5000, 6000, 0, 'amount_mismatch', 'amount_mismatch', 6000, -1000, false],
            'late payment, stock gone' => [5000, 5000, 0, 'needs_resolution', 'needs_resolution', 5000, 0, false],
        ];
    }

    #[DataProvider('cases')]
    public function test_derivation(int $chargeable, int $payments, int $refunds, string $resolution, string $status, int $paid, int $balance, bool $isPaid): void
    {
        $derived = OrderLedger::derive($chargeable, $payments, $refunds, $resolution);

        $this->assertSame($status, $derived['payment_status']);
        $this->assertSame($paid, $derived['amount_paid']);
        $this->assertSame($balance, $derived['balance_due']);
        $this->assertSame($chargeable, $derived['chargeable_total']);
        $this->assertSame($isPaid, $derived['is_paid']);
    }

    public function test_refunds_can_never_exceed_payments(): void
    {
        $this->expectException(\DomainException::class);
        OrderLedger::derive(5000, 5000, 6000, 'none');
    }

    /** Property: for any amounts, the figures obey N1 and the status matches its definition. */
    public function test_any_combination_obeys_the_ledger_identities(): void
    {
        mt_srand(20261019);
        for ($i = 0; $i < 2000; $i++) {
            $chargeable = mt_rand(0, 4) * 2500;
            $payments = mt_rand(0, 4) * 2500;
            $refunds = mt_rand(0, intdiv($payments, 2500)) * 2500;
            $d = OrderLedger::derive($chargeable, $payments, $refunds, 'none');

            $net = $payments - $refunds;
            $this->assertSame($net, $d['amount_paid']);
            $this->assertSame($chargeable - $net, $d['balance_due']);
            $expected = match (true) {
                $net > $chargeable => 'refund_due',
                $chargeable > 0 && $net === $chargeable => 'paid',
                $net > 0 => 'deposit_paid',
                $refunds > 0 && $chargeable === 0 => 'refunded',
                default => 'unpaid',
            };
            $this->assertSame($expected, $d['payment_status'], json_encode(compact('chargeable', 'payments', 'refunds')));
        }
    }
}
