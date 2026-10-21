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
    public static function forStudio(bool $withCutover): array
    {
        $stock = app(StockService::class);
        $shop = Location::query()->online()->first();
        $names = User::pluck('name', 'id');

        $products = Product::with(['variants' => fn ($q) => $q->orderBy('sort')])->orderBy('name')->get()->map(function (Product $p) use ($stock, $shop, $names) {
            $ids = $p->variants->pluck('id');
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

            return [
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
                    'available' => $shop && $p->track_stock ? $stock->availableAt($v->setRelation('product', $p), $shop) : null,
                    'counted_at' => $levels[$v->id]->counted_at ?? null,
                ])->values()->all(),
                'history' => StockMovement::whereIn('product_variant_id', $ids)->with(['variant', 'location', 'operation'])->latest('id')->take(50)->get()
                    ->map(fn (StockMovement $m) => [
                        'id' => $m->id, 'type' => $m->type, 'size' => $m->variant?->label(), 'quantity' => $m->quantity,
                        'by' => $m->actor_type === Actor::USER ? ($names[$m->actor_id] ?? 'Staff') : ucfirst($m->actor_type),
                        'reason' => $m->reason, 'location' => $m->location?->name,
                        'reference' => $m->operation?->reference, 'operation_id' => $m->stock_operation_id,
                        'order' => $m->custom_order_id ? \App\Models\CustomOrder::withTrashed()->whereKey($m->custom_order_id)->value('cipher') : null,
                        'at' => $m->occurred_at?->copy()->timezone(config('app.display_timezone'))->format('d M Y, H:i'),
                    ])->all(),
            ];
        })->all();

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
}
