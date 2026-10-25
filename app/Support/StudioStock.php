<?php

namespace App\Support;

use App\Models\Location;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\StockCutover;
use App\Services\StockService;

/**
 * What Studio → Stock shows (Phase D1): per product, each size with its
 * SKU, on hand, held and available at the shop and when it was last
 * counted, and the latest movements with who and why; and, for the owner,
 * where the switch to stock per size stands.
 */
final class StudioStock
{
    /** Movements shown per product, newest first. */
    public const HISTORY = 50;

    public static function forStudio(bool $withCutover): array
    {
        $stock = app(StockService::class);
        $shop = Location::query()->online()->first();

        // A few whole-list queries, not several per product: 500 products made ~17,000 queries.
        $products = Product::with(['variants' => fn ($q) => $q->orderBy('sort')])->orderBy('name')->get();
        $variants = $products->flatMap(fn (Product $p) => $p->variants->each(fn ($v) => $v->setRelation('product', $p)));
        $ids = $variants->pluck('id');
        $levels = StockLevel::whereIn('product_variant_id', $ids)->where('location_id', $shop?->id)->get()->keyBy('product_variant_id');
        // Paid sale movements already left the unsold pool. Net out any restocked returns.
        $awaitingPickup = \Illuminate\Support\Facades\DB::table('stock_movements as m')
            ->join('custom_orders as o', 'o.id', '=', 'm.custom_order_id')
            ->whereIn('m.product_variant_id', $ids)->where('m.location_id', $shop?->id)
            ->whereIn('m.type', [StockMovement::SALE, StockMovement::RETURN])
            ->where('o.fulfilment_method', 'pickup')->where('o.order_state', 'open')
            ->whereNotIn('o.fulfilment_status', ['collected', 'shipped'])
            ->selectRaw('m.product_variant_id, SUM(m.quantity) as net')->groupBy('m.product_variant_id')->pluck('net', 'm.product_variant_id');
        $held = StockReservation::active()->whereIn('product_variant_id', $ids)->where('location_id', $shop?->id)
            ->selectRaw('product_variant_id, SUM(quantity) as held')->groupBy('product_variant_id')->pluck('held', 'product_variant_id');
        $available = $stock->availableAtMany($variants, $shop);
        $movements = StockMovement::join('product_variants as v', 'v.id', '=', 'stock_movements.product_variant_id')
            ->selectRaw('v.product_id, COUNT(*) as n')->groupBy('v.product_id')->pluck('n', 'v.product_id');

        $products = $products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'garment_type' => $p->garment_type,
            'track_stock' => (bool) $p->track_stock,
            'sizes' => $p->variants->map(fn ($v) => [
                'id' => $v->id, 'part' => $v->part, 'size' => $v->size, 'label' => $v->label(), 'sku' => $v->sku, 'barcode' => $v->barcode,
                'active' => (bool) $v->active,
                'on_hand' => (int) ($levels[$v->id]->on_hand ?? 0),
                'version' => (int) ($levels[$v->id]->version ?? 0),
                'paid_awaiting_pickup' => max(0, -(int) ($awaitingPickup[$v->id] ?? 0)),
                'held' => (int) ($held[$v->id] ?? 0),
                'available' => $available[$v->id],
                'counted_at' => $levels[$v->id]->counted_at ?? null,
            ])->values()->all(),
            // The movements themselves load when the history is opened (history()).
            'history_count' => min(self::HISTORY, (int) ($movements[$p->id] ?? 0)),
        ])->all();

        return [
            'location' => $shop?->only(['id', 'name']),
            'products' => $products,
            'mode' => [
                'v2' => StockMode::v2(),
                'forced' => in_array((string) config('serana.stock_v2'), ['on', 'off', 'true', 'false', '1', '0'], true),
                'preview' => $withCutover ? app(StockCutover::class)->preview() : null,
            ],
        ];
    }

    /** A product's latest stock movements, with who and why, for its history on the Stock screen. */
    public static function history(Product $product): array
    {
        $names = User::pluck('name', 'id');
        $movements = StockMovement::whereIn('product_variant_id', $product->variants()->pluck('id'))
            ->with(['variant', 'location', 'operation'])->latest('id')->take(self::HISTORY)->get();
        $ciphers = \App\Models\CustomOrder::withTrashed()->whereIn('id', $movements->pluck('custom_order_id')->filter()->unique())->pluck('cipher', 'id');

        return $movements->map(fn (StockMovement $m) => [
            'id' => $m->id, 'type' => $m->type, 'size' => $m->variant?->label(), 'quantity' => $m->quantity,
            'by' => $m->actor_type === Actor::USER ? ($names[$m->actor_id] ?? 'Staff') : ucfirst($m->actor_type),
            'reason' => $m->reason, 'location' => $m->location?->name,
            'reference' => $m->operation?->reference, 'operation_id' => $m->stock_operation_id,
            'order' => $m->custom_order_id ? ($ciphers[$m->custom_order_id] ?? null) : null,
            'at' => $m->occurred_at?->copy()->timezone(config('app.display_timezone'))->format('d M Y, H:i'),
        ])->all();
    }
}
