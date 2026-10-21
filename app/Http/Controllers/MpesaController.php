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
                // A choice, never an amount (I2): the server reads what is due from the ledger.
                'pay' => 'nullable|in:deposit,balance',
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
            // Phase D2: a delivery quote has no price yet, so nothing can be paid.
            if ($order->needsDeliveryQuote()) {
                return response()->json(['success' => false, 'message' => 'The Studio is pricing your delivery. You will get the total on WhatsApp before you pay.'], 422);
            }
            if ($order->payment_method === \App\Support\PaymentSettings::WHATSAPP) {
                return response()->json(['success' => false, 'message' => 'This order is being paid via WhatsApp. The studio will confirm your payment.'], 409);
            }

            // Reject re-payment of an already-settled order.
            if ($order->isPaidUp() || $order->legacyStatus() === OrderStatus::PAID) {
                return response()->json(['success' => false, 'message' => 'This order is already paid.'], 409);
            }

            // An expired order's stock hold is long gone — never collect money
            // for it. The customer rebuilds their bag and checks out afresh.
            if ($order->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This order expired before payment. Please check out again.',
                ], 409);
            }

            // With paid Drops paused, an unpaid reservation must not be charged.
            if ($order->isDrop() && ! config('services.drops.enabled')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Drop reservations are paused. Contact support with your order reference.',
                ], 409);
            }

            // Money has already been collected against this order at the wrong
            // amount. A fresh prompt would charge the customer again before the
            // studio has reviewed (and possibly refunded) the first payment.
            if ($order->hasAmountMismatch()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This order needs studio review before another payment. Contact support with your order reference.',
                ], 409);
            }

            // SERVER-AUTHORITATIVE AMOUNT: never trust a client-supplied amount.
            // The only source of truth is the price computed at checkout.
            if ($order->hasFractionalTotal()) {
                return response()->json(['success' => false, 'message' => 'This order amount needs studio review before M-Pesa payment. Contact support with your order reference.'], 422);
            }
            $amount = $this->amountDue($order, $validated['pay'] ?? 'balance');
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
                // order in the meantime is never overwritten. Shop and drop only:
                // a bespoke order keeps its production stage.
                if ($order->isCommerce()) {
                    CustomOrder::whereKey($order->id)
                        ->paidUp(false)
                        ->update(\App\Support\LegacyOrderMap::forStatus(OrderStatus::PENDING_PAYMENT));
                }

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

        // One M-Pesa code is one transaction (Phase C): a code already booked on another
        // order is never booked again here; the Studio is told and the owner alerted.
        if ($resultCode == 0 && $receiptNumber && ($booked = $this->settlement->alreadyBooked('mpesa_stk', $receiptNumber, null))
            && (int) $booked->custom_order_id !== (int) $order->id) {
            $attempt?->markFailed((string) $resultCode, "M-Pesa code already recorded on order {$booked->order->cipher}");
            $this->settlement->flagReferenceConflict($order, $receiptNumber, $booked->order);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        // 2. Idempotency / duplicate-payment check
        if ($order->isPaidUp() || $order->legacyStatus() === OrderStatus::PAID) {
            if ($attempt && $attempt->status === PaymentAttempt::PENDING) {
                if ($resultCode == 0) {
                    // The customer paid a second prompt for an already-paid order:
                    // book it — the ledger then shows a refund owed, not a success.
                    $attempt->markPaid($receiptNumber);
                    $this->settlement->recordPayment($order, is_numeric($paidAmount) ? (int) round((float) $paidAmount) : $attempt->amount,
                        'mpesa_stk', \App\Support\Actor::safaricom(), $receiptNumber, $attempt, 'paid again: the order was already paid', $payload);
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
            $expected = $attempt?->amount ?? $order->total();
            if (! is_numeric($paidAmount) || (int) round((float) $paidAmount * 100) !== $expected * 100) {
                // Bespoke: no stock is at stake, so money in is a payment as it arrived;
                // the balance shows any shortfall or excess (design B/C).
                if ($order->isBespoke() && is_numeric($paidAmount) && (int) round((float) $paidAmount) > 0) {
                    $attempt?->markPaid($receiptNumber);
                    $booked = $this->settlement->recordPayment($order, (int) round((float) $paidAmount), 'mpesa_stk', \App\Support\Actor::safaricom(), $receiptNumber,
                        $attempt, "paid KSh {$paidAmount} against a KSh {$expected} prompt", $payload);
                    // A customer can't change an STK amount: a difference is a bug or tampering.
                    if ($booked['booked']) {
                        $this->settlement->alertUnexpectedAmount($order, $expected, (int) round((float) $paidAmount), $receiptNumber);
                    }

                    return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
                }
                // Shop (Phase C): the money is real. More settles the order and the excess is owed
                // back; less is a part payment with the stock held a day for the balance.
                // Whole shillings only: a fractional amount is never rounded into a settlement.
                if ($order->isShop() && is_numeric($paidAmount) && (float) $paidAmount >= 1 && floor((float) $paidAmount) == (float) $paidAmount) {
                    $paid = (int) round((float) $paidAmount);
                    $attempt?->markPaid($receiptNumber);
                    $booked = $this->settlement->recordPayment($order, $paid, 'mpesa_stk', \App\Support\Actor::safaricom(), $receiptNumber,
                        $attempt, "paid KSh {$paid} against a KSh {$expected} prompt", $payload);
                    if ($booked['booked'] && $paid < $expected) {
                        $this->settlement->holdForBalance($order);
                    }

                    return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
                }
                $attempt?->markFailed((string) $resultCode, 'Amount mismatch');
                $this->settlement->flagAmountMismatch($order, $expected, $paidAmount, $receiptNumber, $attempt);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            // Books the payment; becoming paid commits held stock and sends the receipt.
            $attempt?->markPaid($receiptNumber);
            $this->settlement->recordPayment($order, $expected, 'mpesa_stk', \App\Support\Actor::safaricom(), $receiptNumber, $attempt, null, $payload);
            Log::info("M-Pesa Payment Successful for Order #{$order->id}.");

        } else {
            // FAILED / CANCELLED (Codes like 1032 are common for user cancellation)
            $attempt?->markFailed((string) $resultCode, $stkCallback['ResultDesc'] ?? null);

            // Only fail the order when no other push could still pay it — a
            // cancelled retry must not clobber an earlier prompt the customer
            // may have completed. Bespoke orders keep their production stage.
            if ($order->isCommerce() && ! $order->hasLivePaymentAttempts()) {
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

        $isPaid = $order->isPaidUp();

        return response()->json([
            'success' => true,
            'is_paid' => $isPaid,
            'status' => $order->legacyStatus(),
            'payment_method' => $order->payment_method,
            'reference' => $order->cipher,
            // Server-confirmed total, so the success screen never has to trust
            // the client-side cart for the figure it displays.
            'amount' => $order->total(),
            'transaction_id' => $order->mpesa_receipt_number,
            // The ledger, for deposit and balance payments (pay page).
            'amount_paid' => (int) $order->amount_paid,
            'balance_due' => max(0, (int) $order->balance_due),
            'payment_status' => $order->payment_status,
            // The prompt the page is waiting on: pending | paid | failed.
            'attempt_status' => $request->filled('attempt')
                ? $order->paymentAttempts()->where('checkout_request_id', $request->query('attempt'))->value('status')
                : null,
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
     * What one STK prompt asks for (I2), from the ledger: the bespoke deposit
     * still unpaid when the customer chose "deposit" (bespoke only, D14),
     * otherwise the whole balance.
     */
    private function amountDue(CustomOrder $order, string $choice): int
    {
        $balance = max(0, (int) $order->balance_due);
        $depositLeft = max(0, $order->depositRequired() - (int) $order->amount_paid);

        return $choice === 'deposit' && $order->isBespoke() && $depositLeft > 0 && $depositLeft < $balance
            ? $depositLeft
            : $balance;
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
