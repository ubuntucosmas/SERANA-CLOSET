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
    public function receive(ProductVariant $variant, Location $at, int $quantity, Actor $actor, string $reason, array $extra = []): StockMovement
    {
        $this->positive($quantity);

        return $this->move($variant, $at, StockMovement::RECEIVE, $quantity, $actor, $reason, $extra);
    }

    public function damage(ProductVariant $variant, Location $at, int $quantity, Actor $actor, string $reason, array $extra = []): StockMovement
    {
        $this->positive($quantity);

        return $this->move($variant, $at, StockMovement::DAMAGE, -$quantity, $actor, $reason, $extra);
    }

    /**
     * A stock take: set what is on hand to what was counted, as one
     * count_correction for the difference (none when it matches), and stamp
     * the count either way.
     */
    public function count(ProductVariant $variant, Location $at, int $counted, Actor $actor, string $reason, array $extra = []): ?StockMovement
    {
        if ($counted < 0) {
            throw new \DomainException('A count can’t be negative.');
        }
        $this->reason($actor, $reason);

        return DB::transaction(function () use ($variant, $at, $counted, $actor, $reason, $extra) {
            $level = $this->lockLevels([[$variant->id, $at->id]])->first();
            $movement = $counted !== $level->on_hand
                ? $this->write($variant, $at, $level, StockMovement::COUNT_CORRECTION, $counted - $level->on_hand, $actor, $reason, $extra)
                : null;
            $level->update(['counted_at' => now(), 'version' => $level->version + ($movement === null ? 1 : 0)]);
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
    public function allocate(array $demands, ?Location $only = null): array
    {
        if ($demands === []) {
            return [];
        }
        // Online: any location that fulfils online, by priority. At the till: that shop only.
        $locations = $only ? collect([$only]) : Location::query()->online()->get();
        $this->lockLevels(collect($demands)->flatMap(fn ($d) => $locations->map(fn ($l) => [$d['variant']->id, $l->id]))->all());

        $allocations = [];
        foreach ($demands as $demand) {
            $at = $locations->first(fn (Location $l) => $this->availableAt($demand['variant'], $l, current: true) >= $demand['quantity']);
            if (! $at) {
                $left = $only ? (int) $this->availableAt($demand['variant'], $only, current: true) : (int) $this->availableOnline($demand['variant']);
                throw new \DomainException($this->shortageMessage($demand['variant'], $left));
            }
            $allocations[] = ['variant' => $demand['variant'], 'location_id' => $at->id, 'quantity' => $demand['quantity'], 'second_part' => $demand['second_part']];
        }

        return $allocations;
    }

    /**
     * The old product-level model (until stock per size is on): lock the
     * products by id and check what is free, as checkout does. Returns the
     * quantity to hold per batch-limited product. Inside a transaction.
     *
     * @param  array<int, int>  $quantities  product id => units
     * @return array<int, int>
     */
    public function lockProductsForHold(array $quantities): array
    {
        ksort($quantities);
        $holds = [];
        foreach ($quantities as $productId => $quantity) {
            $product = Product::whereKey($productId)->lockForUpdate()->first();
            if ($product && $product->batch_limit !== null) {
                if ($quantity > $product->availableStock()) {
                    throw new \DomainException("{$product->name}: only {$product->availableStock()} left.");
                }
                $holds[$productId] = (int) $quantity;
            }
        }

        return $holds;
    }

    /**
     * Goods back over the counter (Phase E-d), as the stock writer. Restock:
     * the unit is on sale again (a 'return' movement at this shop with stock
     * per size; the old model's batch_sold goes down either way). Write off:
     * it came back but can't be sold ('return' then 'damage', so the history
     * shows both); the old model's count is unchanged. Untracked products
     * move nothing.
     *
     * @param  list<ProductVariant>  $variants  the sizes the line sold (a set: top and bottom)
     */
    public function takeBack(Product $product, array $variants, Location $at, int $quantity, string $disposition, Actor $actor, string $reason, int $orderId): void
    {
        if (! $product->track_stock) {
            return;
        }
        if ($disposition === 'restock') {
            Product::withTrashed()->whereKey($product->id)->where('batch_sold', '>=', $quantity)->decrement('batch_sold', $quantity);
        }
        if (! \App\Support\StockMode::v2()) {
            return;
        }
        foreach ($variants as $variant) {
            $variant->setRelation('product', $product->fresh());
            $this->move($variant, $at, StockMovement::RETURN, $quantity, $actor, $reason, ['custom_order_id' => $orderId]);
            if ($disposition === 'write_off') {
                $this->move($variant, $at, StockMovement::DAMAGE, -$quantity, $actor, "Written off on return: {$reason}", ['custom_order_id' => $orderId]);
            }
        }
    }

    /**
     * A paper slip from an outage (Phase E-g): the pieces physically left the
     * shop, so the slip is always recorded. Where the books no longer have
     * them free, the slip takes them back, in this order:
     *  1. unpaid online holds give way (no money yet; a later payment is
     *     "stock gone" under the Phase C rules);
     *  2. paid online orders not yet handed over give up their unit (a
     *     'return' movement, the hold released, batch_sold back), and are
     *     returned here for the caller to flag;
     *  3. anything still missing was never in the records: a count
     *     correction (or the old model's batch limit) brings it in, audited,
     *     so stock never goes below zero and nothing is silent.
     * Inside the caller's transaction, before the slip's own sale allocates.
     *
     * @return array<int, list<string>> paid order id => the pieces it lost
     */
    public function makeRoomForSlip(array $items, Collection $products, Location $at, string $slip, Actor $actor): array
    {
        $taken = [];
        $note = "Taken back for paper slip {$slip}: sold in the shop during an outage";
        $openOnline = fn ($o) => $o->where('channel', '!=', 'counter')->where('order_state', 'open');

        if (\App\Support\StockMode::v2()) {
            $plan = $this->demandsFor($items, $products);
            if ($plan['errors'] || $plan['demands'] === []) {
                return []; // the sale itself reports what is wrong
            }
            $this->lockLevels(collect($plan['demands'])->map(fn ($d) => [$d['variant']->id, $at->id])->all());
            foreach ($plan['demands'] as $demand) {
                $variant = $demand['variant'];
                $short = $demand['quantity'] - (int) $this->availableAt($variant, $at, current: true);
                $scope = fn ($q) => $q->where('product_variant_id', $variant->id)->where('location_id', $at->id);
                foreach (StockReservation::active()->where($scope)->whereHas('order', fn ($o) => $openOnline($o)->where('amount_paid', 0))->orderByDesc('id')->lockForUpdate()->get() as $hold) {
                    if ($short <= 0) {
                        break;
                    }
                    $hold->update(['status' => 'released']);
                    $short -= $hold->quantity;
                }
                foreach (StockReservation::where('status', 'committed')->where($scope)->whereHas('order', fn ($o) => $openOnline($o)->where('amount_paid', '>', 0)
                    ->whereNotIn('fulfilment_status', \App\Support\OrderStatus::PRODUCTION_TERMINAL))->orderByDesc('id')->lockForUpdate()->get() as $hold) {
                    if ($short <= 0) {
                        break;
                    }
                    $this->move($variant, $at, StockMovement::RETURN, $hold->quantity, $actor, $note, ['custom_order_id' => $hold->custom_order_id]);
                    if (! $hold->second_part) {
                        Product::withTrashed()->whereKey($hold->product_id)->where('batch_sold', '>=', $hold->quantity)->decrement('batch_sold', $hold->quantity);
                    }
                    $hold->update(['status' => 'released']);
                    $taken[$hold->custom_order_id][] = $this->describe($variant);
                    $short -= $hold->quantity;
                }
                if ($short > 0) {
                    $reason = "Paper slip {$slip} sold {$short} more of {$this->describe($variant)} than the records showed: count it";
                    $this->move($variant, $at, StockMovement::COUNT_CORRECTION, $short, $actor, $reason);
                    \App\Support\Audit::record('stock.slip_shortfall', $variant, [], ['slip' => $slip, 'units' => $short, 'location_id' => $at->id], $reason, $actor);
                }
            }

            return $taken;
        }

        // The old product-level model: batch numbers, no locations.
        $quantities = collect($items)->groupBy('id')->map(fn ($lines) => (int) $lines->sum('quantity'))->sortKeys();
        foreach ($quantities as $productId => $quantity) {
            $product = Product::whereKey($productId)->lockForUpdate()->first();
            if (! $product || $product->batch_limit === null) {
                continue;
            }
            $short = $quantity - (int) $product->availableStock();
            $scope = fn ($q) => $q->where('product_id', $productId)->where('second_part', false);
            foreach (StockReservation::active()->where($scope)->whereHas('order', fn ($o) => $openOnline($o)->where('amount_paid', 0))->orderByDesc('id')->lockForUpdate()->get() as $hold) {
                if ($short <= 0) {
                    break;
                }
                // A set's second-part row goes with it.
                StockReservation::where('custom_order_id', $hold->custom_order_id)->where('product_id', $productId)->where('status', 'held')->update(['status' => 'released']);
                $short -= $hold->quantity;
            }
            foreach (StockReservation::where('status', 'committed')->where($scope)->whereHas('order', fn ($o) => $openOnline($o)->where('amount_paid', '>', 0)
                ->whereNotIn('fulfilment_status', \App\Support\OrderStatus::PRODUCTION_TERMINAL))->orderByDesc('id')->lockForUpdate()->get() as $hold) {
                if ($short <= 0) {
                    break;
                }
                Product::withTrashed()->whereKey($productId)->where('batch_sold', '>=', $hold->quantity)->decrement('batch_sold', $hold->quantity);
                StockReservation::where('custom_order_id', $hold->custom_order_id)->where('product_id', $productId)->where('status', 'committed')->update(['status' => 'released']);
                $taken[$hold->custom_order_id][] = $product->name;
                $short -= $hold->quantity;
            }
            if ($short > 0) {
                $reason = "Paper slip {$slip} sold {$short} more of {$product->name} than the records showed: count it";
                $product->increment('batch_limit', $short);
                \App\Support\Audit::record('stock.slip_shortfall', $product, ['batch_limit' => $product->batch_limit - $short], ['batch_limit' => $product->batch_limit, 'slip' => $slip], $reason, $actor);
            }
        }

        return $taken;
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

    /**
     * Lock order steps 1 and 2 for an order (design §13.3): the level rows
     * of its open holds' sizes, by id, then the holds themselves, by id.
     *
     * @return Collection<int, StockReservation> the order's held and released holds, locked
     */
    public function lockForOrder(\App\Models\CustomOrder $order, array $morePairs = []): Collection
    {
        $pairs = $order->stockReservations()->whereIn('status', ['held', 'released'])->whereNotNull('product_variant_id')
            ->get(['product_variant_id', 'location_id'])->map(fn ($h) => [$h->product_variant_id, $h->location_id])->all();
        $this->lockLevels([...$pairs, ...$morePairs]);

        return $order->stockReservations()->whereIn('status', ['held', 'released'])->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * Sell an order's held sizes once it is paid (design §13.4): one sale
     * movement per hold, at its location. A live hold is ours (if the count
     * still covers it); a lapsed one is taken only if the size is still free,
     * at its location or another online one. If any size can't be served,
     * nothing is sold: the rest stay held until $keepUntil and the missing
     * sizes are returned ("Linen Set (Bottom L)").
     *
     * Holds without a size (only from before the cutover) are passengers: they
     * commit as they always did. Every piece still counts in batch_sold (a set
     * once), so switching back to the old model stays safe (§13.2).
     *
     * @return list<string>
     */
    public function commit(\App\Models\CustomOrder $order, ?\DateTimeInterface $keepUntil = null): array
    {
        return DB::transaction(function () use ($order, $keepUntil) {
            $rows = $this->lockForOrder($order);
            $short = $this->shortRows($rows, true);

            if ($short) {
                foreach ($rows as $row) {
                    if (! isset($short[$row->id])) {
                        $row->update(['status' => 'held', 'expires_at' => $keepUntil]);
                    } elseif ($row->status === 'held') {
                        $row->update(['status' => 'released']);
                    }
                }

                return array_values(array_unique($short));
            }

            foreach ($rows as $row) {
                if ($row->product_variant_id) {
                    $this->move($row->variant, $row->location, StockMovement::SALE, -$row->quantity, Actor::system(), 'Sold',
                        ['custom_order_id' => $order->id, 'stock_reservation_id' => $row->id]);
                }
                if (! $row->second_part) {
                    Product::withTrashed()->whereKey($row->product_id)->increment('batch_sold', $row->quantity);
                }
                $row->update(['status' => 'committed']);
            }

            return [];
        }, 3);
    }

    /**
     * Before an M-Pesa prompt takes money (design §13.4): re-take lapsed sizes
     * that are still free, give live holds a fresh window (never shortening a
     * longer one). False, changing nothing, if any size is gone.
     */
    public function secure(\App\Models\CustomOrder $order, \DateTimeInterface $window): bool
    {
        return DB::transaction(function () use ($order, $window) {
            $rows = $this->lockForOrder($order);
            if ($this->shortRows($rows, false)) {
                return false;
            }
            foreach ($rows as $row) {
                $keep = $row->status === 'held' && ($row->expires_at === null || $row->expires_at > $window);
                $row->update(['status' => 'held', 'expires_at' => $keep ? $row->expires_at : $window]);
            }

            return true;
        }, 3);
    }

    /**
     * Which locked holds can't be served now, by id => "Product (Size M)".
     * A lapsed hold no longer counts against availability, so its size must
     * be free again; it may move to another online location that has it.
     * With $live, a live hold must still be covered by what is on hand (a
     * stock take may have found fewer than were held).
     */
    private function shortRows(Collection $rows, bool $live): array
    {
        $short = [];
        $order = $rows->first()?->order;
        $locations = $order?->fulfilment_method === 'pickup'
            ? Location::whereKey($order->pickup_location_id)->where('active', true)->where('accepts_pickup', true)->get()
            : Location::query()->online()->get();
        // A lapsed hold with no size (from before the switch) can't be checked: the studio decides, it is never sold blind.
        foreach ($rows->whereNull('product_variant_id') as $row) {
            $lapsed = ! ($row->status === 'held' && ($row->expires_at === null || $row->expires_at->isFuture()));
            if ($lapsed && Product::withTrashed()->find($row->product_id)?->track_stock) {
                $short[$row->id] = Product::withTrashed()->find($row->product_id)?->name ?? 'An item';
            }
        }
        foreach ($rows->whereNotNull('product_variant_id')->groupBy(fn ($r) => "{$r->product_variant_id}:{$r->location_id}") as $group) {
            $first = $group->first();
            $variant = $first->variant;
            if (! $this->tracked($variant)) {
                continue;
            }
            $lapsed = $group->reject(fn ($r) => $r->status === 'held' && ($r->expires_at === null || $r->expires_at->isFuture()));
            $need = (int) $lapsed->sum('quantity');
            if ($need > 0 && $need > (int) $this->availableAt($variant, $first->location, current: true)) {
                $elsewhere = $locations->first(fn (Location $l) => $l->id !== $first->location_id && $this->availableAt($variant, $l, current: true) >= $need);
                if ($elsewhere) {
                    $lapsed->each->update(['location_id' => $elsewhere->id]);
                    $this->lockLevels([[$variant->id, $elsewhere->id]]);
                } else {
                    foreach ($group as $row) {
                        $short[$row->id] = $this->describe($variant);
                    }

                    continue;
                }
            }
            if ($live) {
                $onHand = (int) StockLevel::where('product_variant_id', $variant->id)->where('location_id', $first->location_id)->lockForUpdate()->value('on_hand');
                if ((int) $group->sum('quantity') > $onHand) {
                    foreach ($group as $row) {
                        $short[$row->id] = $this->describe($variant);
                    }
                }
            }
        }

        return $short;
    }

    /** "Linen Set (Bottom L)", for the Studio and the customer. */
    public function describe(ProductVariant $variant): string
    {
        $name = ($variant->relationLoaded('product') ? $variant->product : Product::withTrashed()->find($variant->product_id))?->name ?? 'An item';

        // A one-size piece is just its name; otherwise the size says which.
        return $variant->size === ProductVariant::ONE_SIZE ? $name : "{$name} ({$variant->label()})";
    }

    private function shortageMessage(ProductVariant $variant, int $left): string
    {
        $name = ($variant->relationLoaded('product') ? $variant->product : Product::withTrashed()->find($variant->product_id))?->name ?? 'An item';

        $what = $variant->size === ProductVariant::ONE_SIZE ? $name : "{$name} in {$variant->label()}";

        return $left > 0 ? "{$what}: only {$left} left." : "{$what} just sold out.";
    }

    /**
     * Units that can be sold at one location: on hand less its active holds,
     * never below zero. Null = made to order.
     *
     * $current: a decision made while holding this size's level lock (lock
     * order step 1) reads the rows as they are now, with locking reads. Under
     * REPEATABLE READ a plain read may come from a snapshot taken before the
     * lock was granted; a locking read never does. Every writer of holds takes
     * the level lock first, so these reads can't join a lock cycle.
     */
    public function availableAt(ProductVariant $variant, Location $at, bool $current = false): ?int
    {
        if (! $this->tracked($variant)) {
            return null;
        }
        $level = StockLevel::where('product_variant_id', $variant->id)->where('location_id', $at->id);
        $holds = StockReservation::active()->where('product_variant_id', $variant->id)->where('location_id', $at->id);
        $onHand = (int) ($current ? $level->lockForUpdate()->value('on_hand') : $level->value('on_hand'));
        $held = (int) ($current ? $holds->sharedLock()->sum('quantity') : $holds->sum('quantity'));

        return max(0, $onHand - $held);
    }

    /**
     * availableAt() for many sizes at one location in two queries, for lists
     * (Stock screen, Sales Desk search). Plain reads, the same rule; never use
     * it to decide a sale: writers lock with availableAt(..., current: true).
     *
     * @param  \Illuminate\Support\Collection<int, ProductVariant>  $variants  with product loaded
     * @return array<int, ?int> variant id => available (null = made to order)
     */
    public function availableAtMany(Collection $variants, ?Location $at): array
    {
        $ids = $variants->pluck('id');
        $onHand = $at ? StockLevel::whereIn('product_variant_id', $ids)->where('location_id', $at->id)->pluck('on_hand', 'product_variant_id') : collect();
        $held = $at ? StockReservation::active()->whereIn('product_variant_id', $ids)->where('location_id', $at->id)
            ->selectRaw('product_variant_id, SUM(quantity) as held')->groupBy('product_variant_id')->pluck('held', 'product_variant_id') : collect();

        return $variants->mapWithKeys(fn (ProductVariant $v) => [$v->id => $at && $this->tracked($v)
            ? max(0, (int) ($onHand[$v->id] ?? 0) - (int) ($held[$v->id] ?? 0)) : null])->all();
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
     * Availability for a whole product list in two queries (on hand and
     * active holds, at online locations), cached on each model so cards and
     * tables don't query per product. Same rule as productAvailableOnline().
     */
    public function preloadAvailability(Collection $products): void
    {
        $tracked = $products->filter(fn (Product $p) => $p->track_stock);
        $products->each(fn (Product $p) => $p->preloadedAvailable = $p->track_stock ? 0 : null);
        if ($tracked->isEmpty()) {
            return;
        }
        $variants = ProductVariant::whereIn('product_id', $tracked->pluck('id'))->where('active', true)->get(['id', 'product_id', 'part']);
        $online = Location::query()->online()->pluck('id');
        $key = fn ($v, $l) => "{$v}:{$l}";
        $onHand = StockLevel::whereIn('product_variant_id', $variants->pluck('id'))->whereIn('location_id', $online)->get(['product_variant_id', 'location_id', 'on_hand'])
            ->mapWithKeys(fn ($l) => [$key($l->product_variant_id, $l->location_id) => (int) $l->on_hand]);
        $held = StockReservation::active()->whereIn('product_variant_id', $variants->pluck('id'))->whereIn('location_id', $online)
            ->selectRaw('product_variant_id, location_id, SUM(quantity) as held')->groupBy('product_variant_id', 'location_id')->get()
            ->mapWithKeys(fn ($h) => [$key($h->product_variant_id, $h->location_id) => (int) $h->held]);

        $byVariant = $variants->mapWithKeys(fn ($v) => [$v->id => $online->sum(fn ($l) => max(0, ($onHand[$key($v->id, $l)] ?? 0) - ($held[$key($v->id, $l)] ?? 0)))]);
        // Grouped once: filtering every variant per product made 500 products take ~2 s.
        $variantsOf = $variants->groupBy('product_id');
        foreach ($tracked as $product) {
            $parts = $variantsOf->get($product->id, collect())->groupBy('part')->map(fn ($vs) => (int) $vs->sum(fn ($v) => $byVariant[$v->id]));
            $product->preloadedAvailable = $parts->isEmpty() ? 0 : (int) $parts->min();
        }
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
     * Set the old model's batch_limit so it offers exactly what stock per
     * size has on hand online (design §13.2): sold + on hand, a set by its
     * scarcer part. Keeps switching back safe.
     */
    public function rebaseline(?Product $product): void
    {
        if (! $product || ! $product->track_stock) {
            return;
        }
        $online = Location::query()->online()->pluck('id');
        $byPart = $product->variants()->where('active', true)->get()->groupBy('part')
            ->map(fn ($variants) => (int) StockLevel::whereIn('product_variant_id', $variants->pluck('id'))->whereIn('location_id', $online)->sum('on_hand'));
        $limit = (int) $product->fresh()->batch_sold + (int) ($byPart->min() ?? 0);
        \Illuminate\Support\Facades\DB::table('products')->where('id', $product->id)->update(['batch_limit' => $limit]);
        $product->batch_limit = $limit;
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
        $level->update(['on_hand' => $after, 'version' => $level->version + 1]);
        // While stock per size is the truth, the old batch numbers follow it (design §13.2):
        // a sale already counts in batch_sold; anything else moves the baseline.
        if ($type !== StockMovement::SALE && \App\Support\StockMode::v2()) {
            $this->rebaseline($variant->product ?? Product::withTrashed()->find($variant->product_id));
        }

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
