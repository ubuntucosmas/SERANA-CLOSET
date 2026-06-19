<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Public order tracking.
 *
 * Lets a customer — guest or member, ready-to-wear or bespoke — follow an order
 * after the checkout session is gone: payment state, receipt, and studio
 * progress photos. Lookup requires the order reference (cipher) AND the phone
 * on the order, so a leaked reference alone reveals nothing and the endpoint
 * can't be used to enumerate orders or harvest PII.
 */
class OrderTrackingController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('TrackOrder', [
            // Lets success screens deep-link straight to a pre-filled reference.
            'prefillReference' => $request->query('ref'),
        ]);
    }

    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'reference' => 'required|string|max:64',
            'phone'     => 'required|string|max:20',
        ]);

        $reference = trim($validated['reference']);

        $order = CustomOrder::query()
            ->whereRaw('UPPER(cipher) = ?', [strtoupper($reference)])
            ->first();

        // One generic answer whether the reference is unknown or the phone
        // doesn't match — never disclose which half was wrong.
        if (! $order || ! $order->matchesContact($validated['phone'])) {
            return response()->json([
                'success' => false,
                'message' => "We couldn't find an order with that reference and phone number. Please check both and try again.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order'   => $order->publicTrackingPayload(),
        ]);
    }
}
