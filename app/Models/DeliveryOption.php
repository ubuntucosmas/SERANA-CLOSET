<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How an order reaches the customer (Phase D2, design §13.7): collection at a
 * shop, a delivery zone with its fee, or a quote on WhatsApp that the Studio
 * prices. The server always computes the fee; the customer only sends the id.
 */
class DeliveryOption extends Model
{
    public const PICKUP = 'pickup';
    public const ZONE = 'zone';
    public const QUOTE = 'quote';
    public const TYPES = [self::PICKUP, self::ZONE, self::QUOTE];

    protected $guarded = [];

    protected $casts = ['fee' => 'integer', 'free_over' => 'integer', 'active' => 'boolean', 'sort' => 'integer'];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /** Offered to customers now: active, and a pickup only at a shop that takes collections and has an address. */
    public function scopeAvailable($query)
    {
        return $query->where('active', true)->where(fn ($q) => $q->where('type', '!=', self::PICKUP)
            ->orWhereHas('location', fn ($l) => $l->pickupAvailable()))->orderBy('sort')->orderBy('id');
    }

    /** What this option costs on an item subtotal: free over the threshold; a quote has no price yet (null). */
    public function feeFor(int $subtotal): ?int
    {
        if ($this->type === self::QUOTE) {
            return null;
        }

        return $this->free_over !== null && $subtotal >= $this->free_over ? 0 : (int) $this->fee;
    }

    /** What checkout shows: never more than the customer needs to choose. */
    public function forCheckout(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'type' => $this->type, 'fee' => (int) $this->fee, 'free_over' => $this->free_over,
            'eta' => $this->eta_text, 'address' => $this->type === self::PICKUP ? $this->location?->address : null];
    }
}
