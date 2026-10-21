<?php

namespace App\Services;

use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

/**
 * Phase D1 (design §13.5.1): bring the stock-per-size model up to date with
 * the catalogue and the live holds, without changing what the old model
 * sells. Idempotent: safe to run again.
 *
 * - every product gets a variant per offered size and part;
 * - a size seen on a past order but no longer offered becomes an inactive
 *   variant, so history keeps its exact size;
 * - every held or released hold is mapped to its variant(s) at the online
 *   location, from its order's size options. A set hold becomes one hold per
 *   part. A hold whose sizes can't be read exactly is left unmapped and
 *   counted, never guessed.
 *
 * Only writes columns that exist at migration 2026_10_20_000002.
 */
class StockBackfill
{
    /** @return array{variants_created: int, holds_mapped: int, unmapped_holds: int} */
    public function run(): array
    {
        $report = ['variants_created' => 0, 'holds_mapped' => 0, 'unmapped_holds' => 0];
        $location = Location::query()->online()->first();

        // Sizes ever ordered, per product and part.
        $seen = [];
        CustomOrder::withTrashed()->whereNotNull('items_json')->select(['id', 'items_json'])->chunkById(200, function ($orders) use (&$seen) {
            foreach ($orders as $order) {
                foreach ($this->items($order) as $item) {
                    foreach (['top_size' => ProductVariant::TOP, 'bottom_size' => ProductVariant::BOTTOM] as $key => $part) {
                        if (! empty($item['options'][$key])) {
                            $seen[(int) $item['id']][$key][] = trim((string) $item['options'][$key]);
                        }
                    }
                }
            }
        });

        Product::withTrashed()->orderBy('id')->each(function (Product $product) use ($seen, &$report) {
            $extra = [];
            foreach (ProductVariant::partsFor($product->garment_type) as $part) {
                foreach (ProductVariant::optionFor($part) as $key) {
                    $extra[$part] = array_values(array_unique([...($extra[$part] ?? []), ...($seen[$product->id][$key] ?? [])]));
                }
            }
            $report['variants_created'] += ProductVariant::ensureFor($product, $extra);
        });

        StockReservation::whereIn('status', ['held', 'released'])->whereNull('product_variant_id')->orderBy('id')
            ->each(function (StockReservation $hold) use ($location, &$report) {
                $split = $this->split($hold);
                if ($split === null || ! $location) {
                    $report['unmapped_holds']++;

                    return;
                }
                DB::transaction(function () use ($hold, $split, $location) {
                    // The first part's rows keep counting for the product; a set's bottom rows don't.
                    $first = array_shift($split);
                    $hold->update(['product_variant_id' => $first[0], 'location_id' => $location->id, 'quantity' => $first[1], 'second_part' => $first[2]]);
                    foreach ($split as [$variantId, $quantity, $second]) {
                        StockReservation::create(['custom_order_id' => $hold->custom_order_id, 'product_id' => $hold->product_id,
                            'product_variant_id' => $variantId, 'location_id' => $location->id, 'quantity' => $quantity, 'second_part' => $second,
                            'status' => $hold->status, 'expires_at' => $hold->expires_at]);
                    }
                });
                $report['holds_mapped']++;
            });

        return $report;
    }

    /**
     * The (variant id, quantity) pairs a product-level hold stands for, from
     * its order's lines for that product; null unless they add up exactly.
     *
     * @return list<array{0: int, 1: int, 2: bool}>|null (variant id, quantity, second part)
     */
    private function split(StockReservation $hold): ?array
    {
        $order = CustomOrder::withTrashed()->find($hold->custom_order_id);
        $product = Product::withTrashed()->find($hold->product_id);
        if (! $order || ! $product) {
            return null;
        }

        $units = 0;
        $byVariant = [];
        foreach ($this->items($order) as $item) {
            if ((int) $item['id'] !== $product->id) {
                continue;
            }
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $variants = ProductVariant::resolve($product, $item['options'] ?? null, activeOnly: false);
            if ($variants === null) {
                return null;
            }
            $units += $quantity;
            foreach ($variants as $variant) {
                $byVariant[$variant->id] = ($byVariant[$variant->id] ?? 0) + $quantity;
            }
        }

        if ($units !== (int) $hold->quantity || $byVariant === []) {
            return null;
        }
        $firstPart = ProductVariant::partsFor($product->garment_type)[0];
        $parts = ProductVariant::whereIn('id', array_keys($byVariant))->pluck('part', 'id');
        // First-part rows first, so the original row (which the old model already counts) stays a counting row.
        uksort($byVariant, fn ($a, $b) => [$parts[$a] !== $firstPart, $a] <=> [$parts[$b] !== $firstPart, $b]);

        return array_map(fn ($id, $q) => [$id, $q, $parts[$id] !== $firstPart], array_keys($byVariant), $byVariant);
    }

    /** The order's real line items (items_json also carries non-item keys). */
    private function items(CustomOrder $order): array
    {
        $items = is_array($order->items_json) ? $order->items_json : [];

        return array_values(array_filter($items, fn ($item, $key) => $key !== 'precision_sizing' && is_array($item) && isset($item['id']), ARRAY_FILTER_USE_BOTH));
    }
}
