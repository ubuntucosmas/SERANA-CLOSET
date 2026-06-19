<?php

namespace App\Models;

use App\Support\OrderStatus;
use App\Support\WhatsApp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class CustomOrder extends Model
{
    use SoftDeletes;
    protected $guarded = [];

    protected $casts = [
        'inspiration_image_paths' => 'array',
        'items_json' => 'array',
        'is_paid' => 'boolean',
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
        $paths = $this->inspiration_image_paths ?: [];
        $targetDisk = config('filesystems.public_disk');
        $driver = config("filesystems.disks.{$targetDisk}.driver", 'local');

        return array_map(function ($path) use ($targetDisk, $driver) {
            if (str_starts_with($path, 'http')) return $path;
            
            if ($driver === 'local') {
                $rawPath = ltrim(str_replace('/storage/', '/', $path), '/');
                return '/storage/' . $rawPath;
            }
            
            return Storage::disk($targetDisk)->url($path);
        }, $paths);
    }

    public function getItemsWithUrlsAttribute()
    {
        $items = $this->items_json ?: [];
        if (!is_array($items)) return [];

        $targetDisk = config('filesystems.public_disk');
        $driver = config("filesystems.disks.{$targetDisk}.driver", 'local');
        $processed = [];
        foreach ($items as $key => $item) {
            if ($key === 'precision_sizing') continue;
            
            if (is_array($item)) {
                $img = $item['image'] ?? $item['image_path'] ?? null;
                if ($img) {
                    if (str_starts_with($img, 'http')) {
                        $item['image_url'] = $img;
                    } elseif ($driver === 'local') {
                        $rawPath = ltrim(str_replace('/storage/', '/', $img), '/');
                        $item['image_url'] = '/storage/' . $rawPath;
                    } else {
                        $item['image_url'] = Storage::disk($targetDisk)->url($img);
                    }
                }
                $processed[] = $item;
            }
        }
        return $processed;
    }

    protected static function booted()
    {
        static::creating(function ($order) {
            $order->cipher = 'SRN-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('Y');
        });
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

    /**
     * Convert this order's held stock into confirmed sales. Called once on
     * payment confirmation. Idempotent: only 'held' reservations are committed.
     */
    public function commitReservations(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            $held = $this->stockReservations()->where('status', 'held')->lockForUpdate()->get();
            foreach ($held as $reservation) {
                Product::where('id', $reservation->product_id)->increment('batch_sold', $reservation->quantity);
                $reservation->update(['status' => 'committed']);
            }
        });
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
        $input = WhatsApp::normalizeNumber($phone);

        if ($input === '') {
            return false;
        }

        return in_array($input, [
            WhatsApp::normalizeNumber($this->whatsapp_number),
            WhatsApp::normalizeNumber($this->phone),
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
        $isPaid = $this->is_paid || $this->status === OrderStatus::PAID;

        return [
            'reference'   => $this->cipher,
            'placed_at'   => optional($this->created_at)->format('M d, Y'),
            'type'        => $this->type,
            'status'      => $this->status,
            'is_paid'     => $isPaid,
            'amount'      => $this->price_quoted ? (float) $this->price_quoted : null,
            'items'       => $this->trackingItems(),
            'progress'    => $this->progressSnaps()->latest()->get()->map(fn ($snap) => [
                'image_url'   => $snap->image_url,
                'description' => $snap->description,
                'date'        => optional($snap->created_at)->format('M d, Y'),
            ])->all(),
            'receipt_url' => $isPaid
                ? URL::temporarySignedRoute('orders.receipt', now()->addDays(7), ['order' => $this->id])
                : null,
        ];
    }

    /** Item names + quantities for ready-to-wear orders (no-op for bespoke). */
    private function trackingItems(): array
    {
        if ($this->type !== 'standard') {
            return [];
        }

        return collect($this->items_with_urls)->map(fn ($item) => [
            'name'      => $item['name'] ?? 'Item',
            'quantity'  => $item['quantity'] ?? 1,
            'image_url' => $item['image_url'] ?? null,
        ])->all();
    }
}
