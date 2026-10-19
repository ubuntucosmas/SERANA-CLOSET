<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use App\Models\PaymentAttempt;
use App\Services\MpesaService;
use App\Services\OrderSettlementService;
use App\Support\OrderStatus;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    protected $mpesaService;
    protected $settlement;

    public function __construct(MpesaService $mpesaService, OrderSettlementService $settlement)
    {
        $this->mpesaService = $mpesaService;
        $this->settlement = $settlement;
    }

    /**
     * Initiate STK Push
     * Logic: 1. Validate request & authorize the caller against the order
     *        2. Re-secure the order's stock (retries can outlive the hold)
     *        3. Dispatch the push and record it as a PaymentAttempt
     */
    public function initiate(Request $request)
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|integer',
                'phone' => 'required|string|max:30',
            ]);

            $phone = Phone::normalize($validated['phone']);
            if (! Phone::isValidKenyanMobile($phone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Enter a valid Kenyan mobile number, e.g. 07XX XXX XXX.',
                ], 422);
            }

            $order = CustomOrder::find($validated['order_id']);
            if (!$order) return response()->json(['success' => false, 'message' => 'Order not found.'], 404);

            // AUTHORIZE: only the session that created this order (or its owner /
            // an admin) may trigger an STK push for it. Without this, anyone could
            // enumerate sequential order IDs and spam M-Pesa prompts to arbitrary
            // phone numbers — harassment, and a direct cost to the store.
            if (!$this->canActOnOrder($request, $order)) {
                return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
            }

            // M-Pesa switched off in Studio → Payments (or not configured): no new
            // prompts. Callbacks and reconciliation still settle any in progress.
            if (! \App\Support\PaymentSettings::mpesaAvailable()) {
                return response()->json([
                    'success' => false,
                    'code' => 'mpesa_paused',
                    'whatsapp' => \App\Support\PaymentSettings::whatsappAvailable(),
                    'message' => 'M-Pesa payments are paused right now. You can send this same order on WhatsApp instead.',
                ], 409);
            }
            if ($order->payment_method === \App\Support\PaymentSettings::WHATSAPP) {
                return response()->json(['success' => false, 'message' => 'This order is being paid via WhatsApp. The studio will confirm your payment.'], 409);
            }

            // Reject re-payment of an already-settled order.
            if ($order->is_paid || $order->status === OrderStatus::PAID) {
                return response()->json(['success' => false, 'message' => 'This order is already paid.'], 409);
            }

            // An expired order's stock hold is long gone — never collect money
            // for it. The customer rebuilds their bag and checks out afresh.
            if ($order->status === OrderStatus::EXPIRED) {
                return response()->json([
                    'success' => false,
                    'message' => 'This order expired before payment. Please check out again.',
                ], 409);
            }

            // With paid Drops paused, an unpaid reservation must not be charged.
            if ($order->type === 'drop' && ! config('services.drops.enabled')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Drop reservations are paused. Contact support with your order reference.',
                ], 409);
            }

            // Money has already been collected against this order at the wrong
            // amount. A fresh prompt would charge the customer again before the
            // studio has reviewed (and possibly refunded) the first payment.
            if ($order->status === OrderStatus::AMOUNT_MISMATCH) {
                return response()->json([
                    'success' => false,
                    'message' => 'This order needs studio review before another payment. Contact support with your order reference.',
                ], 409);
            }

            // SERVER-AUTHORITATIVE AMOUNT: never trust a client-supplied amount.
            // The only source of truth is the price computed at checkout.
            if ((float) $order->price_quoted !== floor((float) $order->price_quoted)) {
                return response()->json(['success' => false, 'message' => 'This order amount needs studio review before M-Pesa payment. Contact support with your order reference.'], 422);
            }
            $amount = (int) round((float) $order->price_quoted);
            if ($amount <= 0) {
                return response()->json(['success' => false, 'message' => 'Order has no payable amount.'], 422);
            }

            // Retries can arrive after the original hold lapsed: re-secure the
            // stock (extend live holds / re-acquire released ones) before taking
            // a shilling for it.
            if (! $order->secureStockForPayment()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An item in this order sold out while it sat unpaid. Please check out again.',
                ], 409);
            }

            $result = $this->mpesaService->initiateStkPush(
                $phone,
                $amount,
                "ORDER-{$order->id}",
                "Payment for Serana Brief #{$order->id}"
            );

            if ($result['success']) {
                $checkoutID = $result['data']['CheckoutRequestID'];

                // Every push gets its own attempt row so a customer who retries
                // and then pays the *earlier* prompt still settles the order.
                $order->paymentAttempts()->create([
                    'checkout_request_id' => $checkoutID,
                    'phone'               => $phone,
                    'amount'              => $amount,
                    'status'              => PaymentAttempt::PENDING,
                ]);
                // Kept in sync as "latest attempt" for older tooling/queries.
                $order->update(['mpesa_checkout_id' => $checkoutID]);
                // A retry after "Payment Failed" is live again: put the order back
                // into the pending state the expiry and reconciliation jobs watch.
                // Conditional, so an earlier prompt's callback that settled the
                // order in the meantime is never overwritten.
                CustomOrder::whereKey($order->id)
                    ->where('is_paid', false)
                    ->update(\App\Support\LegacyOrderMap::forStatus(OrderStatus::PENDING_PAYMENT));

                return response()->json([
                    'success' => true,
                    'message' => 'STK Push initiated. Check your phone.',
                    'CheckoutRequestID' => $checkoutID
                ]);
            }

            // The service's reason ("Failed to authenticate", "STK Push initiation
            // failed") is for the studio, not the customer. Nothing was charged.
            Log::warning("M-Pesa STK not started for Order #{$order->id}: " . ($result['message'] ?? 'unknown'), ['details' => $result['details'] ?? null]);
            return response()->json([
                'success' => false,
                'message' => "M-Pesa couldn't send the payment prompt just now. You have not been charged. Check the number and try again in a minute.",
            ], 400);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("M-Pesa Initiation Exception: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An internal server error occurred during payment initiation.',
            ], 500);
        }
    }

    /**
     * M-Pesa Callback
     * Safaricom sends the final transaction result here asynchronously.
     * Logic: 1. Log raw payload for audit
     *        2. Identify the attempt (and its order) by CheckoutRequestID
     *        3. Check for idempotency (prevent double processing)
     *        4. Record the attempt outcome, settle the order, dispatch event
     */
    public function callback(Request $request)
    {
        $payload = $request->all();

        $request->validate([
            'Body.stkCallback.ResultCode' => ['required', 'integer'],
            'Body.stkCallback.CheckoutRequestID' => ['required', 'string', 'max:255'],
            'Body.stkCallback.CallbackMetadata.Item' => ['sometimes', 'array'],
        ]);
        $stkCallback = $payload['Body']['stkCallback'];
        $resultCode = $stkCallback['ResultCode'];
        $checkoutRequestID = $stkCallback['CheckoutRequestID'];
        Log::info('M-Pesa callback received.', ['checkout_id' => $checkoutRequestID, 'result_code' => $resultCode]);

        // Match the specific attempt; fall back to the order's latest checkout id
        // for orders created before per-attempt tracking existed.
        $attempt = PaymentAttempt::where('checkout_request_id', $checkoutRequestID)->first();
        $order = $attempt?->order
            ?? CustomOrder::withTrashed()->where('mpesa_checkout_id', $checkoutRequestID)->first();

        if (!$order) {
            Log::error("M-Pesa Callback: Order not found for CheckoutID: $checkoutRequestID");
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Order Not Found']);
        }

        // Extract the success metadata up front — the already-paid branch needs
        // it too, to book-keep a duplicate payment.
        $receiptNumber = null;
        $paidAmount = null;
        if ($resultCode == 0) {
            foreach ($stkCallback['CallbackMetadata']['Item'] ?? [] as $item) {
                if (($item['Name'] ?? null) === 'MpesaReceiptNumber') {
                    $receiptNumber = $item['Value'] ?? null;
                }
                if (($item['Name'] ?? null) === 'Amount') {
                    $paidAmount = $item['Value'] ?? null;
                }
            }
        }

        // 2. Idempotency / duplicate-payment check
        if ($order->is_paid || $order->status === OrderStatus::PAID) {
            if ($attempt && $attempt->status === PaymentAttempt::PENDING) {
                if ($resultCode == 0) {
                    // The customer paid a second prompt for an already-paid order:
                    // record it — this is a refund owed, not a success to celebrate.
                    $attempt->markPaid($receiptNumber);
                    Log::warning("M-Pesa Callback: duplicate successful payment for already-paid Order #{$order->id}. — refund may be owed.");
                } else {
                    $attempt->markFailed((string) $resultCode, $stkCallback['ResultDesc'] ?? null);
                }
            }
            Log::info("M-Pesa Callback: Order #{$order->id} already processed. Skipping.");
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Already Processed']);
        }

        // 3. Process Result
        if ($resultCode == 0) {
            // AMOUNT VERIFICATION: the amount actually paid must match the amount
            // we pushed. A short/forged callback must never flip is_paid.
            $expected = $attempt?->amount ?? (int) round((float) $order->price_quoted);
            if (! is_numeric($paidAmount) || (int) round((float) $paidAmount * 100) !== $expected * 100) {
                $attempt?->markFailed((string) $resultCode, 'Amount mismatch');
                $this->settlement->flagAmountMismatch($order, $expected, $paidAmount);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            // Marks paid, commits held stock to sold, and dispatches the event.
            $attempt?->markPaid($receiptNumber);
            $this->settlement->markPaid($order, $receiptNumber, $payload);
            Log::info("M-Pesa Payment Successful for Order #{$order->id}.");

        } else {
            // FAILED / CANCELLED (Codes like 1032 are common for user cancellation)
            $attempt?->markFailed((string) $resultCode, $stkCallback['ResultDesc'] ?? null);

            // Only fail the order when no other push could still pay it — a
            // cancelled retry must not clobber an earlier prompt the customer
            // may have completed.
            if (! $order->hasLivePaymentAttempts()) {
                $this->settlement->markUnpaid($order, OrderStatus::PAYMENT_FAILED);
            }
            Log::warning("M-Pesa Payment Failed for Order #{$order->id}. Code: $resultCode");
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * Query STK Push Status
     * Frontend polls this to see if the database was updated by the callback.
     */
    public function status(Request $request, $orderId)
    {
        $order = CustomOrder::find($orderId);

        if (!$order) return response()->json(['success' => false, 'message' => 'Order not found'], 404);

        // Authorize: only the session that created this order, its owner, or an
        // admin may read its status. Prevents enumerating order IDs to leak the
        // M-Pesa receipt number / payment state of strangers.
        if (!$this->canActOnOrder($request, $order)) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $isPaid = (bool) $order->is_paid;

        return response()->json([
            'success' => true,
            'is_paid' => $isPaid,
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'reference' => $order->cipher,
            // Server-confirmed total, so the success screen never has to trust
            // the client-side cart for the figure it displays.
            'amount' => (float) $order->price_quoted,
            'transaction_id' => $order->mpesa_receipt_number,
            // Paid only: the payment record the customer posts to the studio.
            'whatsapp_url' => $isPaid ? \App\Support\WhatsAppOrderMessage::paidLink($order) : null,
            // Signed, time-limited receipt link — only valid once payment is confirmed.
            'receipt_url' => $isPaid
                ? \Illuminate\Support\Facades\URL::temporarySignedRoute(
                    'orders.receipt', now()->addDays(7), ['order' => $order->id]
                )
                : null,
        ]);
    }

    /**
     * May the current request act on this order? True only for the guest
     * session that created it, the authenticated owner, or an admin. Shared by
     * STK initiation and status polling so both authorize identically.
     */
    private function canActOnOrder(Request $request, CustomOrder $order): bool
    {
        $sessionOrders = (array) $request->session()->get('serana.order_ids', []);
        if (in_array((int) $order->id, array_map('intval', $sessionOrders), true)) {
            return true;
        }

        return auth()->check() && (
            (int) $order->user_id === (int) auth()->id() || auth()->user()->canUseStudio()
        );
    }
}
