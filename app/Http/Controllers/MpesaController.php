<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    /**
     * Initiate STK Push
     * Placeholder for Daraja API integration.
     */
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required',
            'phone' => 'required|string',
            'amount' => 'required|numeric'
        ]);

        try {
            // M-Pesa Integration Logic would go here
            // 1. Generate Access Token
            // 2. Prepare STK Push Payload
            // 3. Send Request to Safaricom
            
            Log::info("M-Pesa STK Initiated for Order #{$validated['order_id']} | Phone: {$validated['phone']}");

            return response()->json([
                'success' => true,
                'message' => 'STK Push initiated. Check your phone.',
                'CheckoutRequestID' => 'ws_CO_20042026_MOCK_' . rand(1000, 9999)
            ]);

        } catch (\Exception $e) {
            Log::error("M-Pesa Initiation Failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate M-Pesa payment. Please try again.'
            ], 500);
        }
    }

    /**
     * Query STK Push Status
     * Polled by the frontend to check if payment is complete.
     */
    public function status($orderId)
    {
        $order = CustomOrder::find($orderId);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // SIM-MODE: For testing, we actually mark the order as paid in the database
        // and return the success status.
        $order->update([
            'is_paid' => true,
            'status' => 'Paid'
        ]);

        return response()->json([
            'success' => true,
            'is_paid' => true,
            'status' => 'Paid'
        ]);
    }

    /**
     * M-Pesa Callback
     * Endpoint registered with Safaricom to receive transaction results.
     */
    public function callback(Request $request)
    {
        // 1. Parse JSON response from Safaricom
        // 2. Identify the order via CheckoutRequestID or AccountReference
        // 3. If ResultCode is 0, mark order as paid
        
        Log::info("M-Pesa Callback Received", $request->all());
        
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
