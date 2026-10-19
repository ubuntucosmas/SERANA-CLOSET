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
            'address'   => ['required', 'string', 'max:500'],
            'city'      => ['required', 'string', 'max:120'],
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

            // Optional Precision Sizing
            'height_cm' => ['nullable', 'numeric', 'min:50', 'max:250'],
            'bust_cm'   => ['nullable', 'numeric', 'min:40', 'max:200'],
            'waist_cm'  => ['nullable', 'numeric', 'min:30', 'max:200'],
            'hips_cm'   => ['nullable', 'numeric', 'min:40', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.in'        => 'That way of paying is not available right now. Please choose another.',
            'items.*.id.exists'        => 'An item in your bag is no longer available.',
            'items.*.quantity.integer' => 'Item quantities must be whole numbers.',
            'items.*.quantity.min'     => 'Item quantities must be at least 1.',
        ];
    }
}
