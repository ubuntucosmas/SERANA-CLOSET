<?php

namespace App\Services;

use App\Models\CustomOrder;
use App\Models\OrderPayment;
use App\Support\LegacyOrderMap;
use App\Support\OrderStatus;

/**
 * Compares orders' legacy columns with the split model and the ledger (design
 * §2.3). Used by serana:ledger:verify and, after every test, by the suite.
 */
class LedgerVerifier
{
    public const MAPPING = 'mapping';
    public const MONEY = 'money';
    /** Every read adapter (ReadsOrderState) gives the same answer from either source. */
    public const READS = 'reads';

    /**
     * The legacy-status sets the app actually filters on (scopeWithLegacyStatus
     * callers). Compared as sets: legacy allowed "Paid" or "pending" for a paid
     * shop order not yet started, and every caller treats the two alike.
     */
    private const STATUS_SETS = [
        'awaiting payment' => [OrderStatus::PENDING_PAYMENT, OrderStatus::PAYMENT_FAILED],
        'pending payment' => [OrderStatus::PENDING_PAYMENT],
        'reconcilable' => [OrderStatus::PENDING_PAYMENT, OrderStatus::EXPIRED],
        'payment problems' => [OrderStatus::PAYMENT_FAILED, OrderStatus::AMOUNT_MISMATCH],
        'ready for fulfilment' => [OrderStatus::PAID, 'pending'],
        'in production' => ['designing', 'sewing'],
        'completed' => ['completed'],
        'shipped' => ['shipped'],
        'not demand' => [OrderStatus::EXPIRED, OrderStatus::CANCELLED],
        'attention' => [OrderStatus::PENDING_PAYMENT, OrderStatus::PAYMENT_FAILED, OrderStatus::AMOUNT_MISMATCH, OrderStatus::PAID, 'pending'],
    ];

    /**
     * @param  list<string>  $checks  which groups to check
     * @return array<int, list<string>> order id => issues
     */
    public function differences(array $checks = [self::MAPPING, self::MONEY, self::READS]): array
    {
        $differences = [];
        CustomOrder::withTrashed()->orderBy('id')->chunkById(200, function ($orders) use (&$differences, $checks) {
            foreach ($orders as $order) {
                $issues = $this->check($order, $checks);
                if ($issues !== []) {
                    $differences[$order->id] = $issues;
                }
            }
        });

        return $differences;
    }

    /** Does each list query (scope) include this order in both modes alike? @return list<string> */
    private function scopeDifferences(CustomOrder $order): array
    {
        $queries = ['paidUp' => fn ($q) => $q->paidUp(), 'notPaidUp' => fn ($q) => $q->paidUp(false)];
        foreach (['shop', 'drop', 'bespoke'] as $kind) {
            $queries["ofKind({$kind})"] = fn ($q) => $q->ofKind($kind);
        }
        foreach (self::STATUS_SETS as $name => $statuses) {
            $queries["withLegacyStatus({$name})"] = fn ($q) => $q->withLegacyStatus($statuses);
        }
        $queries['quotes (bespoke, pending)'] = fn ($q) => $q->ofKind('bespoke')->withLegacyStatus(['pending']);
        $queries['bespoke in any stage'] = fn ($q) => $q->ofKind('bespoke')->withLegacyStatus(OrderStatus::PRODUCTION_STAGES);

        $issues = [];
        $original = config('serana.ledger_reads');
        try {
            foreach ($queries as $name => $scope) {
                $in = [];
                foreach ([false, true] as $mode) {
                    config(['serana.ledger_reads' => $mode]);
                    $in[] = $scope(CustomOrder::withTrashed()->whereKey($order->id))->exists();
                }
                if ($in[0] !== $in[1]) {
                    $issues[] = "scope {$name}: legacy " . var_export($in[0], true) . ', ledger ' . var_export($in[1], true);
                }
            }
        } finally {
            config(['serana.ledger_reads' => $original]);
        }

        return $issues;
    }

    /** @return list<string> */
    public function check(CustomOrder $order, array $checks = [self::MAPPING, self::MONEY, self::READS]): array
    {
        $issues = [];

        if (in_array(self::READS, $checks, true)) {
            foreach (['isPaidUp', 'orderKind', 'isExpired', 'isCancelled', 'hasAmountMismatch', 'total', 'legacyStatus'] as $read) {
                $legacy = $order->{$read}(false);
                $ledger = $order->{$read}(true);
                // A paid shop/drop order not yet started: legacy "pending" and "Paid" mean the same.
                if ($read === 'legacyStatus' && $legacy === 'pending' && $order->isCommerce(false) && $order->isPaidUp(false)) {
                    $legacy = OrderStatus::PAID;
                }
                if ($legacy !== $ledger) {
                    $issues[] = "{$read}(): legacy " . var_export($legacy, true) . ', ledger ' . var_export($ledger, true);
                }
            }
            $issues = [...$issues, ...$this->scopeDifferences($order)];
        }

        if (in_array(self::MAPPING, $checks, true)) {
            foreach (LegacyOrderMap::columns($order->type, $order->status, $order->payment_method) as $column => $value) {
                // Legacy has one "needs review" status for every open payment problem.
                if ($column === 'resolution' && ($value !== 'none') === (($order->resolution ?? 'none') !== 'none')) {
                    continue;
                }
                if ($order->{$column} !== $value) {
                    $issues[] = "{$column}: legacy implies " . var_export($value, true) . ', stored ' . var_export($order->{$column}, true);
                }
            }
        }

        if (in_array(self::MONEY, $checks, true)) {
            $chargeable = (int) $order->lines()->sum('amount');
            ['payments' => $payments, 'refunds' => $refunds] = $order->ledgerSums();
            try {
                $derived = OrderLedger::derive($chargeable, $payments, $refunds, $order->resolution);
            } catch (\DomainException $e) {
                return [...$issues, $e->getMessage()];
            }

            foreach (['chargeable_total', 'amount_paid', 'balance_due', 'payment_status'] as $column) {
                if ($order->{$column} !== $derived[$column]) {
                    $issues[] = "{$column}: ledger gives " . var_export($derived[$column], true) . ', stored ' . var_export($order->{$column}, true);
                }
            }
            if ($derived['is_paid'] !== (bool) $order->is_paid) {
                $issues[] = 'is_paid: ledger gives ' . var_export($derived['is_paid'], true) . ', legacy ' . var_export((bool) $order->is_paid, true);
            }
            if ($chargeable !== (int) round((float) $order->price_quoted)) {
                $issues[] = "lines total {$chargeable}, legacy price_quoted {$order->price_quoted}";
            }
        }

        return $issues;
    }
}
