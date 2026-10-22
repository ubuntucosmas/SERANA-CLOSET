<?php

namespace App\Models;

use App\Support\OrderStatus;
use App\Support\Phone;
use App\Support\PublicUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class CustomOrder extends Model
{
    use SoftDeletes;
    use Concerns\ReadsOrderState;
    protected $guarded = [];

    protected $casts = [
        'inspiration_image_paths' => 'array',
        'items_json' => 'array',
        'fulfilment_snapshot' => 'array',
        'is_paid' => 'boolean',
        'payment_failed' => 'boolean',
        'chargeable_total' => 'integer',
        'amount_paid' => 'integer',
        'balance_due' => 'integer',
        'deposit_override' => 'integer',
        'paid_at' => 'datetime',
        'refund_due_since' => 'datetime',
        'resolution_since' => 'datetime',
    ];

    protected $appends = ['inspiration_urls', 'items_with_urls', 'deposit_required'];

    /**
     * getInspirationUrlsAttribute
     * 
     * Dynamically resolves stored image paths into full, publicly accessible URLs.
     * It intelligently handles both local storage and cloud (S3) drivers
     * based on the active storage configuration.
     */
    public function getInspirationUrlsAttribute()
    {
        return array_map(
            fn ($path) => PublicUrl::resolve($path),
            $this->inspiration_image_paths ?: []
        );
    }

    public function getItemsWithUrlsAttribute()
    {
        $items = $this->items_json ?: [];
        if (!is_array($items)) return [];

        $processed = [];
        foreach ($items as $key => $item) {
            if ($key === 'precision_sizing') continue;

            if (is_array($item)) {
                $img = $item['image'] ?? $item['image_path'] ?? null;
                if ($img) {
                    $item['image_url'] = PublicUrl::resolve($img);
                }
                $item['size_label'] = self::sizeLabel($item['options'] ?? null);
                $processed[] = $item;
            }
        }
        return $processed;
    }

    /** "Top M · Bottom L" for sets, "Size M" for single pieces, null when none chosen. */
    public static function sizeLabel(?array $options): ?string
    {
        $top = $options['top_size'] ?? null;
        $bottom = $options['bottom_size'] ?? null;
        if ($top && $bottom) return "Top {$top} · Bottom {$bottom}";
        if ($top || $bottom) {
            $size = $top ?: $bottom;
            return $size === \App\Models\ProductVariant::ONE_SIZE ? $size : "Size {$size}";
        }
        return null;
    }

    /** When payment was confirmed, formatted in the store's display timezone. */
    public function paidAtForDisplay(string $format = 'M d, Y g:i A'): ?string
    {
        $at = $this->paid_at
            ?? $this->paymentAttempts()->where('status', PaymentAttempt::PAID)->oldest('updated_at')->value('updated_at');
        $at = $at ? \Illuminate\Support\Carbon::parse($at) : $this->updated_at;

        return $at?->copy()->timezone(config('app.display_timezone'))->format($format);
    }

    protected static function booted()
    {
        static::creating(function ($order) {
            $order->cipher = 'SRN-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('Y');
            // Dual-run (design §2.3): the split columns follow the legacy fields
            // the order is created with.
            foreach (\App\Support\LegacyOrderMap::columns($order->type ?? 'custom', $order->status ?? 'pending', $order->payment_method) as $column => $value) {
                $order->{$column} ??= $value;
            }
        });
        // Model saves that change a legacy field keep the split columns in step
        // (bulk updates merge LegacyOrderMap::forStatus() themselves).
        static::updating(function ($order) {
            if ($order->isDirty('status')) {
                $order->fill(array_diff_key(\App\Support\LegacyOrderMap::forStatus($order->status), ['status' => true]));
            }
            if ($order->isDirty('payment_method')) {
                $order->channel = \App\Support\LegacyOrderMap::channel($order->payment_method);
            }
            if ($order->isDirty('type')) {
                $order->kind = \App\Support\LegacyOrderMap::kind($order->type);
                $order->garment = \App\Support\LegacyOrderMap::garment($order->type);
            }
        });
        // ...and so do its lines and ledger, by the same rules as the backfill.
        static::created(fn ($order) => app(\App\Services\LedgerBackfill::class)->order($order));
    }

    public function progressSnaps()
    {
        return $this->hasMany(OrderProgressSnap::class);
    }

    /** Who sold it at the till (Phase E); null for online orders. */
    public function seller()
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    /** For the Studio list: "where was this sold, and by whom". */
    public function getSellerNameAttribute(): ?string
    {
        return $this->sold_by ? $this->seller?->name : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stockReservations()
    {
        return $this->hasMany(StockReservation::class);
    }

    public function paymentAttempts()
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    /**
     * What must be paid before work starts (design B, D14). Bespoke: the order's
     * override, or the policy share of the quote, in whole shillings. Shop and
     * drop orders have no deposit: they are paid in full.
     */
    public function depositRequired(): int
    {
        $total = (int) $this->chargeable_total;
        if ($this->kind !== 'bespoke') {
            return $total;
        }

        return min($total, $this->deposit_override ?? (int) round($total * \App\Support\OrderPolicy::depositPercent() / 100));
    }

    /** The order's ledger for the Studio: every booking, and whether it was reversed, confirmed or is flagged. */
    public function getLedgerRowsAttribute(): array
    {
        $reviews = \Illuminate\Support\Facades\DB::table('payment_reviews')->whereIn('order_payment_id', $this->payments()->pluck('id'))->pluck('decision', 'order_payment_id');
        $reversed = $this->payments()->whereNotNull('reverses_payment_id')->pluck('reverses_payment_id')->flip();

        $bespoke = $this->kind === 'bespoke';

        return $this->payments()->with('attempt')->orderBy('id')->get()->map(fn (OrderPayment $p) => [
            'id' => $p->id, 'type' => $p->type, 'amount' => $p->amount, 'method' => $p->method,
            'reference' => $p->external_reference, 'actor' => $p->actor_type, 'reason' => $p->reason,
            'at' => $p->occurred_at?->copy()->timezone(config('app.display_timezone'))->format('d M Y, H:i'),
            'reversed' => $reversed->has($p->id),
            'genuine' => ($reviews[$p->id] ?? null) === 'genuine',
            // Booked past the unique index by the backfill: the code is also on another order.
            'flagged' => $p->type === OrderPayment::PAYMENT && str_contains((string) $p->external_reference, \App\Services\LedgerBackfill::DUPLICATE_MARK)
                && ! $reversed->has($p->id) && ! isset($reviews[$p->id]),
            // A bespoke M-Pesa payment that differs from its prompt, not yet confirmed (Phase C Attention list).
            'unexpected' => $bespoke && $p->type === OrderPayment::PAYMENT && $p->attempt && (int) $p->attempt->amount !== $p->amount
                && ! $reversed->has($p->id) && ! isset($reviews[$p->id]),
        ])->all();
    }

    /**
     * A late payment whose item sold out (Phase C), for the Studio's decision
     * box: the items that could not be served. Null when there is none.
     */
    public function getStockProblemAttribute(): ?array
    {
        if ($this->resolution_code !== 'stock_gone' || ! in_array($this->resolution, ['needs_resolution', 'waiting_restock'], true)) {
            return null;
        }

        return [
            'waiting' => $this->resolution === 'waiting_restock',
            // One entry per sold-out product (a set's top and bottom rows together), named by size with stock per size.
            'items' => $this->stockReservations()->where('status', 'released')->with(['product' => fn ($q) => $q->withTrashed(), 'variant'])->get()
                ->groupBy('product_id')
                ->map(fn ($rows) => ['product_id' => $rows->first()->product_id,
                    'name' => ($rows->first()->product?->name ?? 'Item') . ($rows->first()->variant && $rows->first()->variant->size !== \App\Models\ProductVariant::ONE_SIZE
                        ? ' (' . $rows->pluck('variant')->filter()->map->label()->implode(' · ') . ')' : ''),
                    'quantity' => (int) $rows->where('second_part', false)->sum('quantity')])
                ->values()->all(),
        ];
    }

    public function getDepositRequiredAttribute(): int
    {
        return $this->depositRequired();
    }

    /**
     * The customer's pay link (design B, D15): signed, valid for the policy's
     * days, for a quoted bespoke order. A paid order's link still shows it paid.
     */
    public function payLink(): ?string
    {
        // Shop orders get one too while a balance is owed (a swap to a dearer item, a short payment).
        $payable = $this->isBespoke() || ($this->isShop() && (int) $this->amount_paid > 0 && (int) $this->balance_due > 0);
        if (! $payable || (int) $this->chargeable_total <= 0 || $this->order_state !== 'open') {
            return null;
        }

        return URL::temporarySignedRoute('pay', now()->addDays(\App\Support\OrderPolicy::payLinkDays()), ['order' => $this->cipher]);
    }

    public function getPayLinkAttribute(): ?string
    {
        return $this->payLink();
    }

    /** A wa.me link to the customer with the quote and their pay link, for the Studio to send. */
    public function getPayWhatsappUrlAttribute(): ?string
    {
        $link = $this->payLink();
        if (! $link || ! Phone::normalize($this->whatsapp_number ?: $this->phone)) {
            return null;
        }

        $money = fn (int $v) => 'KSh ' . number_format($v);
        $firstName = strtok(trim((string) $this->full_name), ' ') ?: 'there';
        $depositLeft = max(0, $this->depositRequired() - (int) $this->amount_paid);
        $intro = $this->isBespoke()
            ? 'Your ' . \App\Support\StoreProfile::name() . " quote is ready (order {$this->cipher}" . ($this->garment ? ", {$this->garment}" : '') . ').'
            : 'Here is the balance for your ' . \App\Support\StoreProfile::name() . " order {$this->cipher}.";
        $lines = ["Hello {$firstName},", '', $intro, '', 'Total: ' . $money((int) $this->chargeable_total)];
        if ((int) $this->amount_paid > 0) {
            $lines[] = 'Paid so far: ' . $money((int) $this->amount_paid);
        }
        $lines[] = $depositLeft > 0 && $depositLeft < (int) $this->balance_due
            ? 'Deposit to start: ' . $money($depositLeft) . ' · Balance after: ' . $money((int) $this->balance_due - $depositLeft)
            : 'Balance due: ' . $money(max(0, (int) $this->balance_due));
        array_push($lines, '', "Pay securely by M-Pesa here: {$link}");
        if ($this->isBespoke()) {
            array_push($lines, '', \App\Support\OrderPolicy::depositPolicy());
        }

        return \App\Support\WhatsApp::link(Phone::normalize($this->whatsapp_number ?: $this->phone), implode("\n", $lines));
    }

    /** What is owed: the order's total is the sum of these (design §2.1). */
    public function lines()
    {
        return $this->hasMany(OrderLine::class);
    }

    /** Money in and out (the ledger). */
    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    /**
     * The ledger's two sums (N1, N2), optionally as of a ledger row:
     * payments counted (minus reversed bookings) and refunds paid out.
     *
     * @return array{payments: int, refunds: int}
     */
    public function ledgerSums(?int $upToId = null): array
    {
        $sum = fn (string $type) => (int) $this->payments()->where('type', $type)->when($upToId, fn ($q) => $q->where('id', '<=', $upToId))->sum('amount');

        return [
            'payments' => $sum(OrderPayment::PAYMENT) - $sum(OrderPayment::REVERSAL),
            'refunds' => $sum(OrderPayment::REFUND),
        ];
    }

    /**
     * No money is held on this order and nothing awaits a decision: the only
     * kind of order that may be expired, cancelled, superseded or archived as
     * abandoned. One definition for every such path (V1 money invariant 2).
     * amount_paid is derived from the ledger (payments − refunds) on every
     * money write, whatever LEDGER_READS says. An order with money on it goes
     * through the financial paths instead: the balance hold and its expiry to
     * a refund, the Refunds queue, or the Studio's payment problems.
     */
    public function scopeUnfunded(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('amount_paid', '<=', 0)
            ->where(fn ($q) => $q->where('resolution', 'none')->orWhereNull('resolution'));
    }

    public function isUnfunded(): bool
    {
        return (int) $this->amount_paid <= 0 && ($this->resolution ?? 'none') === 'none';
    }

    /**
     * Is any STK push for this order still awaiting its outcome (or already
     * paid)? While one is, a *different* attempt failing must not fail the
     * order — the customer may have paid the other prompt.
     */
    /**
     * A prompt for this order sent moments ago and not yet answered. While one
     * exists, no other payment is started: the customer may still approve it,
     * and a second prompt or a cash tender would take the money twice.
     */
    public function unansweredPrompt(): ?PaymentAttempt
    {
        return $this->paymentAttempts()->where('status', PaymentAttempt::PENDING)
            ->where('created_at', '>=', now()->subSeconds((int) config('services.mpesa.prompt_window_seconds', 150)))
            ->latest('id')->first();
    }

    public function hasLivePaymentAttempts(): bool
    {
        return $this->paymentAttempts()
            ->whereIn('status', [PaymentAttempt::PENDING, PaymentAttempt::PAID])
            ->exists();
    }

    /**
     * Convert this order's held stock into confirmed sales, once the order is
     * paid. Idempotent: committed reservations are skipped.
     *
     * A batch is never sold past its limit (Phase C). A live hold is ours; a
     * hold that lapsed (released, or held past its expiry) is taken only if
     * the stock is still free. If any line cannot be served, nothing is
     * committed: the lines that can are held until $keepUntil while the
     * studio decides, and the names of the sold-out items are returned.
     *
     * @return list<string> the items that sold out (empty when all committed)
     */
    public function commitReservations(?\DateTimeInterface $keepUntil = null): array
    {
        if (\App\Support\StockMode::v2()) {
            return app(\App\Services\StockService::class)->commit($this, $keepUntil);
        }

        return DB::transaction(function () use ($keepUntil) {
            // Lock order: products (by id), then stock_reservations. See lockReservedProducts().
            $this->lockReservedProducts();
            $reservations = $this->stockReservations()
                ->whereIn('status', ['held', 'released'])
                ->lockForUpdate()->get();

            // A lapsed hold no longer counts against availability, so its units must be free again.
            // Per product, all its lapsed units together (a set counts once, by its first part).
            $short = [];
            $lapsed = $reservations->reject(fn ($r) => $r->status === 'held' && ($r->expires_at === null || $r->expires_at->isFuture()));
            foreach ($lapsed->groupBy('product_id') as $productId => $rows) {
                $product = Product::withTrashed()->find($productId);
                $units = (int) $rows->where('second_part', false)->sum('quantity');
                if (! $product || ($product->batch_limit !== null && $units > (int) $product->availableStock())) {
                    foreach ($rows as $row) {
                        $short[$row->id] = $product?->name ?? 'An item';
                    }
                }
            }

            if ($short) {
                foreach ($reservations as $reservation) {
                    if (! isset($short[$reservation->id])) {
                        $reservation->update(['status' => 'held', 'expires_at' => $keepUntil]);
                    } elseif ($reservation->status === 'held') {
                        $reservation->update(['status' => 'released']);
                    }
                }

                return array_values(array_unique($short));
            }

            foreach ($reservations as $reservation) {
                if (! $reservation->second_part) {
                    Product::where('id', $reservation->product_id)->increment('batch_sold', $reservation->quantity);
                }
                $reservation->update(['status' => 'committed']);
            }

            return [];
        });
    }

    /**
     * Make sure every unit this order needs is still ours before an STK push
     * takes the customer's money. Live holds get a fresh payment window;
     * holds the expiry job already released are re-acquired if the stock is
     * still free. Returns false — without mutating anything — when it isn't,
     * so the caller can refuse the push instead of collecting money for a
     * garment someone else bought.
     */
    public function secureStockForPayment(?\DateTimeInterface $until = null): bool
    {
        // M-Pesa holds last minutes; a WhatsApp order passes its longer window.
        $window = $until ?? now()->addMinutes(config('services.mpesa.reservation_minutes', 15));
        if (\App\Support\StockMode::v2()) {
            return app(\App\Services\StockService::class)->secure($this, $window);
        }

        return DB::transaction(function () use ($window) {
            // Lock order: products (by id), then stock_reservations. See lockReservedProducts().
            $this->lockReservedProducts();
            $reservations = $this->stockReservations()
                ->whereIn('status', ['held', 'released'])
                ->lockForUpdate()->get();

            // Validate first, mutate after — a bare `return false` from this
            // closure commits, so nothing may change until all lines pass.
            foreach ($reservations->where('status', 'released')->groupBy('product_id') as $productId => $rows) {
                $product = Product::whereKey($productId)->lockForUpdate()->first();
                if (! $product) {
                    return false;
                }
                // A released hold no longer counts against availability, so all of this
                // product's released units must still be free to re-claim them (a set once).
                if ($product->batch_limit && (int) $rows->where('second_part', false)->sum('quantity') > (int) $product->availableStock()) {
                    return false;
                }
            }

            foreach ($reservations as $reservation) {
                // Never shorten a longer hold (a swap or balance hold kept for days).
                $keep = $reservation->status === 'held' && ($reservation->expires_at === null || $reservation->expires_at > $window);
                $reservation->update(['status' => 'held', 'expires_at' => $keep ? $reservation->expires_at : $window]);
            }

            return true;
        });
    }

    /**
     * The one global lock order for stock: products by id, then
     * stock_reservations. Checkout locks its products and then inserts holds;
     * a path that locked this order's holds first and a product second would
     * deadlock against it (InnoDB gap locks on stock_reservations block the
     * checkout's insert while the checkout holds the product). Proven on
     * MariaDB by tests/Feature/ConcurrencyTest.
     */
    private function lockReservedProducts(): void
    {
        $productIds = $this->stockReservations()
            ->whereIn('status', ['held', 'released'])
            ->pluck('product_id')->unique()->sort()->values();

        if ($productIds->isNotEmpty()) {
            Product::withTrashed()->whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get(['id']);
        }
    }

    /**
     * The first two steps of the global lock order (design N9): this order's
     * products by id, then its stock holds. The settlement service takes the
     * order row after this (OrderSettlementService::lockForMoney).
     */
    public function lockStock(): void
    {
        if (\App\Support\StockMode::v2()) {
            app(\App\Services\StockService::class)->lockForOrder($this); // stock levels by id, then holds (design §13.3)

            return;
        }
        $this->lockReservedProducts();
        $this->stockReservations()->whereIn('status', ['held', 'released'])->orderBy('id')->lockForUpdate()->get(['id']);
    }

    /** Return this order's held stock to the pool (failed/expired payment). */
    public function releaseReservations(): void
    {
        $this->stockReservations()->where('status', 'held')->update(['status' => 'released']);
    }

    /**
     * Does the supplied phone identify the person who placed this order?
     * Both sides are normalized so "+254 7…", "07…" and "2547…" all match.
     */
    public function matchesContact(?string $phone): bool
    {
        $input = Phone::normalize($phone);

        if ($input === '') {
            return false;
        }

        return in_array($input, [
            Phone::normalize($this->whatsapp_number),
            Phone::normalize($this->phone),
        ], true);
    }

    /**
     * Safe, public-facing projection for the order-tracking page.
     *
     * Deliberately excludes PII the tracker should never expose (email, street
     * address, phone, internal studio notes, other customers' data). The signed
     * receipt link is only minted once payment is confirmed.
     */
    public function deliveryOption()
    {
        return $this->belongsTo(DeliveryOption::class);
    }

    /** The delivery lines (Phase D2): the charge and any later change, all of type delivery. */
    public function deliveryTotal(): int
    {
        return (int) $this->lines()->where('line_type', OrderLine::DELIVERY)->sum('amount');
    }

    /** What was quoted or bought, without delivery: the Studio edits this, never the delivery inside it. */
    public function quoteAmount(): int
    {
        return $this->total() - $this->deliveryTotal();
    }

    /** A quote-on-WhatsApp order the Studio hasn't priced yet: it can't be paid until it is. */
    public function needsDeliveryQuote(): bool
    {
        return $this->deliveryOption?->type === DeliveryOption::QUOTE && ! $this->lines()->where('line_type', OrderLine::DELIVERY)->exists();
    }

    /**
     * The delivery as every screen shows it (receipts, tracking, the WhatsApp
     * message, the Studio): the option's name, the fee charged and when to
     * expect it. Null for orders from before delivery had a line.
     *
     * @return array{name: string, fee: int, eta: ?string}|null
     */
    public function deliverySummary(): ?array
    {
        if (! $this->delivery_option_id || $this->needsDeliveryQuote()) {
            return null;
        }

        return ['name' => $this->deliveryOption?->name ?? 'Delivery', 'fee' => $this->deliveryTotal(), 'eta' => $this->deliveryOption?->eta_text];
    }

    /** For the Studio's order screens: the delivery charged, the quote without it, and whether a quote is still to be priced. */
    public function getDeliveryInfoAttribute(): array
    {
        return ['summary' => $this->deliverySummary(), 'delivery_total' => $this->deliveryTotal(), 'quote_amount' => $this->quoteAmount(),
            'needs_quote' => $this->needsDeliveryQuote(), 'option_id' => $this->delivery_option_id];
    }

    public function handoverDetails(): ?array
    {
        if (! $this->fulfilment_method) return null;
        $pickup = $this->fulfilment_method === 'pickup';
        return [
            'method' => $this->fulfilment_method,
            'label' => $pickup ? 'Collect from shop' : 'Delivery',
            'location' => $pickup ? ($this->fulfilment_snapshot['name'] ?? 'Shop') : null,
            'address' => $pickup ? ($this->fulfilment_snapshot['address'] ?? null) : null,
            'next' => match (true) {
                $this->order_state === 'cancelled' => 'This order is cancelled. Contact the Studio about any refund; do not travel for collection.',
                $this->isExpired() => 'This unpaid order expired. Place a new order before arranging handover.',
                $this->fulfilment_status === 'collected' => 'Collection confirmed.',
                $this->fulfilment_status === 'shipped' => 'Your order has been dispatched for delivery.',
                ($this->resolution ?? 'none') !== 'none' => 'The Studio is reviewing a payment or stock issue. Wait for confirmation before arranging handover.',
                $pickup && $this->fulfilment_status === 'ready_for_pickup' => 'Ready for collection. Bring your order reference and the phone number used for this order. Any balance must be settled before handover.',
                $pickup => 'Wait until tracking shows ready for collection before travelling.',
                default => 'The Studio will prepare your order for delivery.',
            },
        ];
    }

    public function publicTrackingPayload(): array
    {
        $isPaid = $this->isPaidUp();

        return [
            'reference'   => $this->cipher,
            'placed_at'   => optional($this->created_at)->timezone(config('app.display_timezone'))->format('M d, Y'),
            'type'        => $this->legacyType(),
            'status'      => $this->legacyStatus(),
            'is_paid'     => $isPaid,
            'payment_method' => $this->payment_method,
            'handover' => $this->handoverDetails(),
            'delivery' => $this->deliverySummary(),
            'delivery_quote_pending' => $this->needsDeliveryQuote(),
            // How to pay a WhatsApp order, as set in Studio → Payments.
            'payment_instructions' => ! $isPaid && $this->payment_method === \App\Support\PaymentSettings::WHATSAPP
                ? (\App\Support\PaymentSettings::instructions() ?: null) : null,
            'whatsapp_url' => ! $isPaid && $this->payment_method === \App\Support\PaymentSettings::WHATSAPP && $this->isShop()
                && ! $this->isExpired() && ! $this->isCancelled()
                ? \App\Support\WhatsAppOrderMessage::link($this) : null,
            // Paid orders: post the payment record to the studio, if it was missed at checkout.
            'paid_whatsapp_url' => $isPaid && $this->isCommerce()
                ? \App\Support\WhatsAppOrderMessage::paidLink($this) : null,
            'amount'      => $this->total() ?: null,
            // The ledger: what has been paid and what is still owed (deposits).
            'amount_paid' => (int) $this->amount_paid,
            'balance_due' => max(0, (int) $this->balance_due),
            'items'       => $this->trackingItems(),
            'progress'    => $this->progressSnaps()->latest()->get()->map(fn ($snap) => [
                'image_url'   => $snap->image_url,
                'description' => $snap->description,
                'date'        => optional($snap->created_at)->timezone(config('app.display_timezone'))->format('M d, Y'),
            ])->all(),
            'receipt_url' => $isPaid
                ? URL::temporarySignedRoute('orders.receipt', now()->addDays(7), ['order' => $this->id])
                : null,
        ];
    }

    /** Item names + quantities for ready-to-wear orders (no-op for bespoke). */
    private function trackingItems(): array
    {
        if ($this->isDrop()) {
            return [['name' => $this->items_json['drop'] ?? 'Drop Reservation', 'quantity' => 1,
                'options' => ['size' => $this->items_json['size'] ?? null], 'price' => $this->total()]];
        }
        if (! $this->isShop()) return [];

        return collect($this->items_with_urls)->map(fn ($item) => [
            'name'      => $item['name'] ?? 'Item',
            'quantity'  => $item['quantity'] ?? 1,
            'image_url' => $item['image_url'] ?? null,
            'options' => $item['options'] ?? null,
            'size_label' => $item['size_label'] ?? null,
            'price' => $item['price'] ?? null,
        ])->all();
    }
}
