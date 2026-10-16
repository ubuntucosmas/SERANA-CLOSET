<?php

namespace App\Http\Controllers;

use App\Support\SubmissionReceipt;

use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Product;
use App\Models\CustomOrder;
use App\Support\OrderStatus;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     *
     * Everything money-relevant is server-authoritative: the client sends only
     * product ids, quantities and size selections; names, prices and images are
     * snapshotted from the catalog so a tampered cart can neither change what
     * is charged nor what the receipt later shows.
     */
    public function process(StoreCheckoutRequest $request)
    {
        $validated = $request->validated();

        if ($saved = SubmissionReceipt::recall($request, 'checkout', $validated)) return response()->json($saved);

        // A fresh checkout from this browser replaces any earlier unpaid order it
        // abandoned, so the customer is never blocked by their own stock hold and
        // stale duplicates don't pile up in the Studio.
        $this->supersedeAbandonedOrders($request);

        $products = Product::whereIn('id', collect($validated['items'])->pluck('id'))
            ->get()
            ->keyBy('id');

        $quantities = collect($validated['items'])->groupBy('id')->map(fn ($lines) => $lines->sum('quantity'));

        // Pre-check stock and build the server-side item snapshot.
        $stockErrors = [];
        $calculatedTotal = 0;
        $snapshotItems = [];

        foreach ($validated['items'] as $item) {
            $product = $products->get($item['id']);
            if (! $product) {
                // Product vanished between validation and now (deleted mid-request).
                return response()->json([
                    'success' => false,
                    'message' => 'An item in your bag is no longer available.',
                ], 422);
            }

            $quantity = (int) $item['quantity'];

            // Batch-limited products: availability accounts for active holds.
            if (! $product->in_stock || ($product->batch_limit !== null && $quantities[$product->id] > $product->availableStock())) {
                $stockErrors[] = "{$product->name} — only {$product->availableStock()} available (requested: {$quantity})";
            }

            $calculatedTotal += $product->price * $quantity;

            $snapshotItems[] = [
                'id'         => $product->id,
                'name'       => $product->name,
                'slug'       => $product->slug,
                'price'      => (float) $product->price,
                'quantity'   => $quantity,
                'image_path' => $product->image_path,
                'is_bespoke' => (bool) $product->is_customizable,
                'options'    => $item['options'] ?? null,
            ];
        }

        if (! empty($stockErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock for: ' . implode(', ', $stockErrors),
            ], 422);
        }

        $userId = auth()->id();
        $phone = Phone::normalize($validated['phone']);

        // Merge optional measurements into the items payload so the
        // Studio Dashboard Artisan Panel can surface them in the spec grid.
        $sizingProvided = array_filter([
            'height_cm' => $validated['height_cm'] ?? null,
            'bust_cm'   => $validated['bust_cm'] ?? null,
            'waist_cm'  => $validated['waist_cm'] ?? null,
            'hips_cm'   => $validated['hips_cm'] ?? null,
        ]);
        if (! empty($sizingProvided)) {
            $snapshotItems['precision_sizing'] = $sizingProvided;
        }

        // Atomic order creation with stock holds
        try {
            $order = DB::transaction(function () use ($validated, $userId, $phone, $calculatedTotal, $snapshotItems, $quantities) {
                $expiresAt = now()->addMinutes(config('services.mpesa.reservation_minutes', 15));
                $toReserve = [];

                // Hold (not sell) batch stock for each item under a row lock so
                // concurrent checkouts serialize on the product and can't oversell.
                foreach ($quantities->sortKeys() as $productId => $quantity) {
                    $product = Product::where('id', $productId)->lockForUpdate()->first();
                    if (! $product || ! $product->in_stock) {
                        throw new \DomainException('This item is no longer available.');
                    }

                    if ($product->batch_limit !== null) {
                        // Re-check availability inside the lock (sold + active holds).
                        if ($quantity > $product->availableStock()) {
                            throw new \DomainException("Stock level changed during processing for: {$product->name}");
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
                    'phone'        => $phone,
                    'items_json'   => $snapshotItems,
                    'is_paid'      => false,
                    'whatsapp_number' => $phone,
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
        } catch (\DomainException $e) {
            // Stock race: someone else claimed the last unit(s) mid-checkout.
            return response()->json([
                'success' => false,
                'message' => 'An item in your bag just sold out. Please review your bag and try again.',
            ], 422);
        } catch (\Throwable $e) {
            Log::error("🚨 [Checkout_Fault] " . $e->getMessage(), [
                'user_id' => $userId,
                'email'   => $validated['email'],
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while creating your order. Please try again.',
            ], 500);
        }

        // Bind this order to the buyer's session so they (and only they) can poll
        // its payment status and receive the signed receipt link — without login.
        $request->session()->push('serana.order_ids', $order->id);

        return response()->json(SubmissionReceipt::remember($request, 'checkout', $validated, [
            'success'  => true,
            'message'  => 'Order created. Proceed to payment',
            'order_id' => $order->id,
            'reference' => $order->cipher,
            'amount' => (float) $order->price_quoted,
            'items' => $snapshotItems,
        ]));
    }

    /**
     * Expire this session's earlier unpaid shop orders that have no M-Pesa
     * prompt in flight, releasing their stock holds. Orders with a pending
     * prompt are left alone: the customer may be paying it right now.
     */
    private function supersedeAbandonedOrders(\Illuminate\Http\Request $request): void
    {
        $ids = array_map('intval', (array) $request->session()->get('serana.order_ids', []));
        if (! $ids) {
            return;
        }

        CustomOrder::whereIn('id', $ids)
            ->where('type', 'standard')
            ->where('is_paid', false)
            ->whereIn('status', [OrderStatus::PENDING_PAYMENT, OrderStatus::PAYMENT_FAILED])
            ->whereDoesntHave('paymentAttempts', fn ($q) => $q->where('status', \App\Models\PaymentAttempt::PENDING))
            ->get()
            ->each(function (CustomOrder $order) {
                $order->releaseReservations();
                CustomOrder::whereKey($order->id)->where('is_paid', false)->update(['status' => OrderStatus::EXPIRED]);
            });
    }
}
