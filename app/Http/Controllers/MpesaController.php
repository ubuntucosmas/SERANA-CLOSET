<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use App\Services\MpesaService;
use App\Services\OrderSettlementService;
use App\Support\OrderStatus;
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
     * Logic: 1. Validate request
     *        2. Call MpesaService to dispatch the STK push
     *        3. Return the CheckoutRequestID for frontend tracking
     */
    public function initiate(Request $request)
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required',
                'phone' => 'required|string',
            ]);

            $order = CustomOrder::find($validated['order_id']);
            if (!$order) return response()->json(['success' => false, 'message' => 'Order not found.'], 404);

            // AUTHORIZE: only the session that created this order (or its owner /
            // an admin) may trigger an STK push for it. Without this, anyone could
            // enumerate sequential order IDs and spam M-Pesa prompts to arbitrary
            // phone numbers — harassment, and a direct cost to the store.
            if (!$this->canActOnOrder($request, $order)) {
                return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
            }

            // Reject re-payment of an already-settled order.
            if ($order->is_paid || $order->status === OrderStatus::PAID) {
                return response()->json(['success' => false, 'message' => 'This order is already paid.'], 409);
            }

            // SERVER-AUTHORITATIVE AMOUNT: never trust a client-supplied amount.
            // The only source of truth is the price computed at checkout.
            $amount = round((float) $order->price_quoted);
            if ($amount <= 0) {
                return response()->json(['success' => false, 'message' => 'Order has no payable amount.'], 422);
            }

            $result = $this->mpesaService->initiateStkPush(
                $validated['phone'],
                $amount,
                "ORDER-{$order->id}",
                "Payment for Serana Brief #{$order->id}"
            );

            if ($result['success']) {
                $checkoutID = $result['data']['CheckoutRequestID'];
                $order->update(['mpesa_checkout_id' => $checkoutID]);

                return response()->json([
                    'success' => true,
                    'message' => 'STK Push initiated. Check your phone.',
                    'CheckoutRequestID' => $checkoutID
                ]);
            }

            return response()->json($result, 400);

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
     *        2. Identify order by CheckoutRequestID
     *        3. Check for idempotency (prevent double processing)
     *        4. Update order & dispatch event
     */
    public function callback(Request $request)
    {
        $payload = $request->all();
        
        // 1. Audit Trail
        Log::info("M-Pesa Callback Received", [
            'payload' => $payload,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        if (!isset($payload['Body']['stkCallback'])) {
            Log::error("M-Pesa Callback: Malformed payload missing Body.stkCallback");
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected']);
        }

        $stkCallback = $payload['Body']['stkCallback'];
        $resultCode = $stkCallback['ResultCode'];
        $checkoutRequestID = $stkCallback['CheckoutRequestID'];

        $order = CustomOrder::where('mpesa_checkout_id', $checkoutRequestID)->first();

        if (!$order) {
            Log::error("M-Pesa Callback: Order not found for CheckoutID: $checkoutRequestID");
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Order Not Found']);
        }

        // 2. Idempotency Check
        if ($order->is_paid || $order->status === OrderStatus::PAID) {
            Log::info("M-Pesa Callback: Order #{$order->id} already processed. Skipping.");
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Already Processed']);
        }

        // 3. Process Result
        if ($resultCode == 0) {
            // SUCCESS
            $receiptNumber = null;
            $paidAmount = null;
            $items = $stkCallback['CallbackMetadata']['Item'] ?? [];
            foreach ($items as $item) {
                if (($item['Name'] ?? null) === 'MpesaReceiptNumber') {
                    $receiptNumber = $item['Value'] ?? null;
                }
                if (($item['Name'] ?? null) === 'Amount') {
                    $paidAmount = $item['Value'] ?? null;
                }
            }

            // AMOUNT VERIFICATION: the amount actually paid must match the amount
            // we quoted. A short/forged callback must never flip is_paid.
            $expected = round((float) $order->price_quoted);
            if ($paidAmount === null || (int) round((float) $paidAmount) !== (int) $expected) {
                $this->settlement->markUnpaid($order, OrderStatus::AMOUNT_MISMATCH);
                Log::error("M-Pesa Callback: amount mismatch for Order #{$order->id}.", [
                    'expected' => $expected,
                    'paid' => $paidAmount,
                ]);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            // Marks paid, commits held stock to sold, and dispatches the event.
            $this->settlement->markPaid($order, $receiptNumber, $payload);
            Log::info("M-Pesa Payment Successful for Order #{$order->id}. Receipt: $receiptNumber");

        } else {
            // FAILED / CANCELLED (Codes like 1032 are common for user cancellation)
            $this->settlement->markUnpaid($order, OrderStatus::PAYMENT_FAILED);
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

        $isPaid = $order->status === OrderStatus::PAID || $order->is_paid;

        return response()->json([
            'success' => true,
            'is_paid' => $isPaid,
            'status' => $order->status,
            'reference' => $order->cipher,
            'transaction_id' => $order->mpesa_receipt_number,
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
            (int) $order->user_id === (int) auth()->id() || auth()->user()->role === 'admin'
        );
    }
}
