<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use App\Models\Product;
use App\Support\OrderStatus;
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

            // Handle batch-limited products (availability accounts for active holds)
            if ($product->batch_limit) {
                $available = $product->availableStock();
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
            $order = DB::transaction(function () use ($validated, $userId, $calculatedTotal, $itemsWithSizing) {
                $expiresAt = now()->addMinutes(config('services.mpesa.reservation_minutes', 15));
                $toReserve = [];

                // Hold (not sell) batch stock for each item under a row lock so
                // concurrent checkouts serialize on the product and can't oversell.
                foreach ($validated['items'] as $item) {
                    $product = Product::where('id', $item['id'])->lockForUpdate()->first();

                    if ($product && $product->batch_limit) {
                        $quantity = $item['quantity'] ?? 1;
                        // Re-check availability inside the lock (sold + active holds).
                        if ($quantity > $product->availableStock()) {
                            throw new \Exception("Stock level changed during processing for: {$product->name}");
                        }
                        $toReserve[] = ['product_id' => $product->id, 'quantity' => $quantity];
                    }
                }

                // Create order - mark as pending payment (NOT paid yet)
                $order = CustomOrder::create([
                    'user_id'      => $userId,
                    'full_name'    => $validated['full_name'],
                    'email'        => $validated['email'],
                    'type'         => 'standard',
                    'status'       => OrderStatus::PENDING_PAYMENT,
                    'price_quoted' => $calculatedTotal,
                    'address'      => $validated['address'],
                    'city'         => $validated['city'],
                    'phone'        => $validated['phone'],
                    'items_json'   => $itemsWithSizing,
                    'is_paid'      => false, 
                    'whatsapp_number' => $validated['phone'],
                    'bust_cm'  => $validated['bust_cm'] ?? null,
                    'waist_cm' => $validated['waist_cm'] ?? null,
                    'hips_cm'  => $validated['hips_cm'] ?? null,
                ]);

                // Persist the holds now that we have an order id.
                foreach ($toReserve as $reservation) {
                    $order->stockReservations()->create([
                        'product_id' => $reservation['product_id'],
                        'quantity'   => $reservation['quantity'],
                        'status'     => 'held',
                        'expires_at' => $expiresAt,
                    ]);
                }

                return $order;
            });
        } catch (\Exception $e) {
            // Log the error for Artisan inspection
            \Illuminate\Support\Facades\Log::error("🚨 [Checkout_Fault] " . $e->getMessage(), [
                'user_id' => $userId,
                'email'   => $validated['email'],
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json([
                'success'  => false,
                'message'  => 'Transaction security alert. Please refresh and try again.',
            ], 500);
        }

        // Bind this order to the buyer's session so they (and only they) can poll
        // its payment status and receive the signed receipt link — without login.
        $request->session()->push('serana.order_ids', $order->id);

        return response()->json([
            'success'  => true,
            'message'  => 'Order created. Proceed to payment',
            'order_id' => $order->id
        ]);
    }
}
