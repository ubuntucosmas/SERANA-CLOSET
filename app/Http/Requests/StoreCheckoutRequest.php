<?php

namespace App\Http\Requests;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checkout is the top of the money path, so every line item is validated
 * individually. Quantities must be positive integers — a negative or
 * fractional quantity would flow straight into the server-computed total and
 * let a crafted cart pay less than the goods are worth.
 */
class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guest checkout is allowed by design
    }

    public function rules(): array
    {
        return [
            'request_id' => ['nullable', 'uuid'],
            // Only methods switched on in Studio → Payments (and M-Pesa only when configured).
            'payment_method' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Support\PaymentSettings::methods())],
            'full_name' => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255'],
            // Phase D2: the customer picks an option offered now; the server prices it.
            'delivery_option_id' => ['required', 'integer', function ($attribute, $value, $fail) {
                if (! $this->deliveryOption()) {
                    $fail('Choose how you would like to receive your order.');
                }
            }],
            // A pickup needs no address; everything else is delivered there.
            'address'   => [Rule::requiredIf(fn () => $this->deliveryOption()?->type !== \App\Models\DeliveryOption::PICKUP), 'nullable', 'string', 'max:500'],
            'city'      => [Rule::requiredIf(fn () => $this->deliveryOption()?->type !== \App\Models\DeliveryOption::PICKUP), 'nullable', 'string', 'max:120'],
            'phone'     => ['required', 'string', 'max:30', function ($attribute, $value, $fail) {
                if (! Phone::isValidKenyanMobile($value)) {
                    $fail('Enter a valid Kenyan mobile number, e.g. 07XX XXX XXX.');
                }
            }],

            'items'            => ['required', 'array', 'min:1', 'max:30'],
            'items.*.id'       => ['required', 'integer',
                                   Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            // Size/variant selections chosen by the customer (not pricing data).
            'items.*.options'  => ['nullable', 'array'],
            // The sizes the bag says it holds (stock per size); checked against the options, never trusted alone.
            'items.*.variant_ids'   => ['nullable', 'array', 'max:2'],
            'items.*.variant_ids.*' => ['integer'],

            // Optional Precision Sizing
            'height_cm' => ['nullable', 'numeric', 'min:50', 'max:250'],
            'bust_cm'   => ['nullable', 'numeric', 'min:40', 'max:200'],
            'waist_cm'  => ['nullable', 'numeric', 'min:30', 'max:200'],
            'hips_cm'   => ['nullable', 'numeric', 'min:40', 'max:200'],
        ];
    }

    /** The option chosen, if it is on offer now (a quote only while WhatsApp payment is on). */
    public function deliveryOption(): ?\App\Models\DeliveryOption
    {
        $id = $this->input('delivery_option_id');
        if (! is_numeric($id)) {
            return null;
        }
        $option = \App\Models\DeliveryOption::available()->with('location')->find((int) $id);

        return $option && ($option->type !== \App\Models\DeliveryOption::QUOTE || \App\Support\PaymentSettings::whatsappAvailable()) ? $option : null;
    }

    public function messages(): array
    {
        return [
            'payment_method.in'        => 'That way of paying is not available right now. Please choose another.',
            'items.*.id.exists'        => 'An item in your bag is no longer available.',
            'items.*.quantity.integer' => 'Item quantities must be whole numbers.',
            'items.*.quantity.min'     => 'Item quantities must be at least 1.',
            'delivery_option_id.required' => 'Choose how you would like to receive your order.',
        ];
    }
}
