<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use App\Models\DeliveryOption;
use App\Models\Location;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Phase D2 (design §13.7). The owner keeps the delivery options (no deletes:
 * deactivate instead, orders point at them). Staff who record money set an
 * order's delivery: the fee for a WhatsApp quote, or the option for a
 * bespoke order, with a reason, until it is handed over.
 */
class DeliveryController extends Controller
{
    public static function forStudio(): array
    {
        return DeliveryOption::with('location')->orderBy('sort')->orderBy('id')->get()
            ->map(fn (DeliveryOption $o) => $o->forCheckout() + ['active' => $o->active, 'location_id' => $o->location_id, 'sort' => $o->sort])->all();
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(DeliveryOption::TYPES)],
            'fee' => ['required_if:type,zone', 'nullable', 'integer', 'min:0', 'max:100000'],
            'free_over' => ['nullable', 'integer', 'min:1'],
            'eta_text' => ['nullable', 'string', 'max:120'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'active' => ['sometimes', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    private function values(array $data): array
    {
        $pickup = $data['type'] === DeliveryOption::PICKUP;

        return [
            'name' => trim($data['name']), 'type' => $data['type'],
            // A pickup and a quote have no fee of their own; a pickup names its shop.
            'fee' => $data['type'] === DeliveryOption::ZONE ? (int) $data['fee'] : 0,
            'free_over' => $data['type'] === DeliveryOption::ZONE ? ($data['free_over'] ?? null) : null,
            'eta_text' => $data['eta_text'] ?? null,
            'location_id' => $pickup ? ($data['location_id'] ?? Location::query()->orderBy('sort')->orderBy('id')->value('id')) : null,
            'active' => (bool) ($data['active'] ?? true), 'sort' => (int) ($data['sort'] ?? 50),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $option = DeliveryOption::create($this->values($data));
        Audit::record('delivery.option_added', $option, [], $option->only(['name', 'type', 'fee', 'free_over', 'active']), $data['reason']);

        return back()->with('success', "{$option->name} added.");
    }

    public function update(Request $request, DeliveryOption $option)
    {
        $data = $request->validate($this->rules());
        $before = $option->only(['name', 'type', 'fee', 'free_over', 'active', 'eta_text']);
        $option->update($this->values($data));
        Audit::record('delivery.option_updated', $option, $before, $option->only(array_keys($before)), $data['reason']);

        return back()->with('success', "{$option->name} saved. Orders already placed keep what they were charged.");
    }

    /** An order's delivery, set or changed in the Studio. */
    public function setForOrder(Request $request, CustomOrder $order, OrderSettlementService $settlement)
    {
        $data = $request->validate([
            'delivery_option_id' => ['required', 'integer', Rule::exists('delivery_options', 'id')->where('active', true)],
            'fee' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $option = DeliveryOption::with('location')->findOrFail($data['delivery_option_id']);
        if ($option->type === DeliveryOption::PICKUP && ! Location::pickupAvailable()->whereKey($option->location_id)->exists()) {
            throw ValidationException::withMessages(['delivery_option_id' => 'That shop doesn’t take collections yet. Give it an address in Settings first.']);
        }
        if ($option->type === DeliveryOption::QUOTE && ! isset($data['fee'])) {
            throw ValidationException::withMessages(['fee' => 'Enter the delivery fee you quoted.']);
        }
        $fee = $option->type === DeliveryOption::QUOTE ? (int) $data['fee'] : $option->feeFor(max(0, $order->quoteAmount()));
        try {
            $settlement->setDelivery($order, $option, $fee, Actor::user($request->user()), $data['reason']);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['delivery_option_id' => $e->getMessage()]);
        }
        Audit::record('order.delivery_set', $order, [], ['option' => $option->name, 'fee' => $fee], $data['reason']);

        return back()->with('success', "Delivery set: {$option->name}, KSh " . number_format($fee) . '.');
    }
}
