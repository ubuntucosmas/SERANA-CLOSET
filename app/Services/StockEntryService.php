<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Actor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Human inventory entries: all rows commit together; retries never add units twice. */
final class StockEntryService
{
    public function __construct(private StockService $stock) {}

    /** Input has been validated by StockController. Returns true for an already completed retry. */
    public function save(Product $product, Location $shop, Actor $actor, array $data): bool
    {
        $rows = collect($data['rows'])->sortBy('variant_id')->map(fn ($row) => [
            'variant_id' => (int) $row['variant_id'], 'quantity' => (int) $row['quantity'],
            'expected_version' => isset($row['expected_version']) ? (int) $row['expected_version'] : null,
        ])->values()->all();
        $fingerprint = hash('sha256', json_encode([
            'actor' => [$actor->type, $actor->id], 'product' => $product->id, 'location' => $shop->id,
            'action' => $data['action'], 'reason' => trim($data['reason']),
            'reference' => trim($data['reference'] ?? ''), 'exclude_paid_pickups' => (bool) ($data['exclude_paid_pickups'] ?? false), 'rows' => $rows,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($product, $shop, $actor, $data, $rows, $fingerprint) {
            // The unique UUID serializes identical concurrent requests before any stock locks.
            DB::table('stock_operations')->insertOrIgnore([
                'id' => $data['operation_id'], 'fingerprint' => $fingerprint, 'user_id' => $actor->id,
                'product_id' => $product->id, 'location_id' => $shop->id, 'action' => $data['action'],
                'reason' => trim($data['reason']), 'reference' => trim($data['reference'] ?? '') ?: null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $operation = DB::table('stock_operations')->where('id', $data['operation_id'])->lockForUpdate()->first();
            if (! $operation || ! hash_equals($operation->fingerprint, $fingerprint)) {
                throw ValidationException::withMessages(['operation_id' => 'This entry number was already used for different details. Start a new entry.']);
            }
            if ($operation->completed_at !== null) {
                return true;
            }

            $variants = ProductVariant::where('product_id', $product->id)->whereIn('id', array_column($rows, 'variant_id'))->get()->keyBy('id');
            if (! $product->fresh()->track_stock || $variants->count() !== count($rows) || $variants->contains(fn ($variant) => ! $variant->active)) {
                throw ValidationException::withMessages(['rows' => 'Choose active sizes from this stocked product. Refresh stock before trying again.']);
            }
            $levels = $this->stock->lockLevels(array_map(fn ($row) => [$row['variant_id'], $shop->id], $rows))->keyBy('product_variant_id');
            foreach ($rows as $row) {
                $i = array_search($row['variant_id'], array_map('intval', array_column($data['rows'], 'variant_id')), true);
                if ($data['action'] === 'count' && (int) $levels[$row['variant_id']]->version !== $row['expected_version']) {
                    $variant = $variants[$row['variant_id']];
                    throw ValidationException::withMessages(["rows.{$i}.quantity" => "{$variant->label()} changed after this count was opened. Nothing was saved. Refresh quantities and recount unsold stock."]);
                }
            }
            foreach ($rows as $row) {
                $i = array_search($row['variant_id'], array_map('intval', array_column($data['rows'], 'variant_id')), true);
                $variant = $variants[$row['variant_id']];
                try {
                    $extra = ['stock_operation_id' => $data['operation_id']];
                    match ($data['action']) {
                        'count' => $this->stock->count($variant, $shop, $row['quantity'], $actor, trim($data['reason']), $extra),
                        'receive' => $this->stock->receive($variant, $shop, $row['quantity'], $actor, trim($data['reason']), $extra),
                        'damage' => $this->stock->damage($variant, $shop, $row['quantity'], $actor, trim($data['reason']), $extra),
                    };
                } catch (\DomainException $e) {
                    throw ValidationException::withMessages(["rows.{$i}.quantity" => $e->getMessage()]);
                }
            }
            DB::table('stock_operations')->where('id', $data['operation_id'])->update(['completed_at' => now(), 'updated_at' => now()]);

            return false;
        }, 3);
    }
}
