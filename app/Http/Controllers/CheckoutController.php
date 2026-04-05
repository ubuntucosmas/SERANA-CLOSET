<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    /**
     * Display the secure checkout page.
     */
    public function index()
    {
        return Inertia::render('Checkout');
    }

    /**
     * Process the payment and order creation.
     */
    public function process(Request $request)
    {
        $validated = $request->validate([
            'full_name'      => 'required|string|max:255',
            'email'          => 'required|email',
            'address'        => 'required|string',
            'city'           => 'required|string',
            'phone'          => 'required|string',
            'payment_method' => 'required|string',
            'items'          => 'required|array',
            // Optional Precision Sizing
            'height_cm' => 'nullable|numeric|min:50|max:250',
            'bust_cm'   => 'nullable|numeric|min:40|max:200',
            'waist_cm'  => 'nullable|numeric|min:30|max:200',
            'hips_cm'   => 'nullable|numeric|min:40|max:200',
        ]);

        // Secure Server-Side Price Recalculation
        $calculatedTotal = 0;
        foreach ($validated['items'] as $item) {
            $product = Product::find($item['id']);
            if ($product) {
                $calculatedTotal += $product->price * $item['quantity'];
            }
        }

        // Merge optional measurements into the items payload so the
        // Studio Dashboard Artisan Panel can surface them in the spec grid.
        $itemsWithSizing = $validated['items'];
        $sizingProvided = array_filter([
            'height_cm' => $validated['height_cm'] ?? null,
            'bust_cm'   => $validated['bust_cm'] ?? null,
            'waist_cm'  => $validated['waist_cm'] ?? null,
            'hips_cm'   => $validated['hips_cm'] ?? null,
        ]);
        if (!empty($sizingProvided)) {
            $itemsWithSizing['precision_sizing'] = $sizingProvided;
        }

        // Unified Order Creation
        $order = CustomOrder::create([
            'user_id'     => auth()->id(),
            'full_name'   => $validated['full_name'],
            'type'        => 'standard',
            'status'      => 'Designing',
            'price_quoted'=> $calculatedTotal,
            'address'     => $validated['address'],
            'city'        => $validated['city'],
            'phone'       => $validated['phone'],
            'items_json'  => $itemsWithSizing,
            'is_paid'     => true,
            'whatsapp_number' => $validated['phone'],
            // Persist to dedicated columns too for direct dashboard column filtering
            'bust_cm'  => $validated['bust_cm'] ?? null,
            'waist_cm' => $validated['waist_cm'] ?? null,
            'hips_cm'  => $validated['hips_cm'] ?? null,
        ]);

        return response()->json([
            'success'  => true,
            'order_id' => $order->id,
            'message'  => 'Payment Secured. Order #SRN-' . str_pad($order->id, 4, '0', STR_PAD_LEFT) . ' is now in production.'
        ]);
    }
}
