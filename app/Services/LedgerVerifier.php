<?php

namespace App\Services;

use App\Models\CustomOrder;
use App\Models\OrderPayment;
use App\Support\LegacyOrderMap;

/**
 * Compares orders' legacy columns with the split model and the ledger (design
 * §2.3). Used by serana:ledger:verify and, after every test, by the suite.
 */
class LedgerVerifier
{
    public const MAPPING = 'mapping';
    public const MONEY = 'money';

    /**
     * @param  list<string>  $checks  which groups to check
     * @return array<int, list<string>> order id => issues
     */
    public function differences(array $checks = [self::MAPPING, self::MONEY]): array
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

    /** @return list<string> */
    public function check(CustomOrder $order, array $checks = [self::MAPPING, self::MONEY]): array
    {
        $issues = [];

        if (in_array(self::MAPPING, $checks, true)) {
            foreach (LegacyOrderMap::columns($order->type, $order->status, $order->payment_method) as $column => $value) {
                if ($order->{$column} !== $value) {
                    $issues[] = "{$column}: legacy implies " . var_export($value, true) . ', stored ' . var_export($order->{$column}, true);
                }
            }
        }

        if (in_array(self::MONEY, $checks, true)) {
            $chargeable = (int) $order->lines()->sum('amount');
            $payments = (int) $order->payments()->where('type', OrderPayment::PAYMENT)->sum('amount');
            $refunds = (int) $order->payments()->where('type', OrderPayment::REFUND)->sum('amount');
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
