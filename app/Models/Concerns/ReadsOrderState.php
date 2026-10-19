<?php

namespace App\Models\Concerns;

use App\Support\OrderStatus;
use App\Support\PaymentSettings;
use Illuminate\Database\Eloquent\Builder;

/**
 * How the app reads an order's state (design §2.3 step A4). Every answer comes
 * from the legacy columns (status, type, is_paid, price_quoted) or, with
 * config('serana.ledger_reads') on, from the split columns and the ledger.
 * Both are written on every change and checked after every test
 * (LedgerVerifier::READS), so the switch changes nothing but the source.
 */
trait ReadsOrderState
{
    public static function ledgerReads(): bool
    {
        return (bool) config('serana.ledger_reads');
    }

    /** Fully paid, nothing in dispute (the meaning of the legacy is_paid). */
    public function isPaidUp(?bool $ledger = null): bool
    {
        return ($ledger ?? self::ledgerReads())
            ? $this->resolution === 'none' && $this->chargeable_total > 0 && $this->balance_due <= 0
            : (bool) $this->is_paid;
    }

    public function orderKind(?bool $ledger = null): string
    {
        return ($ledger ?? self::ledgerReads()) ? (string) $this->kind : \App\Support\LegacyOrderMap::kind($this->type);
    }

    public function isShop(?bool $ledger = null): bool
    {
        return $this->orderKind($ledger) === 'shop';
    }

    public function isDrop(?bool $ledger = null): bool
    {
        return $this->orderKind($ledger) === 'drop';
    }

    public function isBespoke(?bool $ledger = null): bool
    {
        return $this->orderKind($ledger) === 'bespoke';
    }

    /** Shop checkout or Drop reservation: paid in full through the payment flow. */
    public function isCommerce(?bool $ledger = null): bool
    {
        return in_array($this->orderKind($ledger), ['shop', 'drop'], true);
    }

    public function isExpired(?bool $ledger = null): bool
    {
        return ($ledger ?? self::ledgerReads()) ? $this->order_state === 'expired' : $this->status === OrderStatus::EXPIRED;
    }

    public function isCancelled(?bool $ledger = null): bool
    {
        return ($ledger ?? self::ledgerReads()) ? $this->order_state === 'cancelled' : $this->status === OrderStatus::CANCELLED;
    }

    public function hasAmountMismatch(?bool $ledger = null): bool
    {
        // Ledger: any open payment problem (mismatch, code conflict, late payment...) needs the studio.
        return ($ledger ?? self::ledgerReads()) ? ($this->resolution ?? 'none') !== 'none' : $this->status === OrderStatus::AMOUNT_MISMATCH;
    }

    /** The legacy type vocabulary (standard, drop, or the garment) for screens that still use it. */
    public function legacyType(?bool $ledger = null): string
    {
        if (! ($ledger ?? self::ledgerReads())) {
            return (string) $this->type;
        }

        return match ($this->kind) {
            'shop' => 'standard',
            'drop' => 'drop',
            default => $this->garment ?? 'custom',
        };
    }

    /** The column that holds the order total, for sums in list queries. */
    public static function totalColumn(): string
    {
        return self::ledgerReads() ? 'chargeable_total' : 'price_quoted';
    }

    /**
     * Legacy guard: a stored price with cents cannot be charged by M-Pesa or held
     * by the whole-KES ledger. Every price is now whole at entry (catalogue and
     * drops); this goes with price_quoted in A5.
     */
    public function hasFractionalTotal(): bool
    {
        return (float) $this->price_quoted !== floor((float) $this->price_quoted);
    }

    /** What the order costs, in whole KES. */
    public function total(?bool $ledger = null): int
    {
        return ($ledger ?? self::ledgerReads()) ? (int) $this->chargeable_total : (int) round((float) $this->price_quoted);
    }

    /**
     * The order's status in the legacy vocabulary, for code and screens that
     * still speak it. With ledger reads it is rebuilt from the split columns.
     * A paid shop or drop order not yet started reads "Paid" (legacy also
     * allowed "pending" there; every caller treats the two alike).
     */
    public function legacyStatus(?bool $ledger = null): string
    {
        if (! ($ledger ?? self::ledgerReads())) {
            return (string) $this->status;
        }

        return match (true) {
            $this->fulfilment_status !== 'pending' => $this->fulfilment_status,
            $this->order_state === 'expired' => OrderStatus::EXPIRED,
            $this->order_state === 'cancelled' => OrderStatus::CANCELLED,
            // Any open payment problem reads as the legacy "needs review" status.
            ($this->resolution ?? 'none') !== 'none' => OrderStatus::AMOUNT_MISMATCH,
            $this->kind === 'bespoke' => 'pending',
            $this->isPaidUp(true) => OrderStatus::PAID,
            (bool) $this->payment_failed => OrderStatus::PAYMENT_FAILED,
            default => OrderStatus::PENDING_PAYMENT,
        };
    }

    // ---- Query scopes: the same answers, for lists and jobs. ----

    public function scopePaidUp(Builder $query, bool $paid = true): Builder
    {
        if (! self::ledgerReads()) {
            return $query->where('is_paid', $paid);
        }

        return $paid
            ? $query->where('resolution', 'none')->where('chargeable_total', '>', 0)->where('balance_due', '<=', 0)
            : $query->where(fn ($q) => $q->where('resolution', '!=', 'none')->orWhere('chargeable_total', '<=', 0)->orWhere('balance_due', '>', 0));
    }

    /** @param  string  ...$kinds  shop | bespoke | drop */
    public function scopeOfKind(Builder $query, string ...$kinds): Builder
    {
        if (self::ledgerReads()) {
            return $query->whereIn('kind', $kinds);
        }

        return $query->where(function ($q) use ($kinds) {
            $types = array_values(array_filter(['shop' => 'standard', 'drop' => 'drop'], fn ($kind) => in_array($kind, $kinds, true), ARRAY_FILTER_USE_KEY));
            if ($types !== []) {
                $q->orWhereIn('type', $types);
            }
            if (in_array('bespoke', $kinds, true)) {
                $q->orWhereNotIn('type', ['standard', 'drop']);
            }
        });
    }

    /**
     * Orders whose legacy status is one of $statuses, answered from the split
     * columns under ledger reads (the inverse of LegacyOrderMap, as legacyStatus()).
     *
     * @param  list<string>  $statuses
     */
    public function scopeWithLegacyStatus(Builder $query, array $statuses): Builder
    {
        if (! self::ledgerReads()) {
            return $query->whereIn('status', $statuses);
        }

        return $query->where(function ($any) use ($statuses) {
            foreach ($statuses as $status) {
                $any->orWhere(fn ($q) => $this->ledgerStatus($q, $status));
            }
        });
    }

    private function ledgerStatus(Builder $q, string $status): void
    {
        $unsettled = fn ($q) => $q->where('fulfilment_status', 'pending')->where('order_state', 'open')->where('resolution', 'none');
        $paidUp = fn ($q) => $q->where('chargeable_total', '>', 0)->where('balance_due', '<=', 0);
        $notPaidUp = fn ($q) => $q->where(fn ($p) => $p->where('chargeable_total', '<=', 0)->orWhere('balance_due', '>', 0));
        $commerce = fn ($q) => $q->where('kind', '!=', 'bespoke');

        match ($status) {
            OrderStatus::EXPIRED => $q->where('fulfilment_status', 'pending')->where('order_state', 'expired'),
            OrderStatus::CANCELLED => $q->where('fulfilment_status', 'pending')->where('order_state', 'cancelled'),
            OrderStatus::AMOUNT_MISMATCH => $q->where('fulfilment_status', 'pending')->where('order_state', 'open')->where('resolution', '!=', 'none'),
            // Bespoke not started, or a paid shop/drop order not started (see legacyStatus()).
            'pending' => $unsettled($q)->where(fn ($p) => $p->where('kind', 'bespoke')->orWhere(fn ($c) => $paidUp($commerce($c)))),
            OrderStatus::PAID => $paidUp($commerce($unsettled($q))),
            OrderStatus::PAYMENT_FAILED => $notPaidUp($commerce($unsettled($q)))->where('payment_failed', true),
            OrderStatus::PENDING_PAYMENT => $notPaidUp($commerce($unsettled($q)))->where('payment_failed', false),
            default => $q->where('fulfilment_status', $status), // production stages
        };
    }
}
