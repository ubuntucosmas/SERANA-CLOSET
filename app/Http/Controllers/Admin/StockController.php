<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StockCutover;
use App\Services\StockService;
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
    /** Count (set what's on hand), receive, or write off (damage) one size at the shop. */
    public function move(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'action' => ['required', \Illuminate\Validation\Rule::in(['count', 'receive', 'damage'])],
            'quantity' => ['required', 'integer', $request->input('action') === 'count' ? 'min:0' : 'min:1', 'max:100000'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Say why, for the stock history.',
            'quantity.min' => $request->input('action') === 'count' ? 'A count can’t be negative.' : 'Enter at least 1.',
        ]);
        $variant = ProductVariant::with('product')->findOrFail($data['variant_id']);
        $shop = Location::query()->online()->firstOrFail();
        $actor = Actor::user($request->user());

        try {
            match ($data['action']) {
                'count' => $stock->count($variant, $shop, (int) $data['quantity'], $actor, $data['reason']),
                'receive' => $stock->receive($variant, $shop, (int) $data['quantity'], $actor, $data['reason']),
                'damage' => $stock->damage($variant, $shop, (int) $data['quantity'], $actor, $data['reason']),
            };
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['quantity' => $e->getMessage()]);
        }

        return back()->with('success', match ($data['action']) {
            'count' => 'Count saved',
            'receive' => 'Stock received',
            'damage' => 'Written off',
        } . ': ' . $stock->describe($variant) . '.');
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
