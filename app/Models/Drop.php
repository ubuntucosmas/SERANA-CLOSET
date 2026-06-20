<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A Serana Drop: a design concept sold "sell-then-make". Customers reserve a
 * piece (pay via M-Pesa) against a target quantity and deadline; the studio
 * produces it only once enough pieces are reserved.
 *
 * Reservations are CustomOrder rows (type='drop', drop_id) so the existing
 * payment/settlement/receipt/tracking rails apply unchanged.
 */
class Drop extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'target_quantity' => 'integer',
        'ends_at' => 'datetime',
        'sizes' => 'array',
    ];

    protected $appends = ['image_url', 'reserved_count', 'funded_percent', 'is_funded', 'is_open'];

    protected static function booted(): void
    {
        static::saving(function (Drop $drop) {
            if (empty($drop->slug) || $drop->isDirty('title')) {
                $drop->slug = static::uniqueSlug($drop->title, $drop->id);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    private static function uniqueSlug(string $title, $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'drop';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    /** All reservations (paid or not). */
    public function reservations()
    {
        return $this->hasMany(CustomOrder::class, 'drop_id');
    }

    /** Confirmed (paid) reservations — what counts toward the target. */
    public function paidReservations()
    {
        return $this->hasMany(CustomOrder::class, 'drop_id')->where('is_paid', true);
    }

    public function getImageUrlAttribute(): ?string
    {
        $value = $this->image_path;
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;

        $targetDisk = config('filesystems.public_disk', 's3');
        $driver = config("filesystems.disks.{$targetDisk}.driver", 'local');

        if ($driver === 'local') {
            return '/storage/' . ltrim(str_replace('/storage/', '/', $value), '/');
        }
        return Storage::disk($targetDisk)->url($value);
    }

    public function getReservedCountAttribute(): int
    {
        // Prefer an eager-loaded count (withCount) to avoid N+1 in lists.
        return (int) ($this->paid_reservations_count ?? $this->paidReservations()->count());
    }

    public function getFundedPercentAttribute(): int
    {
        $target = max(1, (int) $this->target_quantity);
        return min(100, (int) round(($this->reserved_count / $target) * 100));
    }

    public function getIsFundedAttribute(): bool
    {
        return $this->reserved_count >= (int) $this->target_quantity;
    }

    /** Open for new reservations: live and not past its deadline. */
    public function getIsOpenAttribute(): bool
    {
        return $this->status === 'live' && (is_null($this->ends_at) || $this->ends_at->isFuture());
    }
}
