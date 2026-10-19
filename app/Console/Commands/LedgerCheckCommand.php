<?php

namespace App\Console\Commands;

use App\Models\CustomOrder;
use App\Models\OrderPayment;
use App\Services\LedgerBackfill;
use App\Services\OrderLedger;
use Illuminate\Console\Command;

/**
 * The money gate before LEDGER_READS is turned on in production: for every
 * order (archived included), the legacy money fields (is_paid, price_quoted,
 * paid_at, receipt number) must agree with what the ledger derives (amount
 * paid, balance due, payment status), and the stored money columns must
 * equal the derivation. Prints each mismatch with the order reference and
 * both values; exits non-zero if there is any.
 */
class LedgerCheckCommand extends Command
{
    protected $signature = 'serana:ledger-check {--limit=100 : How many mismatches to print}';

    protected $description = 'Check every order\'s legacy money fields against the payments ledger';

    public function handle(): int
    {
        ['orders' => $orders, 'mismatches' => $mismatches] = $this->scan();
        $mismatched = count($mismatches);
        $printed = 0;
        $limit = (int) $this->option('limit');
        foreach ($mismatches as $cipher => $issues) {
            foreach ($issues as $issue) {
                if ($printed++ < $limit) {
                    $this->line("  {$cipher}  {$issue}");
                }
            }
        }

        if ($mismatched > 0) {
            $this->error("Ledger check failed: {$orders} order(s), {$mismatched} mismatch(es). Do not turn on LEDGER_READS.");

            return self::FAILURE;
        }

        $this->info("Ledger check passed: {$orders} order(s), 0 mismatch(es).");

        return self::SUCCESS;
    }

    /**
     * Every order, archived included. Also used by serana:preflight.
     *
     * @return array{orders: int, mismatches: array<string, list<string>>} reference => issues
     */
    public function scan(): array
    {
        $orders = 0;
        $mismatches = [];
        CustomOrder::withTrashed()->orderBy('id')->chunkById(200, function ($chunk) use (&$orders, &$mismatches) {
            foreach ($chunk as $order) {
                $orders++;
                if (($issues = $this->issues($order)) !== []) {
                    $mismatches[$order->cipher] = $issues;
                }
            }
        });

        return ['orders' => $orders, 'mismatches' => $mismatches];
    }

    /** @return list<string> */
    private function issues(CustomOrder $order): array
    {
        $money = fn (int $v) => 'KSh ' . number_format($v);
        $chargeable = (int) $order->lines()->sum('amount');
        $payments = (int) $order->payments()->where('type', OrderPayment::PAYMENT)->sum('amount');
        $refunds = (int) $order->payments()->where('type', OrderPayment::REFUND)->sum('amount');
        try {
            $ledger = OrderLedger::derive($chargeable, $payments, $refunds, $order->resolution ?? 'none');
        } catch (\DomainException $e) {
            return [$e->getMessage()];
        }

        $issues = [];
        $legacyPaid = (bool) $order->is_paid;
        if ($legacyPaid !== $ledger['is_paid']) {
            $issues[] = 'is_paid: legacy ' . var_export($legacyPaid, true) . ', ledger ' . var_export($ledger['is_paid'], true)
                . " (paid {$money($ledger['amount_paid'])} of {$money($chargeable)})";
        }

        $price = (int) round((float) $order->price_quoted);
        if ($price !== $chargeable) {
            $issues[] = "price_quoted: legacy {$price}, ledger {$chargeable}";
        }

        if ($ledger['is_paid'] && $order->paid_at === null) {
            $issues[] = 'paid_at: legacy none, ledger paid';
        } elseif (! $ledger['is_paid'] && ! $legacyPaid && $order->paid_at !== null) {
            $issues[] = 'paid_at: legacy set, ledger not paid';
        }

        if ($order->mpesa_receipt_number !== null && $order->mpesa_receipt_number !== '' && ! $this->ledgerHasReference($order, $order->mpesa_receipt_number)) {
            $issues[] = "receipt {$order->mpesa_receipt_number}: legacy has it, ledger has no payment with that reference";
        }

        foreach (['chargeable_total', 'amount_paid', 'balance_due', 'payment_status'] as $column) {
            if ($order->{$column} !== $ledger[$column]) {
                $issues[] = "{$column}: stored " . $order->{$column} . ', ledger ' . $ledger[$column];
            }
        }

        return $issues;
    }

    /** The reference as booked, or as marked when it was already used on another order. */
    private function ledgerHasReference(CustomOrder $order, string $reference): bool
    {
        return $order->payments()->where(fn ($q) => $q->where('external_reference', $reference)
            ->orWhere('external_reference', $reference . LedgerBackfill::DUPLICATE_MARK . $order->id))->exists();
    }
}
