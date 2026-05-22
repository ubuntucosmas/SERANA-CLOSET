<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    protected $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
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
                'amount' => 'required|numeric'
            ]);

            $order = CustomOrder::find($validated['order_id']);
            if (!$order) return response()->json(['success' => false, 'message' => 'Order not found.'], 404);

            $result = $this->mpesaService->initiateStkPush(
                $validated['phone'],
                $validated['amount'],
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
                'error' => $e->getMessage()
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
        if ($order->is_paid || $order->status === 'Paid') {
            Log::info("M-Pesa Callback: Order #{$order->id} already processed. Skipping.");
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Already Processed']);
        }

        // 3. Process Result
        if ($resultCode == 0) {
            // SUCCESS
            $receiptNumber = null;
            $items = $stkCallback['CallbackMetadata']['Item'] ?? [];
            foreach ($items as $item) {
                if ($item['Name'] === 'MpesaReceiptNumber') {
                    $receiptNumber = $item['Value'];
                }
            }

            $order->update([
                'is_paid' => true,
                'status' => 'Paid',
                'mpesa_receipt_number' => $receiptNumber
            ]);

            Log::info("M-Pesa Payment Successful for Order #{$order->id}. Receipt: $receiptNumber");
            
            // 4. Dispatch Event for downstream logic (Notifications, Studio Pulse, etc.)
            event(new \App\Events\MpesaWebhookHandled($order, $payload));

        } else {
            // FAILED / CANCELLED (Codes like 1032 are common for user cancellation)
            $order->update(['status' => 'Payment Failed']);
            Log::warning("M-Pesa Payment Failed for Order #{$order->id}. Code: $resultCode");
        }
        
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * Query STK Push Status
     * Frontend polls this to see if the database was updated by the callback.
     */
    public function status($orderId)
    {
        $order = CustomOrder::find($orderId);

        if (!$order) return response()->json(['success' => false, 'message' => 'Order not found'], 404);

        return response()->json([
            'success' => true,
            'is_paid' => $order->status === 'Paid' || $order->is_paid,
            'status' => $order->status,
            'transaction_id' => $order->mpesa_receipt_number
        ]);
    }
}
