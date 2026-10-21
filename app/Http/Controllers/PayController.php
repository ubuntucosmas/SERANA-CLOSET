<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use App\Support\OrderPolicy;
use App\Support\PaymentSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The customer pay link (design B, D15): what the order costs, what is paid,
 * and a deposit or balance payment by M-Pesa. The signed, expiring URL is the
 * customer's key: opening it binds this order to the browser session, the
 * same ownership checkout gives (MpesaController::canActOnOrder).
 */
class PayController extends Controller
{
    public function show(Request $request, CustomOrder $order)
    {
        // Checked here, not by middleware, so an expired link gets a page that says what to do.
        if (! $request->hasValidSignature()) {
            return Inertia::render('Pay', ['valid' => false])->toResponse($request)->setStatusCode(403);
        }

        $ids = array_map('intval', (array) $request->session()->get('serana.order_ids', []));
        if (! in_array($order->id, $ids, true)) {
            $request->session()->push('serana.order_ids', $order->id);
        }

        $balance = max(0, (int) $order->balance_due);
        $depositLeft = max(0, $order->depositRequired() - (int) $order->amount_paid);

        return Inertia::render('Pay', [
            'valid' => true,
            // Customer-safe: no email, phone, address or studio notes.
            'order' => [
                'id' => $order->id,
                'reference' => $order->cipher,
                'handover' => $order->handoverDetails(),
                'first_name' => strtok(trim((string) $order->full_name), ' ') ?: null,
                'garment' => $order->garment,
                'lines' => $order->lines()->orderBy('id')->get()
                    ->map(fn ($line) => ['description' => $line->description, 'amount' => $line->amount])->all(),
                'total' => (int) $order->chargeable_total,
                'amount_paid' => (int) $order->amount_paid,
                'balance_due' => $balance,
                // More paid than owed (a lowered quote): said plainly instead of "balance 0".
                'refund_due' => max(0, -(int) $order->balance_due),
                'refund_days' => OrderPolicy::refundWorkingDays(),
                'deposit_due' => $depositLeft > 0 && $depositLeft < $balance ? $depositLeft : null,
                'deposit_policy' => $order->isBespoke() ? OrderPolicy::depositPolicy() : null,
                'payment_status' => $order->payment_status,
                'can_pay' => $balance > 0 && $order->order_state === 'open' && PaymentSettings::mpesaAvailable(),
                'track_url' => route('track', ['ref' => $order->cipher]),
            ],
        ]);
    }
}
