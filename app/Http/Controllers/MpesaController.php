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
    }

    /**
     * M-Pesa Callback
     * Safaricom sends the final transaction result here asynchronously.
     */
    public function callback(Request $request)
    {
        $payload = $request->all();
        Log::info("M-Pesa Callback Data", $payload);

        $stkCallback = $payload['Body']['stkCallback'];
        $resultCode = $stkCallback['ResultCode'];
        $checkoutRequestID = $stkCallback['CheckoutRequestID'];

        $order = CustomOrder::where('mpesa_checkout_id', $checkoutRequestID)->first();

        if ($order) {
            if ($resultCode == 0) {
                // SUCCESS
                $order->update([
                    'is_paid' => true,
                    'status' => 'Paid'
                ]);
                Log::info("M-Pesa Payment Successful for Order #{$order->id}");
            } else {
                // FAILED / CANCELLED
                $order->update(['status' => 'Payment Failed']);
                Log::warning("M-Pesa Payment Failed for Order #{$order->id}. Code: $resultCode");
            }
        } else {
            Log::error("M-Pesa Callback: Order not found for CheckoutID: $checkoutRequestID");
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
            'status' => $order->status
        ]);
    }
}
