<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Support\Actor;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single writer of stock (design §13.1): stock levels and stock
 * movements, and (from step 4) the variant holds. Every movement is written
 * with its level in one transaction, under the level row lock; a human
 * movement needs an actor and a reason and is audited.
 *
 * Lock order (design §13.3): stock_levels rows by id, then stock holds by
 * id, then the order row, then ledger rows. lockLevels() takes the first step.
 */
class StockService
{
    public function receive(ProductVariant $variant, Location $at, int $quantity, Actor $actor, string $reason): StockMovement
    {
        $this->positive($quantity);

        return $this->move($variant, $at, StockMovement::RECEIVE, $quantity, $actor, $reason);
    }

    public function damage(ProductVariant $variant, Location $at, int $quantity, Actor $actor, string $reason): StockMovement
    {
        $this->positive($quantity);

        return $this->move($variant, $at, StockMovement::DAMAGE, -$quantity, $actor, $reason);
    }

    /**
     * A stock take: set what is on hand to what was counted, as one
     * count_correction for the difference (none when it matches), and stamp
     * the count either way.
     */
    public function count(ProductVariant $variant, Location $at, int $counted, Actor $actor, string $reason): ?StockMovement
    {
        if ($counted < 0) {
            throw new \DomainException('A count can’t be negative.');
        }
        $this->reason($actor, $reason);

        return DB::transaction(function () use ($variant, $at, $counted, $actor, $reason) {
            $level = $this->lockLevels([[$variant->id, $at->id]])->first();
            $movement = $counted !== $level->on_hand
                ? $this->write($variant, $at, $level, StockMovement::COUNT_CORRECTION, $counted - $level->on_hand, $actor, $reason)
                : null;
            $level->update(['counted_at' => now()]);
            if ($movement === null && $actor->type === Actor::USER) {
                Audit::record('stock.counted', $variant, [], ['location' => $at->id, 'on_hand' => $counted], $reason, $actor);
            }

            return $movement;
        }, 3);
    }

    /** Move units between locations: a transfer_out and a transfer_in sharing one transfer id. */
    public function transfer(ProductVariant $variant, Location $from, Location $to, int $quantity, Actor $actor, string $reason): array
    {
        $this->positive($quantity);
        $this->reason($actor, $reason);
        if ($from->is($to)) {
            throw new \DomainException('Choose two different locations.');
        }

        return DB::transaction(function () use ($variant, $from, $to, $quantity, $actor, $reason) {
            $levels = $this->lockLevels([[$variant->id, $from->id], [$variant->id, $to->id]])->keyBy('location_id');
            $id = (string) \Illuminate\Support\Str::uuid();

            return [
                $this->write($variant, $from, $levels[$from->id], StockMovement::TRANSFER_OUT, -$quantity, $actor, $reason, ['transfer_id' => $id]),
                $this->write($variant, $to, $levels[$to->id], StockMovement::TRANSFER_IN, $quantity, $actor, $reason, ['transfer_id' => $id]),
            ];
        }, 3);
    }

    /** One movement, with its level, under the level lock. */
    public function move(ProductVariant $variant, Location $at, string $type, int $quantity, Actor $actor, ?string $reason, array $extra = []): StockMovement
    {
        $this->reason($actor, $reason);

        return DB::transaction(function () use ($variant, $at, $type, $quantity, $actor, $reason, $extra) {
            $level = $this->lockLevels([[$variant->id, $at->id]])->first();

            return $this->write($variant, $at, $level, $type, $quantity, $actor, $reason, $extra);
        }, 3);
    }

    /**
     * Step 1 of the lock order: the level rows for these (variant, location)
     * pairs, created at zero if missing, locked in id order.
     *
     * @param  list<array{0: int, 1: int}>  $pairs
     * @return Collection<int, StockLevel>
     */
    public function lockLevels(array $pairs): Collection
    {
        $pairs = collect($pairs)->unique(fn ($p) => "{$p[0]}:{$p[1]}")->values();
        if ($pairs->isEmpty()) {
            return collect();
        }
        DB::table('stock_levels')->insertOrIgnore($pairs->map(fn ($p) => [
            'product_variant_id' => $p[0], 'location_id' => $p[1], 'on_hand' => 0, 'created_at' => now(), 'updated_at' => now(),
        ])->all());

        return StockLevel::where(function ($q) use ($pairs) {
            foreach ($pairs as [$variant, $location]) {
                $q->orWhere(fn ($w) => $w->where('product_variant_id', $variant)->where('location_id', $location));
            }
        })->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * What a bag asks for, per size (design §13.6): each line resolved to its
     * variant(s) — a set to its top and bottom — and summed per variant.
     * Lines the server can't read as sizes come back as errors, never guessed.
     *
     * @param  array<int, array{id: int, quantity: int, options?: ?array, variant_ids?: ?array}>  $items
     * @return array{demands: list<array{variant: ProductVariant, quantity: int, second_part: bool}>, variant_ids: array<int, list<int>>, errors: list<array{index: int, product_id: int, message: string}>}
     */
    public function demandsFor(array $items, Collection $products): array
    {
        $demands = [];
        $variantIds = [];
        $errors = [];
        foreach (array_values($items) as $index => $item) {
            $product = $products->get($item['id']);
            $variants = $product ? ProductVariant::resolve($product, $item['options'] ?? null) : null;
            $claimed = isset($item['variant_ids']) ? collect($item['variant_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all() : null;
            if ($variants === null || ($claimed !== null && $claimed !== collect($variants)->pluck('id')->sort()->values()->all())) {
                $errors[] = ['index' => $index, 'product_id' => (int) $item['id'], 'message' => 'Choose a size for ' . ($product?->name ?? 'this item') . '.'];

                continue;
            }
            $variantIds[$index] = collect($variants)->pluck('id')->all();
            if (! $product->track_stock) {
                continue;
            }
            $firstPart = ProductVariant::partsFor($product->garment_type)[0];
            foreach ($variants as $variant) {
                $variant->setRelation('product', $product);
                $demands[$variant->id] ??= ['variant' => $variant, 'quantity' => 0, 'second_part' => $variant->part !== $firstPart];
                $demands[$variant->id]['quantity'] += (int) $item['quantity'];
            }
        }

        return ['demands' => array_values($demands), 'variant_ids' => $variantIds, 'errors' => $errors];
    }

    /** Why these demands can't be met right now, read without locks (a friendly pre-check), or null. */
    public function shortageFor(array $demands): ?string
    {
        foreach ($demands as $demand) {
            $left = (int) $this->availableOnline($demand['variant']);
            if ($demand['quantity'] > $left) {
                return $this->shortageMessage($demand['variant'], $left);
            }
        }

        return null;
    }

    /**
     * Inside a transaction: lock the level rows of every demanded size at
     * every online location (lock order step 1), and pick, per size, the
     * first location by priority that has all of it (design §13.3; a line is
     * never split across locations). Throws a DomainException naming the size
     * when one can't be met.
     *
     * @return list<array{variant: ProductVariant, location_id: int, quantity: int, second_part: bool}>
     */
    public function allocate(array $demands): array
    {
        if ($demands === []) {
            return [];
        }
        $locations = Location::query()->online()->get();
        $this->lockLevels(collect($demands)->flatMap(fn ($d) => $locations->map(fn ($l) => [$d['variant']->id, $l->id]))->all());

        $allocations = [];
        foreach ($demands as $demand) {
            $at = $locations->first(fn (Location $l) => $this->availableAt($demand['variant'], $l) >= $demand['quantity']);
            if (! $at) {
                throw new \DomainException($this->shortageMessage($demand['variant'], (int) $this->availableOnline($demand['variant'])));
            }
            $allocations[] = ['variant' => $demand['variant'], 'location_id' => $at->id, 'quantity' => $demand['quantity'], 'second_part' => $demand['second_part']];
        }

        return $allocations;
    }

    /** Write the holds an allocation decided, for an order, inside the same transaction. */
    public function hold(\App\Models\CustomOrder $order, array $allocations, \DateTimeInterface $until): void
    {
        foreach ($allocations as $a) {
            $order->stockReservations()->create(['product_id' => $a['variant']->product_id, 'product_variant_id' => $a['variant']->id,
                'location_id' => $a['location_id'], 'quantity' => $a['quantity'], 'second_part' => $a['second_part'],
                'status' => 'held', 'expires_at' => $until]);
        }
    }

    private function shortageMessage(ProductVariant $variant, int $left): string
    {
        $name = ($variant->relationLoaded('product') ? $variant->product : Product::withTrashed()->find($variant->product_id))?->name ?? 'An item';

        return $left > 0 ? "{$name} in {$variant->label()}: only {$left} left." : "{$name} in {$variant->label()} just sold out.";
    }

    /** Units that can be sold at one location: on hand less its active holds, never below zero. Null = made to order. */
    public function availableAt(ProductVariant $variant, Location $at): ?int
    {
        if (! $this->tracked($variant)) {
            return null;
        }
        $onHand = (int) StockLevel::where('product_variant_id', $variant->id)->where('location_id', $at->id)->value('on_hand');
        $held = (int) StockReservation::active()->where('product_variant_id', $variant->id)->where('location_id', $at->id)->sum('quantity');

        return max(0, $onHand - $held);
    }

    /** Units of one size that can be bought online: Σ over online locations. Null = made to order. */
    public function availableOnline(ProductVariant $variant): ?int
    {
        if (! $this->tracked($variant)) {
            return null;
        }

        return (int) Location::query()->online()->get()->sum(fn (Location $at) => $this->availableAt($variant, $at));
    }

    /**
     * Every active size of a product with its online availability, for the
     * size picker (null = made to order).
     *
     * @return list<array{id: int, part: string, size: string, available: ?int}>
     */
    public function sizesOnline(Product $product): array
    {
        return $product->variants()->where('active', true)->orderBy('sort')->get()
            ->map(fn (ProductVariant $v) => ['id' => $v->id, 'part' => $v->part, 'size' => $v->size, 'available' => $this->availableOnline($v)])
            ->all();
    }

    /**
     * Pieces of a product that can be bought online, for product cards: a
     * single piece sums its sizes; a set counts complete sets (the scarcer
     * part). Null = made to order.
     */
    public function productAvailableOnline(Product $product): ?int
    {
        if (! $product->track_stock) {
            return null;
        }
        $byPart = collect($this->sizesOnline($product))->groupBy('part')->map(fn ($sizes) => (int) $sizes->sum('available'));

        return $byPart->isEmpty() ? 0 : (int) $byPart->min();
    }

    /**
     * What the stock reconciliation checks about the new model itself:
     * every level equals the sum of its movements, and nothing is negative.
     *
     * @return list<string>
     */
    public function integrityIssues(): array
    {
        $issues = [];
        $sums = StockMovement::selectRaw('product_variant_id, location_id, SUM(quantity) as total')->groupBy('product_variant_id', 'location_id')->get()
            ->keyBy(fn ($m) => "{$m->product_variant_id}:{$m->location_id}");
        foreach (StockLevel::with('variant')->get() as $level) {
            $total = (int) ($sums["{$level->product_variant_id}:{$level->location_id}"]->total ?? 0);
            if ($total !== $level->on_hand) {
                $issues[] = "{$level->variant?->sku} at location {$level->location_id}: on hand {$level->on_hand}, movements add up to {$total}";
            }
        }

        return $issues;
    }

    private function write(ProductVariant $variant, Location $at, StockLevel $level, string $type, int $quantity, Actor $actor, ?string $reason, array $extra = []): StockMovement
    {
        $after = $level->on_hand + $quantity;
        if ($after < 0) {
            $product = $variant->product?->name ?? 'This item';
            throw new \DomainException("Only {$level->on_hand} of {$product} ({$variant->label()}) is on hand at {$at->name}.");
        }

        $movement = StockMovement::create(array_merge([
            'product_variant_id' => $variant->id, 'location_id' => $at->id, 'type' => $type, 'quantity' => $quantity,
            'actor_type' => $actor->type, 'actor_id' => $actor->id, 'reason' => $reason !== null ? mb_substr(trim($reason), 0, 255) : null,
            'occurred_at' => now(),
        ], $extra));
        $level->update(['on_hand' => $after]);

        if ($actor->type === Actor::USER) {
            Audit::record('stock.moved', $variant, ['on_hand' => $after - $quantity],
                ['on_hand' => $after, 'type' => $type, 'quantity' => $quantity, 'location' => $at->id, 'movement' => $movement->id], $reason, $actor);
        }

        return $movement;
    }

    private function tracked(ProductVariant $variant): bool
    {
        return (bool) ($variant->relationLoaded('product') ? $variant->product : Product::withTrashed()->find($variant->product_id))?->track_stock;
    }

    private function positive(int $quantity): void
    {
        if ($quantity < 1) {
            throw new \DomainException('Enter a quantity of at least 1.');
        }
    }

    /** A person always says why (design: actor + reason on every human action). */
    private function reason(Actor $actor, ?string $reason): void
    {
        if ($actor->type === Actor::USER && trim((string) $reason) === '') {
            throw new \DomainException('Say why, for the stock history.');
        }
    }
}
