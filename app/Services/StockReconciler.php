<?php

namespace App\Services;

use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Support\StockMode;

/**
 * Does stock per size agree with the old model, and with itself (design
 * §13.5.3)? Used by serana:stock:reconcile, the cutover and preflight.
 *
 * - differences: per tracked product, what the old batch numbers say a
 *   shopper can buy against what the size levels say. Expected before the
 *   cutover (the count corrects them); a failure while v2 is on.
 * - uncounted: tracked sizes nobody has counted yet.
 * - to_replay: sales the old model made after a size was counted, which the
 *   cutover writes as sale movements; a failure while v2 is on.
 * - integrity: always a failure. Levels equal to their movements, nothing
 *   negative, and (v2 on) no live hold without a size.
 */
class StockReconciler
{
    public function __construct(private StockService $stock)
    {
    }

    public function report(): array
    {
        $tracked = Product::where('track_stock', true)->with('variants')->orderBy('id')->get();
        $shop = Location::query()->online()->first();

        $differences = [];
        foreach ($tracked as $product) {
            $old = $product->availableStock();
            $new = $this->stock->productAvailableOnline($product);
            if ($old !== $new) {
                $differences[] = "{$product->name}: old model {$old}, stock per size {$new}";
            }
        }

        $uncounted = [];
        foreach ($tracked as $product) {
            foreach ($product->variants->where('active', true) as $variant) {
                if (! StockLevel::where('product_variant_id', $variant->id)->whereNotNull('counted_at')->exists()) {
                    $uncounted[] = $this->stock->describe($variant->setRelation('product', $product));
                }
            }
        }

        $integrity = $this->stock->integrityIssues();
        if (StockMode::v2()) {
            foreach (StockReservation::where('status', 'held')->whereNull('product_variant_id')->with('order')->get() as $hold) {
                $integrity[] = "A live hold on order {$hold->order?->cipher} has no size";
            }
        }

        return ['v2' => StockMode::v2(), 'differences' => $differences, 'uncounted' => $uncounted,
            'to_replay' => array_map(fn ($r) => $r['label'], $this->replays()), 'unreadable_sales' => $this->unreadableSales(), 'integrity' => $integrity,
            'shop' => $shop?->name];
    }

    /** What must be fixed: integrity always; with v2 on, any difference or unreplayed sale too. */
    public function failures(array $report): array
    {
        return $report['v2']
            ? [...$report['integrity'], ...$report['differences'], ...$report['to_replay'], ...$report['unreadable_sales']]
            : $report['integrity'];
    }

    /**
     * Sales the old model committed after their size was counted, with no
     * sale movement yet: (hold, variant, location, quantity, order, label).
     * An old product-level hold of a set gives two (top and bottom).
     *
     * @return list<array{reservation: StockReservation, variant: ProductVariant, location_id: int, quantity: int, order: CustomOrder, label: string}>
     */
    public function replays(): array
    {
        $replays = [];
        foreach ($this->committedAfterCount() as [$hold, $order, $parts]) {
            foreach ($parts ?? [] as [$variant, $quantity]) {
                $level = StockLevel::where('product_variant_id', $variant->id)->whereNotNull('counted_at')->orderBy('counted_at')->first();
                if (! $level || $order->paid_at <= $level->counted_at) {
                    continue;
                }
                if (StockMovement::where('stock_reservation_id', $hold->id)->where('product_variant_id', $variant->id)->exists()) {
                    continue;
                }
                $replays[] = ['reservation' => $hold, 'variant' => $variant, 'location_id' => $level->location_id, 'quantity' => $quantity,
                    'order' => $order, 'label' => $this->stock->describe($variant) . ": order {$order->cipher}"];
            }
        }

        return $replays;
    }

    /** Old sales after a count whose sizes can't be read from their order: the owner must recount those products. */
    public function unreadableSales(): array
    {
        $out = [];
        foreach ($this->committedAfterCount() as [$hold, $order, $parts]) {
            if ($parts === null) {
                $out[] = "Order {$order->cipher}: a sale after the count whose size can't be read";
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<array{0: StockReservation, 1: CustomOrder, 2: ?list<array{0: ProductVariant, 1: int}>}> */
    private function committedAfterCount(): array
    {
        $firstCount = StockLevel::whereNotNull('counted_at')->min('counted_at');
        if ($firstCount === null) {
            return [];
        }
        $rows = [];
        StockReservation::where('status', 'committed')
            ->whereHas('product', fn ($q) => $q->where('track_stock', true))
            ->whereHas('order', fn ($q) => $q->where('paid_at', '>', $firstCount))
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('stock_movements')->whereColumn('stock_movements.stock_reservation_id', 'stock_reservations.id'))
            ->with(['order', 'variant', 'product'])->orderBy('id')
            ->each(function (StockReservation $hold) use (&$rows) {
                $rows[] = [$hold, $hold->order, $hold->variant ? [[$hold->variant, (int) $hold->quantity]] : $this->partsOf($hold)];
            });

        return $rows;
    }

    /** The sizes an old product-level hold stood for, read from its order (null when not exact). */
    private function partsOf(StockReservation $hold): ?array
    {
        $parts = [];
        $units = 0;
        foreach ((array) $hold->order?->items_json as $key => $item) {
            if ($key === 'precision_sizing' || ! is_array($item) || (int) ($item['id'] ?? 0) !== $hold->product_id) {
                continue;
            }
            $variants = ProductVariant::resolve($hold->product, $item['options'] ?? null, activeOnly: false);
            if ($variants === null) {
                return null;
            }
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $units += $quantity;
            foreach ($variants as $variant) {
                $parts[$variant->id] ??= [$variant, 0];
                $parts[$variant->id][1] += $quantity;
            }
        }

        return $units === (int) $hold->quantity && $parts ? array_values($parts) : null;
    }
}
