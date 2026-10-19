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
    protected $guarded = [];

    protected $casts = [
        'inspiration_image_paths' => 'array',
        'items_json' => 'array',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
    ];

    protected $appends = ['inspiration_urls', 'items_with_urls'];

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
        if ($top || $bottom) return 'Size ' . ($top ?: $bottom);
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
     * Is any STK push for this order still awaiting its outcome (or already
     * paid)? While one is, a *different* attempt failing must not fail the
     * order — the customer may have paid the other prompt.
     */
    public function hasLivePaymentAttempts(): bool
    {
        return $this->paymentAttempts()
            ->whereIn('status', [PaymentAttempt::PENDING, PaymentAttempt::PAID])
            ->exists();
    }

    /**
     * Convert this order's held stock into confirmed sales. Called once on
     * payment confirmation. Idempotent: already-committed reservations are
     * skipped. Released holds are committed too — if the money arrived after
     * the hold lapsed, the customer must still be served; availability floors
     * at zero, so any overshoot surfaces as "sold out" rather than overselling
     * further.
     */
    public function commitReservations(): void
    {
        DB::transaction(function () {
            // Lock order: products (by id), then stock_reservations. See lockReservedProducts().
            $this->lockReservedProducts();
            $reservations = $this->stockReservations()
                ->whereIn('status', ['held', 'released'])
                ->lockForUpdate()->get();
            foreach ($reservations as $reservation) {
                Product::where('id', $reservation->product_id)->increment('batch_sold', $reservation->quantity);
                $reservation->update(['status' => 'committed']);
            }
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

        return DB::transaction(function () use ($window) {
            // Lock order: products (by id), then stock_reservations. See lockReservedProducts().
            $this->lockReservedProducts();
            $reservations = $this->stockReservations()
                ->whereIn('status', ['held', 'released'])
                ->lockForUpdate()->get();

            // Validate first, mutate after — a bare `return false` from this
            // closure commits, so nothing may change until all lines pass.
            foreach ($reservations->where('status', 'released') as $reservation) {
                $product = Product::whereKey($reservation->product_id)->lockForUpdate()->first();
                if (! $product) {
                    return false;
                }
                // The released hold no longer counts against availability, so
                // the full quantity must still be free to re-claim it.
                if ($product->batch_limit && $reservation->quantity > (int) $product->availableStock()) {
                    return false;
                }
            }

            foreach ($reservations as $reservation) {
                $reservation->update(['status' => 'held', 'expires_at' => $window]);
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
    public function publicTrackingPayload(): array
    {
        $isPaid = (bool) $this->is_paid;

        return [
            'reference'   => $this->cipher,
            'placed_at'   => optional($this->created_at)->timezone(config('app.display_timezone'))->format('M d, Y'),
            'type'        => $this->type,
            'status'      => $this->status,
            'is_paid'     => $isPaid,
            'payment_method' => $this->payment_method,
            // How to pay a WhatsApp order, as set in Studio → Payments.
            'payment_instructions' => ! $isPaid && $this->payment_method === \App\Support\PaymentSettings::WHATSAPP
                ? (\App\Support\PaymentSettings::instructions() ?: null) : null,
            'whatsapp_url' => ! $isPaid && $this->payment_method === \App\Support\PaymentSettings::WHATSAPP && $this->type === 'standard'
                && ! in_array($this->status, [OrderStatus::EXPIRED, OrderStatus::CANCELLED], true)
                ? \App\Support\WhatsAppOrderMessage::link($this) : null,
            // Paid orders: post the payment record to the studio, if it was missed at checkout.
            'paid_whatsapp_url' => $isPaid && in_array($this->type, ['standard', 'drop'], true)
                ? \App\Support\WhatsAppOrderMessage::paidLink($this) : null,
            'amount'      => $this->price_quoted ? (float) $this->price_quoted : null,
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
        if ($this->type === 'drop') {
            return [['name' => $this->items_json['drop'] ?? 'Drop Reservation', 'quantity' => 1,
                'options' => ['size' => $this->items_json['size'] ?? null], 'price' => $this->price_quoted]];
        }
        if ($this->type !== 'standard') return [];

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
