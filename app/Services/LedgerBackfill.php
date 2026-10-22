<?php

namespace App\Services;

use App\Models\CustomOrder;
use App\Models\OrderLine;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Support\Actor;
use App\Support\LegacyOrderMap;
use Illuminate\Support\Facades\DB;

/**
 * Builds an order's lines, ledger and split columns from its legacy fields
 * (design §2.3). Idempotent: lines and payments are only created when the
 * order has none. Never touches the legacy columns or updated_at.
 *
 * Used by the backfill migration, by serana:ledger:backfill, and for each new
 * order while the legacy columns are still the input (CustomOrder::created).
 *
 * Migration 000004 runs this code, so it may only write columns created by
 * migration 000003 or earlier: a column added later needs its own migration
 * and backfill, or fresh installs fail at 000004.
 */
class LedgerBackfill
{
    /** @return array{adjusted: bool, reference_renamed: bool} what needed attention */
    public function order(CustomOrder $order): array
    {
        $report = ['adjusted' => false, 'reference_renamed' => false];

        DB::transaction(function () use ($order, &$report) {
            $columns = LegacyOrderMap::columns($order->type, $order->status, $order->payment_method);

            if (! $order->lines()->exists()) {
                $report['adjusted'] = $this->lines($order, $columns['kind']);
            }
            if (! $order->payments()->exists()) {
                $report['reference_renamed'] = $this->payments($order);
            }

            $chargeable = (int) $order->lines()->sum('amount');
            ['payments' => $payments, 'refunds' => $refunds] = $order->ledgerSums();
            $derived = OrderLedger::derive($chargeable, $payments, $refunds, $columns['resolution']);
            unset($derived['is_paid']); // legacy column: left as it is

            DB::table('custom_orders')->where('id', $order->id)->update($columns + $derived);
            // The caller's instance sees what was stored (a new order is read right after create).
            $order->forceFill($columns + $derived)->syncOriginalAttributes(array_keys($columns + $derived));
        });

        return $report;
    }

    /** Backfill every order, archived ones included. */
    public function all(): array
    {
        $totals = ['orders' => 0, 'adjusted' => 0, 'reference_renamed' => 0];
        CustomOrder::withTrashed()->orderBy('id')->chunkById(200, function ($orders) use (&$totals) {
            foreach ($orders as $order) {
                $report = $this->order($order);
                $totals['orders']++;
                $totals['adjusted'] += (int) $report['adjusted'];
                $totals['reference_renamed'] += (int) $report['reference_renamed'];
            }
        });

        return $totals;
    }

    /** Returns true when an adjustment line had to reconcile the items with the stored total. */
    private function lines(CustomOrder $order, string $kind): bool
    {
        $total = (int) round((float) $order->price_quoted);
        $sum = 0;

        if ($kind === 'shop') {
            foreach ($order->items_json ?? [] as $key => $item) {
                if ($key === 'precision_sizing' || ! is_array($item) || ! isset($item['name'], $item['price'])) {
                    continue;
                }
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $unit = (int) round((float) $item['price']);
                $productId = isset($item['id']) && Product::withTrashed()->whereKey($item['id'])->exists() ? (int) $item['id'] : null;
                $this->line($order, OrderLine::ITEM, $item['name'], $quantity, $unit, null, $productId, $item['options'] ?? null);
                $sum += $quantity * $unit;
            }
        } elseif ($total > 0) {
            $description = $kind === 'drop'
                ? 'Drop: ' . ($order->items_json['drop'] ?? 'reservation') . (! empty($order->items_json['size']) ? " ({$order->items_json['size']})" : '')
                : 'Custom order quote';
            $this->line($order, OrderLine::ITEM, $description, 1, $total);
            $sum = $total;
        }

        if ($sum !== $total) {
            $this->line($order, OrderLine::ADJUSTMENT, 'Backfill adjustment', 1, $total - $sum, 'backfill: items did not add up to the stored total');

            return true;
        }

        return false;
    }

    private function line(CustomOrder $order, string $type, string $description, int $quantity, int $unit, ?string $reason = null, ?int $productId = null, ?array $meta = null): void
    {
        OrderLine::create([
            'custom_order_id' => $order->id,
            'line_type' => $type,
            'product_id' => $productId,
            'description' => mb_substr($description, 0, 255),
            'quantity' => $quantity,
            'unit_amount' => $unit,
            'amount' => $quantity * $unit,
            'actor_type' => Actor::SYSTEM,
            'reason' => $reason,
            'meta' => $meta,
        ]);
    }

    /**
     * One reference can be booked once per method (N4). Legacy data can hold a
     * reference on two orders (seen locally: a stubbed STK receipt on two
     * paid attempts). Both payments are booked; the later one is marked so the
     * owner can review it (serana:ledger:verify lists them).
     *
     * @return array{0: ?string, 1: bool} [reference to store, whether it was marked]
     */
    private function uniqueReference(string $method, ?string $reference, int $orderId): array
    {
        if ($reference === null || ! OrderPayment::where('reference_scope', OrderPayment::scopeFor($method))->where('external_reference', $reference)->exists()) {
            return [$reference, false];
        }

        return ["{$reference}" . self::DUPLICATE_MARK . $orderId, true];
    }

    public const DUPLICATE_MARK = '#dup-order-';

    /** Returns true when a reference was already booked on another order and had to be marked. */
    private function payments(CustomOrder $order): bool
    {
        $marked = false;
        $paidAttempts = $order->paymentAttempts()->where('status', 'paid')->orderBy('id')->get();
        foreach ($paidAttempts as $attempt) {
            [$reference, $wasMarked] = $this->uniqueReference('mpesa_stk', $attempt->mpesa_receipt_number, $order->id);
            $marked = $marked || $wasMarked;
            OrderPayment::create([
                'custom_order_id' => $order->id, 'type' => OrderPayment::PAYMENT, 'amount' => $attempt->amount,
                'method' => 'mpesa_stk', 'external_reference' => $reference,
                'payment_attempt_id' => $attempt->id, 'actor_type' => Actor::SAFARICOM,
                'reason' => 'backfill: paid STK attempt', 'occurred_at' => $attempt->updated_at ?? now(),
            ]);
        }

        $total = (int) round((float) $order->price_quoted);
        if ($paidAttempts->isNotEmpty() || ! $order->is_paid || $total <= 0) {
            return $marked;
        }

        // Paid outside STK (WhatsApp confirmation, bespoke "record payment", pre-attempt M-Pesa).
        [$reference, $wasMarked] = $this->uniqueReference('mpesa_manual', $order->mpesa_receipt_number ?: "legacy-{$order->id}", $order->id);
        OrderPayment::create([
            'custom_order_id' => $order->id, 'type' => OrderPayment::PAYMENT, 'amount' => $total,
            'method' => 'mpesa_manual', 'external_reference' => $reference,
            'actor_type' => Actor::SYSTEM, 'reason' => 'backfill: is_paid',
            'occurred_at' => $order->paid_at ?? $order->updated_at ?? now(),
        ]);

        return $wasMarked;
    }
}
