<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use App\Models\PaymentAttempt;
use App\Models\UnmatchedPayment;
use App\Services\PaymentReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Studio → M-Pesa payment review (OD-1): the owner settles money only the statement explains. Every action needs a reason. */
class PaymentReviewController extends Controller
{
    public function __construct(private PaymentReviewService $review)
    {
    }

    private const REASON = ['required', 'string', 'max:255'];
    private const MESSAGES = ['reason.required' => 'Say what you checked, for the record.'];

    /** Money on the statement the system has no record of. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'receipt' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9]{8,12}$/'],
            'phone' => ['nullable', 'string', 'max:30'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'reason' => self::REASON,
        ], self::MESSAGES + ['receipt.regex' => 'Type the M-Pesa code exactly as on the statement (letters and digits, e.g. TJ4AB12CDE).']);
        $this->review->addFromStatement($request->user(), (int) $data['amount'], $data['receipt'], $data['phone'] ?? null,
            Carbon::parse($data['paid_at'], config('app.display_timezone'))->utc(), $data['reason']);

        return back()->with('success', 'Added to M-Pesa payment review. Match it to an order, or mark it for refund.');
    }

    public function match(Request $request, UnmatchedPayment $unmatched)
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:40'], 'phone_checked' => ['sometimes', 'boolean'], 'reason' => self::REASON],
            self::MESSAGES + ['reference.required' => 'Type the order reference (SRN-…).']);
        $order = CustomOrder::withTrashed()->where('cipher', strtoupper(trim($data['reference'])))->first()
            ?? throw ValidationException::withMessages(['reference' => 'No order has that reference.']);
        $result = $this->review->match($unmatched, $order, $request->user(), $data['reason'], (bool) ($data['phone_checked'] ?? false));
        $fresh = $order->fresh();

        return back()->with('success', 'KSh ' . number_format($unmatched->amount) . " recorded on {$order->cipher}. "
            . match (true) {
                (int) $fresh->balance_due < 0 => 'It paid more than owed: KSh ' . number_format(-(int) $fresh->balance_due) . ' is now in Refunds.',
                (int) $fresh->balance_due > 0 => 'KSh ' . number_format((int) $fresh->balance_due) . ' is still due on it.',
                ! empty($result['stock_gone']) => 'An item sold out meanwhile: the order needs your decision (payment problems).',
                default => 'The order is paid.',
            });
    }

    public function requireRefund(Request $request, UnmatchedPayment $unmatched)
    {
        $data = $request->validate(['reason' => self::REASON], self::MESSAGES);
        $this->review->requireRefund($unmatched, $request->user(), $data['reason']);

        return back()->with('success', 'Marked: refund required. It stays in the list until you record the refund.');
    }

    public function refunded(Request $request, UnmatchedPayment $unmatched)
    {
        $data = $request->validate(['refund_reference' => ['required', 'string', 'max:100'], 'reason' => self::REASON],
            self::MESSAGES + ['refund_reference.required' => 'Add the M-Pesa code of the refund you sent.']);
        $this->review->markRefunded($unmatched, $request->user(), $data['refund_reference'], $data['reason']);

        return back()->with('success', 'Refund recorded. This payment is closed.');
    }

    public function note(Request $request, UnmatchedPayment $unmatched)
    {
        $data = $request->validate(['reason' => self::REASON], self::MESSAGES);
        $this->review->note($unmatched, $request->user(), $data['reason']);

        return back()->with('success', 'Note saved. The payment stays open for review.');
    }

    public function confirmPrompt(Request $request, PaymentAttempt $attempt)
    {
        $data = $request->validate(['receipt' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9]{8,12}$/'], 'reason' => self::REASON],
            self::MESSAGES + ['receipt.regex' => 'Type the M-Pesa code exactly as on the statement.']);
        $this->review->confirmPrompt($attempt, $request->user(), $data['receipt'], $data['reason']);

        return back()->with('success', "Payment confirmed from the statement on {$attempt->order->cipher}.");
    }

    public function closePrompt(Request $request, PaymentAttempt $attempt)
    {
        $data = $request->validate(['reason' => self::REASON], self::MESSAGES);
        $this->review->closePrompt($attempt, $request->user(), $data['reason']);

        return back()->with('success', "Prompt closed. {$attempt->order->cipher} can be paid again or will expire.");
    }
}
