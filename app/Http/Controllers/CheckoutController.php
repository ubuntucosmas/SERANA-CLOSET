<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'items'          => 'required|array|min:1',
            // Optional Precision Sizing
            'height_cm' => 'nullable|numeric|min:50|max:250',
            'bust_cm'   => 'nullable|numeric|min:40|max:200',
            'waist_cm'  => 'nullable|numeric|min:30|max:200',
            'hips_cm'   => 'nullable|numeric|min:40|max:200',
        ]);

        // Validate all items exist and check stock BEFORE processing
        $stockErrors = [];
        $calculatedTotal = 0;
        
        foreach ($validated['items'] as $item) {
            $product = Product::find($item['id']);
            
            if (!$product) {
                return response()->json([
                    'success'  => false,
                    'message'  => "Product not found: {$item['id']}",
                ], 422);
            }

            // Handle batch-limited products
            if ($product->batch_limit) {
                $available = $product->batch_limit - $product->batch_sold;
                $requested = $item['quantity'] ?? 1;
                
                if ($requested > $available) {
                    $stockErrors[] = "{$product->name} - only {$available} available (requested: {$requested})";
                }
            }

            $calculatedTotal += $product->price * ($item['quantity'] ?? 1);
        }

        if (!empty($stockErrors)) {
            return response()->json([
                'success'  => false,
                'message'  => 'Insufficient stock for: ' . implode(', ', $stockErrors),
            ], 422);
        }

        // Handle guest vs authenticated user
        $userId = null;
        if (auth()->check()) {
            $userId = auth()->id();
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

        // Atomic order creation with stock decrement
        try {
            DB::transaction(function () use ($validated, $userId, $calculatedTotal, $itemsWithSizing) {
                // Decrement batch stock for each item
                foreach ($validated['items'] as $item) {
                    $product = Product::find($item['id']);
                    if ($product && $product->batch_limit) {
                        $product->increment('batch_sold', $item['quantity'] ?? 1);
                    }
                }

                // Create order - mark as pending payment (NOT paid yet)
                CustomOrder::create([
                    'user_id'      => $userId,
                    'full_name'    => $validated['full_name'],
                    'email'        => $validated['email'],
                    'type'         => 'standard',
                    'status'       => 'Pending Payment',
                    'price_quoted' => $calculatedTotal,
                    'address'      => $validated['address'],
                    'city'         => $validated['city'],
                    'phone'        => $validated['phone'],
                    'items_json'   => $itemsWithSizing,
                    'is_paid'      => false, // Payment pending
                    'whatsapp_number' => $validated['phone'],
                    'bust_cm'  => $validated['bust_cm'] ?? null,
                    'waist_cm' => $validated['waist_cm'] ?? null,
                    'hips_cm'  => $validated['hips_cm'] ?? null,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success'  => false,
                'message'  => 'Order processing failed. Please try again.',
            ], 500);
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Order created. Proceed to payment',
        ]);
    }
}
