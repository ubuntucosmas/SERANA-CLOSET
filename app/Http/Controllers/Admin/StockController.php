<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StockCutover;
use App\Services\StockService;
use App\Services\StockEntryService;
use App\Support\Actor;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Studio → Stock (Phase D1): every change to stock is made here by a person,
 * with a reason, through StockService (the single writer). The switch to
 * stock per size and back is the owner's.
 */
class StockController extends Controller
{
    /** A product's stock history, loaded when staff open it on the Stock screen. */
    public function history(Product $product)
    {
        return response()->json(['history' => \App\Support\StudioStock::history($product)]);
    }

    public function options(Request $request, Product $product, StockService $stock)
    {
        $request->validate(['order_id' => ['nullable', 'integer', 'exists:custom_orders,id']]);
        $order = $request->filled('order_id') ? \App\Models\CustomOrder::findOrFail($request->integer('order_id')) : null;
        $v2 = \App\Support\StockMode::v2();
        $variants = $product->variants()->where('active', true)->orderBy('sort')->get();
        $location = $order?->fulfilment_method === 'pickup' ? \App\Models\Location::find($order->pickup_location_id) : null;
        $sizes = $variants->map(fn ($variant) => [
            'id' => $variant->id, 'part' => $variant->part, 'size' => $variant->size,
            'available' => ! $product->in_stock ? 0 : ($v2 ? ($location ? $stock->availableAt($variant, $location) : $stock->availableOnline($variant)) : $product->availableStock()),
        ])->all();
        if (! $v2 && ! $sizes) {
            $list = data_get($product->specifications, 'ui.sizes') ?: data_get($product->specifications, 'sizes') ?: ['S', 'M', 'L', 'XL', 'XXL'];
            $parts = $product->garment_type === 'set' ? ['top', 'bottom'] : ($product->garment_type === 'jogger' ? ['bottom'] : ['single']);
            foreach ($parts as $part) foreach ($list as $size) $sizes[] = ['id' => null, 'part' => $part, 'size' => $size, 'available' => $product->in_stock ? $product->availableStock() : 0];
        }
        return response()->json(['sizes' => $sizes, 'where' => $location?->name ?: 'Online fulfilment stock']);
    }

    /** The single-row route uses the same safeguards as the size matrix. */
    public function move(Request $request, StockEntryService $entries)
    {
        $request->validate(['variant_id' => ['required', 'integer', 'exists:product_variants,id']]);
        $variant = ProductVariant::with('product')->findOrFail($request->integer('variant_id'));
        $request->merge(['rows' => [[
            'variant_id' => $variant->id, 'quantity' => $request->input('quantity'),
            'expected_version' => $request->input('expected_version'),
        ]]]);
        try {
            return $this->batch($request, $variant->product, $entries);
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->errors() as $key => $errors) $messages[str_starts_with($key, 'rows.') ? 'quantity' : $key] = $errors;
            throw ValidationException::withMessages($messages);
        }
    }

    public function batch(Request $request, Product $product, StockEntryService $entries)
    {
        $counting = $request->input('action') === 'count';
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'],
            'action' => ['required', \Illuminate\Validation\Rule::in(['count', 'receive', 'damage'])],
            'reason' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'exclude_paid_pickups' => $counting ? ['required', 'accepted'] : ['nullable', 'boolean'],
            'rows' => ['required', 'array', 'min:1', 'max:100'],
            'rows.*.variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'rows.*.quantity' => ['required', 'integer', $counting ? 'min:0' : 'min:1', 'max:100000'],
            'rows.*.expected_version' => [$counting ? 'required' : 'nullable', 'integer', 'min:0'],
        ], [
            'reason.required' => 'Say why, for the stock history.',
            'exclude_paid_pickups.accepted' => 'Confirm that paid orders awaiting pickup are excluded from this unsold-stock count.',
            'exclude_paid_pickups.required' => 'Confirm that paid orders awaiting pickup are excluded from this unsold-stock count.',
        ]);
        $shop = Location::query()->online()->firstOrFail();
        $repeated = $entries->save($product, $shop, Actor::user($request->user()), $data);
        return back()->with('success', $repeated ? 'This stock entry was already saved. No units were added again.' : match ($data['action']) {
            'count' => 'Unsold-stock counts saved.', 'receive' => 'Delivery received.', 'damage' => 'Stock written off.',
        });
    }

    /** Offer a new size (every part of a set), or withdraw / bring back one. */
    public function sizes(Request $request, Product $product)
    {
        $data = $request->validate([
            'size' => ['required', 'string', 'max:30'],
            'active' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $size = trim($data['size']);
        $active = (bool) ($data['active'] ?? true);
        $existing = $product->variants()->where('size', $size)->get();

        if ($active && $existing->where('active', true)->isNotEmpty() && $existing->where('active', false)->isEmpty()) {
            throw ValidationException::withMessages(['size' => "{$product->name} already offers " . $existing->first()->label() . '.']);
        }
        if (! $active && $existing->isEmpty()) {
            throw ValidationException::withMessages(['size' => "{$product->name} doesn't have that size."]);
        }

        if ($existing->isNotEmpty()) {
            $product->variants()->where('size', $size)->update(['active' => $active]);
        } else {
            foreach (ProductVariant::partsFor($product->garment_type) as $p => $part) {
                $variant = ProductVariant::create(['product_id' => $product->id, 'part' => $part, 'size' => $size, 'active' => true,
                    'sku' => ProductVariant::skuFor($product, $part, $size), 'sort' => (int) $product->variants()->where('part', $part)->max('sort') + 1]);
                $variant->update(['barcode' => ProductVariant::barcodeFor($variant->id)]);
            }
        }
        Audit::record('stock.sizes', $product, [], ['size' => $size, 'active' => $active], $data['reason']);

        return back()->with('success', $active ? "{$product->name} now offers {$size}." : "{$size} withdrawn from {$product->name}.");
    }

    /** Stocked (counted per size) or made to order (always available). */
    public function track(Request $request, Product $product)
    {
        $data = $request->validate(['track_stock' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:255']],
            ['reason.required' => 'Say why this changes.']);
        $before = (bool) $product->track_stock;
        $product->forceFill(['track_stock' => (bool) $data['track_stock']])->save();
        Audit::record('stock.tracking', $product, ['track_stock' => $before], ['track_stock' => (bool) $data['track_stock']], $data['reason']);

        return back()->with('success', $data['track_stock'] ? "{$product->name} is stocked: count its sizes." : "{$product->name} is made to order: always available.");
    }

    public function cutover(Request $request, StockCutover $cutover)
    {
        try {
            $result = $cutover->confirm(Actor::user($request->user()));
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['cutover' => $e->getMessage()]);
        }

        return back()->with('success', "Stock per size is on. {$result['replayed']} sale(s) after the count were applied.");
    }

    public function rollback(Request $request, StockCutover $cutover)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Say why stock per size is being switched off.']);
        $cutover->rollback(Actor::user($request->user()), $data['reason']);

        return back()->with('success', 'Stock per size is off. The old batch numbers sell again.');
    }
}
