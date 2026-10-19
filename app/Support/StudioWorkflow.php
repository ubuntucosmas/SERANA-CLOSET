<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/** Shared definitions keep manager totals and the opened work list consistent. */
final class StudioWorkflow
{
    public const QUEUES = ['review', 'quotes', 'payment', 'fulfilment', 'production', 'dispatch'];

    public static function apply(Builder $query, ?string $queue): Builder
    {
        return match ($queue) {
            'review' => $query->where('is_paid', false)->whereIn('status', [OrderStatus::PAYMENT_FAILED, OrderStatus::AMOUNT_MISMATCH]),
            'quotes' => $query->where('is_paid', false)->whereNotIn('type', ['standard', 'drop'])->where('status', 'pending')->where('price_quoted', '<=', 0),
            'payment' => $query->where('is_paid', false)->where(function ($q) {
                $q->where('status', OrderStatus::PENDING_PAYMENT)->orWhere(fn ($q) => $q->whereNotIn('type', ['standard', 'drop'])->whereIn('status', OrderStatus::PRODUCTION_STAGES)->where('price_quoted', '>', 0));
            }),
            'fulfilment' => $query->where('is_paid', true)->whereIn('status', [OrderStatus::PAID, 'pending']),
            'production' => $query->whereIn('status', ['designing', 'sewing']),
            'dispatch' => $query->where('is_paid', true)->where('status', 'completed'),
            default => $query,
        };
    }
}
