<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/** Shared definitions keep manager totals and the opened work list consistent. */
final class StudioWorkflow
{
    public const QUEUES = ['review', 'quotes', 'payment', 'fulfilment', 'production', 'dispatch', 'pickup'];

    public static function apply(Builder $query, ?string $queue): Builder
    {
        // Read through the order adapter (ReadsOrderState), so LEDGER_READS switches the queues too.
        return match ($queue) {
            'review' => $query->paidUp(false)->withLegacyStatus([OrderStatus::PAYMENT_FAILED, OrderStatus::AMOUNT_MISMATCH]),
            'quotes' => $query->paidUp(false)->ofKind('bespoke')->withLegacyStatus(['pending'])->where(\App\Models\CustomOrder::totalColumn(), '<=', 0),
            'payment' => $query->paidUp(false)->where(function ($q) {
                $q->withLegacyStatus([OrderStatus::PENDING_PAYMENT])
                    ->orWhere(fn ($q) => $q->ofKind('bespoke')->withLegacyStatus(OrderStatus::PRODUCTION_STAGES)->where(\App\Models\CustomOrder::totalColumn(), '>', 0));
            }),
            'fulfilment' => $query->paidUp()->withLegacyStatus([OrderStatus::PAID, 'pending']),
            'production' => $query->withLegacyStatus(['designing', 'sewing']),
            'dispatch' => $query->paidUp()->withLegacyStatus(['completed']),
            // Phase D2: waiting at the shop for the customer.
            'pickup' => $query->withLegacyStatus(['ready_for_pickup']),
            default => $query,
        };
    }
}
