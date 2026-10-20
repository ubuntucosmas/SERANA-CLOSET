<?php

namespace Tests\Concerns;

use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Services\StockService;
use App\Support\Actor;
use App\Support\StockMode;

/**
 * Stock fixtures that mean the same thing in either stock model (Phase D1:
 * the suite runs with STOCK_V2 off and on). A one-size product keeps the
 * numbers identical: N units left is batch_limit − batch_sold = N in the old
 * model and N on hand in its single size in the new one.
 */
trait StockFixtures
{
    /**
     * A stocked one-size product with $left units a shopper can buy (any
     * batch_sold in $attributes is what already left the shop): old model
     * batch_limit = sold + left; new model $left on hand.
     */
    protected function stockedProduct(int $left, array $attributes = []): Product
    {
        $sold = (int) ($attributes['batch_sold'] ?? 0);
        $product = Product::create(array_merge([
            'name' => 'Limited Piece ' . uniqid(), 'slug' => 'limited-' . uniqid(), 'price' => 5000, 'in_stock' => true,
            'garment_type' => 'hoodie', 'specifications' => ['sizes' => [ProductVariant::ONE_SIZE]],
            'batch_limit' => $sold + $left, 'batch_sold' => $sold,
        ], $attributes));
        $this->setOnHand($product, $left);

        return $product->fresh();
    }

    /** What's physically on hand of a one-size product (v2); the old model reads batch numbers. */
    protected function setOnHand(Product $product, int $units): void
    {
        if (! $product->track_stock) {
            return;
        }
        $variant = $this->oneSize($product);
        $level = StockLevel::firstOrCreate(['product_variant_id' => $variant->id, 'location_id' => Location::query()->online()->first()->id], ['on_hand' => 0]);
        if ($units !== $level->on_hand) {
            StockMovement::create(['product_variant_id' => $variant->id, 'location_id' => $level->location_id, 'type' => StockMovement::COUNT_CORRECTION,
                'quantity' => $units - $level->on_hand, 'actor_type' => Actor::SYSTEM, 'reason' => 'Test fixture', 'occurred_at' => now()]);
            $level->update(['on_hand' => $units, 'counted_at' => now()]);
        }
    }

    /** Someone else bought $units of it: batch_sold in the old model, a sale movement in the new one. */
    protected function soldElsewhere(Product $product, int $units = 1): void
    {
        $product->increment('batch_sold', $units);
        if (StockMode::v2() && $product->track_stock) {
            app(StockService::class)->move($this->oneSize($product), Location::query()->online()->first(), StockMovement::SALE, -$units, Actor::system(), 'Sold elsewhere (test)');
        }
    }

    /** A hold as checkout would leave it in the current model: product-level, or on the one size at the shop. */
    protected function holdFor(CustomOrder $order, Product $product, int $quantity, string $status = 'held', $expiresAt = null): StockReservation
    {
        $v2 = StockMode::v2();

        return $order->stockReservations()->create([
            'product_id' => $product->id,
            'product_variant_id' => $v2 ? $this->oneSize($product)->id : null,
            'location_id' => $v2 ? Location::query()->online()->first()->id : null,
            'quantity' => $quantity, 'status' => $status,
            'expires_at' => $expiresAt ?? ($status === 'held' ? now()->addMinutes(15) : now()->subMinutes(5)),
        ]);
    }

    protected function oneSize(Product $product): ProductVariant
    {
        return $product->variants()->where('active', true)->where('size', ProductVariant::ONE_SIZE)->first()
            ?? throw new \LogicException("{$product->name} is not a one-size product: give it specifications.sizes = ['One size'].");
    }

    /** Units of a one-size product still sold to nobody, in the current model. */
    protected function unitsLeft(Product $product): ?int
    {
        return $product->fresh()->available_stock;
    }
}
