<?php

namespace App\Http\Controllers;

use App\Support\SubmissionReceipt;

use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Product;
use App\Models\CustomOrder;
use App\Support\OrderStatus;
use App\Support\PaymentSettings;
use App\Support\WhatsAppOrderMessage;
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
        return Inertia::render('Checkout', [
            'pickup_locations' => \App\Models\Location::pickupAvailable()->orderBy('sort')->get(['id', 'name', 'address']),
        ]);
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

        // M-Pesa (pay now) or WhatsApp (pay after the studio confirms).
        $method = $validated['payment_method'] ?? PaymentSettings::methods()[0];
        $isWhatsApp = $method === PaymentSettings::WHATSAPP;

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

            // Batch-limited products: availability accounts for active holds (the old model; v2 checks per size below).
            if (! \App\Support\StockMode::v2() && (! $product->in_stock || ($product->batch_limit !== null && $quantities[$product->id] > $product->availableStock()))) {
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

        // Stock per size (design §13.6): each line is read as its size(s), and sizes are what's held.
        $v2 = \App\Support\StockMode::v2();
        $demands = [];
        if ($v2) {
            $stock = app(\App\Services\StockService::class);
            $plan = $stock->demandsFor($validated['items'], $products);
            if ($plan['errors']) {
                return response()->json(['success' => false, 'message' => $plan['errors'][0]['message'], 'line_errors' => $plan['errors']], 422);
            }
            foreach ($plan['variant_ids'] as $index => $ids) {
                $snapshotItems[$index]['variant_ids'] = $ids;
            }
            $demands = $plan['demands'];
            $stockErrors = array_filter([$stock->shortageFor($demands)]);
            foreach ($products as $product) {
                if (! $product->in_stock) {
                    $stockErrors[] = "{$product->name} is no longer available.";
                }
            }
            if ($stockErrors) {
                return response()->json(['success' => false, 'message' => $stockErrors[0] . ' Please review your bag and try again.'], 422);
            }
        }

        if (! empty($stockErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock for: ' . implode(', ', $stockErrors),
            ], 422);
        }

        $pickup = ($validated['fulfilment_method'] ?? 'delivery') === 'pickup'
            ? \App\Models\Location::pickupAvailable()->findOrFail($validated['pickup_location_id']) : null;
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

        // Atomic order creation with stock holds. Up to 3 attempts: a deadlock
        // rolls the whole transaction back, and this closure only touches the
        // database (mail and the session come after it), so re-running is safe.
        try {
            $order = DB::transaction(function () use ($validated, $userId, $phone, $calculatedTotal, $snapshotItems, $quantities, $method, $isWhatsApp, $v2, $demands, $pickup) {
                // A prompt is paid in minutes; a WhatsApp order needs time to arrange payment.
                $expiresAt = $isWhatsApp
                    ? now()->addHours(PaymentSettings::holdHours())
                    : now()->addMinutes(config('services.mpesa.reservation_minutes', 15));
                $toReserve = [];
                // Stock per size: lock the size levels (lock order step 1), choose a location per size.
                $allocations = $v2 ? app(\App\Services\StockService::class)->allocate($demands, $pickup) : [];

                // Hold (not sell) batch stock for each item under a row lock so
                // concurrent checkouts serialize on the product and can't oversell.
                // Lock order: products (by id), then stock_reservations — the
                // global order every stock path follows (CustomOrder::lockReservedProducts).
                foreach ($v2 ? [] : $quantities->sortKeys() as $productId => $quantity) {
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
                    'fulfilment_method' => $pickup ? 'pickup' : 'delivery',
                    'pickup_location_id' => $pickup?->id,
                    'fulfilment_snapshot' => $pickup ? $pickup->only(['name', 'address']) : null,
                    'address'      => $pickup ? null : $validated['address'],
                    'city'         => $pickup ? null : $validated['city'],
                    'phone'        => $phone,
                    'items_json'   => $snapshotItems,
                    'is_paid'      => false,
                    'payment_method' => $method,
                    'whatsapp_number' => $phone,
                    'bust_cm'  => $validated['bust_cm'] ?? null,
                    'waist_cm' => $validated['waist_cm'] ?? null,
                    'hips_cm'  => $validated['hips_cm'] ?? null,
                ]);

                // Persist the holds now that we have an order id.
                app(\App\Services\StockService::class)->hold($order, $allocations, $expiresAt);
                foreach ($toReserve as $reservation) {
                    $order->stockReservations()->create([
                        'product_id' => $reservation['product_id'],
                        'quantity'   => $reservation['quantity'],
                        'status'     => 'held',
                        'expires_at' => $expiresAt,
                    ]);
                }

                return $order;
            }, 3);
        } catch (\DomainException $e) {
            // Stock race: someone else claimed the last unit(s) mid-checkout. Per size, say which.
            return response()->json([
                'success' => false,
                'message' => $v2 ? $e->getMessage() . ' Please review your bag and try again.' : 'An item in your bag just sold out. Please review your bag and try again.',
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

        if ($isWhatsApp) {
            self::sendOrderPlacedMail($order);
        }

        // Bind this order to the buyer's session so they (and only they) can poll
        // its payment status and receive the signed receipt link — without login.
        $request->session()->push('serana.order_ids', $order->id);

        return response()->json(SubmissionReceipt::remember($request, 'checkout', $validated, [
            'success'  => true,
            'message'  => 'Order created. Proceed to payment',
            'order_id' => $order->id,
            'reference' => $order->cipher,
            'handover' => $order->handoverDetails(),
            'amount' => $order->total(),
            'items' => $snapshotItems,
            'payment_method' => $method,
            'whatsapp_url' => $isWhatsApp ? WhatsAppOrderMessage::link($order) : null,
            'instructions' => $isWhatsApp ? (PaymentSettings::instructions() ?: null) : null,
            'hold_hours' => $isWhatsApp ? PaymentSettings::holdHours() : null,
        ]));
    }

    /**
     * Move an unpaid M-Pesa order to WhatsApp (e.g. M-Pesa was paused, or the
     * customer prefers it). Same order, same code: no duplicate is created.
     */
    public function switchToWhatsApp(\Illuminate\Http\Request $request, CustomOrder $order)
    {
        $owned = in_array((int) $order->id, array_map('intval', (array) $request->session()->get('serana.order_ids', [])), true)
            || (auth()->check() && (int) $order->user_id === (int) auth()->id());
        abort_unless($owned && $order->isShop(), 404);

        if (! PaymentSettings::whatsappAvailable()) {
            return response()->json(['success' => false, 'message' => 'Ordering on WhatsApp is not available right now.'], 409);
        }
        if ($order->isPaidUp()) {
            return response()->json(['success' => false, 'message' => 'This order is already paid.'], 409);
        }
        if (! in_array($order->legacyStatus(), [OrderStatus::PENDING_PAYMENT, OrderStatus::PAYMENT_FAILED], true)) {
            return response()->json(['success' => false, 'message' => 'This order can no longer be changed. Please check out again.'], 409);
        }
        // Never while a prompt may still be answered: that payment would settle the order too.
        if ($order->paymentAttempts()->where('status', \App\Models\PaymentAttempt::PENDING)
            ->where('created_at', '>=', now()->subMinutes(config('services.mpesa.reservation_minutes', 15)))->exists()) {
            return response()->json(['success' => false, 'message' => 'An M-Pesa prompt for this order may still be completing. Wait a moment, then try again.'], 409);
        }
        if (! $order->secureStockForPayment(now()->addHours(PaymentSettings::holdHours()))) {
            return response()->json(['success' => false, 'message' => 'An item in this order sold out meanwhile. Please review your bag and check out again.'], 409);
        }

        $order->update(['payment_method' => PaymentSettings::WHATSAPP, 'channel' => 'whatsapp'] + \App\Support\LegacyOrderMap::forStatus(OrderStatus::PENDING_PAYMENT));
        self::sendOrderPlacedMail($order);

        return response()->json([
            'success' => true,
            'payment_method' => PaymentSettings::WHATSAPP,
            'reference' => $order->cipher,
            'handover' => $order->handoverDetails(),
            'whatsapp_url' => WhatsAppOrderMessage::link($order),
            'instructions' => PaymentSettings::instructions() ?: null,
            'hold_hours' => PaymentSettings::holdHours(),
        ]);
    }

    /** Customer gets their code and how to pay; the owner gets a Bcc as the new-order alert. */
    private static function sendOrderPlacedMail(CustomOrder $order): void
    {
        try {
            $owner = \App\Support\StoreProfile::notificationEmail();
            $mail = \Illuminate\Support\Facades\Mail::to($order->email ?: $owner);
            if ($owner && $order->email) $mail->bcc($owner);
            $mail->queue(new \App\Mail\WhatsAppOrderPlacedMail($order));
        } catch (\Throwable $e) {
            // Never fail the order over email; it's in the Studio either way.
            Log::warning('WhatsApp order email could not be queued.', ['order_id' => $order->id, 'exception' => get_class($e)]);
        }
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
            ->ofKind('shop')
            ->paidUp(false)
            // A WhatsApp order is waiting on the studio, not abandoned.
            ->where('payment_method', '!=', PaymentSettings::WHATSAPP)
            ->withLegacyStatus([OrderStatus::PENDING_PAYMENT, OrderStatus::PAYMENT_FAILED])
            ->whereDoesntHave('paymentAttempts', fn ($q) => $q->where('status', \App\Models\PaymentAttempt::PENDING))
            ->get()
            ->each(function (CustomOrder $order) {
                $order->releaseReservations();
                CustomOrder::whereKey($order->id)->paidUp(false)->update(\App\Support\LegacyOrderMap::forStatus(OrderStatus::EXPIRED));
            });
    }
}
