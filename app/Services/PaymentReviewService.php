<?php

namespace App\Services;

use App\Models\CustomOrder;
use App\Models\PaymentAttempt;
use App\Models\UnmatchedPayment;
use App\Models\User;
use App\Support\Actor;
use App\Support\Audit;
use App\Support\OrderStatus;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * M-Pesa money only the statement can explain (OD-1, 2026-10-22):
 *
 *   unmatched or statement-only payment → needs review → the owner verifies
 *   → matched to an order (booked by the settlement service) or refund
 *   required → refunded. Every step has a reason and is audited.
 *
 * Two kinds of case: a payment that matches no order (an unmatched callback,
 * or one the owner found on the statement), and a prompt nobody could
 * confirm (pending past reconciliation's day). Money is never attached to an
 * order without the owner choosing that order, and the phone on it must match
 * or the owner must say they checked.
 */
class PaymentReviewService
{
    /** A prompt older than this is no longer asked about by mpesa:reconcile. */
    public const STALE_PROMPT_HOURS = 24;

    public function __construct(private OrderSettlementService $settlement)
    {
    }

    /** A successful callback that matches no order: kept for review, never dropped. */
    public function recordUnmatchedCallback(string $checkoutRequestId, array $metadata, array $payload): ?UnmatchedPayment
    {
        $receipt = isset($metadata['MpesaReceiptNumber']) ? mb_substr(strtoupper(trim((string) $metadata['MpesaReceiptNumber'])), 0, 30) : null;
        if ($receipt && $this->settlement->referenceHolder('mpesa_stk', $receipt)) {
            return null; // already booked on an order: not unmatched
        }
        $unmatched = UnmatchedPayment::firstOrCreate(['checkout_request_id' => mb_substr($checkoutRequestId, 0, 100)], [
            'source' => 'callback', 'method' => 'mpesa_stk', 'receipt' => $receipt,
            'amount' => is_numeric($metadata['Amount'] ?? null) ? (int) round((float) $metadata['Amount']) : 0,
            'phone' => isset($metadata['PhoneNumber']) ? Phone::normalize((string) $metadata['PhoneNumber']) : null,
            'paid_at' => now(), 'raw' => $payload,
        ]);
        if ($unmatched->wasRecentlyCreated) {
            Audit::record('payment.unmatched', $unmatched, [], ['amount' => $unmatched->amount, 'receipt' => $receipt,
                'phone_ending' => substr((string) $unmatched->phone, -3) ?: null], null, Actor::safaricom());
        }

        return $unmatched;
    }

    /** The owner found money on the M-Pesa statement that the system has no record of. */
    public function addFromStatement(User $by, int $amount, string $receipt, ?string $phone, \DateTimeInterface $paidAt, string $reason): UnmatchedPayment
    {
        $receipt = strtoupper(trim($receipt));
        $this->assertCodeFree($receipt);

        $unmatched = UnmatchedPayment::create(['source' => 'statement', 'method' => 'mpesa_manual', 'receipt' => $receipt, 'amount' => $amount,
            'phone' => $phone ? Phone::normalize($phone) : null, 'paid_at' => $paidAt, 'notes' => $this->stamp($by, $reason)]);
        Audit::record('payment.statement_added', $unmatched, [], ['amount' => $amount, 'receipt' => $receipt], $reason, Actor::user($by));

        return $unmatched;
    }

    /** Book the money on the order the owner chose, through the settlement service. */
    public function match(UnmatchedPayment $unmatched, CustomOrder $order, User $by, string $reason, bool $phoneChecked): array
    {
        if (! $this->phoneMatches($unmatched, $order) && ! $phoneChecked) {
            throw ValidationException::withMessages(['phone_checked' => 'The phone that paid is not the one on ' . $order->cipher
                . '. Confirm you checked with the customer that this payment is theirs.']);
        }

        return DB::transaction(function () use ($unmatched, $order, $by, $reason) {
            $locked = UnmatchedPayment::whereKey($unmatched->id)->lockForUpdate()->firstOrFail();
            if ($locked->state !== UnmatchedPayment::NEEDS_REVIEW) {
                throw ValidationException::withMessages(['order' => 'This payment was already dealt with (' . str_replace('_', ' ', $locked->state) . ').']);
            }
            $this->assertCodeFree($locked->receipt, $locked->id);
            $result = $this->settlement->recordPayment($order, $locked->amount, $locked->method, Actor::user($by), $locked->receipt, null,
                'Matched from M-Pesa payment review: ' . $reason);
            if (! $result['booked']) {
                throw ValidationException::withMessages(['order' => 'This M-Pesa code is already recorded on an order.']);
            }
            $locked->update(['state' => UnmatchedPayment::MATCHED, 'custom_order_id' => $order->id, 'order_payment_id' => $result['payment']->id,
                'resolved_by' => $by->id, 'resolved_at' => now(), 'notes' => $this->append($locked, $by, "Matched to {$order->cipher}: {$reason}")]);
            Audit::record('payment.matched', $order, [], ['unmatched_payment_id' => $locked->id, 'amount' => $locked->amount, 'receipt' => $locked->receipt], $reason, Actor::user($by));

            return $result;
        }, 3);
    }

    public function requireRefund(UnmatchedPayment $unmatched, User $by, string $reason): void
    {
        $this->transition($unmatched, UnmatchedPayment::NEEDS_REVIEW, UnmatchedPayment::REFUND_REQUIRED, 'payment.review_refund_required', $by, "Refund required: {$reason}", [], $reason);
    }

    public function markRefunded(UnmatchedPayment $unmatched, User $by, string $reference, string $reason): void
    {
        $this->transition($unmatched, UnmatchedPayment::REFUND_REQUIRED, UnmatchedPayment::REFUNDED, 'payment.review_refunded', $by,
            "Refunded (ref {$reference}): {$reason}", ['refund_reference' => $reference, 'resolved_by' => $by->id, 'resolved_at' => now()], $reason);
    }

    /** "No match yet": what was checked, for whoever looks next. It stays open. */
    public function note(UnmatchedPayment $unmatched, User $by, string $note): void
    {
        $unmatched->update(['notes' => $this->append($unmatched, $by, $note)]);
        Audit::record('payment.review_noted', $unmatched, [], [], $note, Actor::user($by));
    }

    /** A stale prompt the owner found on the statement: settles its order like the callback would have. */
    public function confirmPrompt(PaymentAttempt $attempt, User $by, string $receipt, string $reason): array
    {
        $receipt = strtoupper(trim($receipt));
        $this->assertStale($attempt);
        $this->assertCodeFree($receipt);
        $result = $this->settlement->recordPayment($attempt->order, $attempt->amount, 'mpesa_stk', Actor::user($by), $receipt, $attempt,
            'Confirmed from the M-Pesa statement: ' . $reason);
        $attempt->markPaid($receipt);
        Audit::record('payment.prompt_confirmed', $attempt->order, [], ['attempt' => $attempt->id, 'amount' => $attempt->amount, 'receipt' => $receipt], $reason, Actor::user($by));

        return $result;
    }

    /** A stale prompt that is not on the statement: closed, so the order can be paid again or expire. */
    public function closePrompt(PaymentAttempt $attempt, User $by, string $reason): void
    {
        $this->assertStale($attempt);
        $attempt->markFailed(null, 'Not on the M-Pesa statement (checked by ' . $by->name . ')');
        $order = $attempt->order;
        if ($order && $order->isCommerce() && ! $order->hasLivePaymentAttempts()) {
            $this->settlement->markUnpaid($order, OrderStatus::PAYMENT_FAILED);
        }
        Audit::record('payment.prompt_closed', $order, [], ['attempt' => $attempt->id, 'amount' => $attempt->amount], $reason, Actor::user($by));
    }

    /** The Studio workspace: open unmatched money and stale prompts, oldest first, each with what to compare. */
    public function forStudio(): array
    {
        $unmatched = UnmatchedPayment::open()->oldest('id')->get()->map(fn (UnmatchedPayment $u) => [
            'kind' => 'unmatched', 'id' => $u->id, 'status' => $u->state, 'source' => $u->source,
            'amount' => $u->amount, 'receipt' => $u->receipt, 'phone_ending' => substr((string) $u->phone, -3) ?: null,
            'paid_at' => $u->paid_at?->copy()->timezone(config('app.display_timezone'))->format('d M Y, H:i'),
            'notes' => $u->notes,
            'likely_orders' => $this->likelyOrders($u)->map(fn (CustomOrder $o) => ['id' => $o->id, 'reference' => $o->cipher, 'customer' => $o->full_name,
                'balance_due' => max(0, (int) $o->balance_due), 'phone_matches' => $this->phoneMatches($u, $o)])->values()->all(),
        ]);
        $prompts = PaymentAttempt::with('order')->pending()->where('checkout_request_id', 'not like', PaymentAttempt::UNCONFIRMED_PREFIX . '%')
            ->where('created_at', '<', now()->subHours(self::STALE_PROMPT_HOURS))->oldest('id')->get()
            ->filter(fn ($a) => $a->order !== null)->map(fn (PaymentAttempt $a) => [
                'kind' => 'prompt', 'id' => $a->id, 'status' => 'needs_review', 'amount' => (int) $a->amount, 'phone_ending' => substr((string) $a->phone, -3),
                'paid_at' => $a->created_at->copy()->timezone(config('app.display_timezone'))->format('d M Y, H:i'),
                'order' => ['id' => $a->order->id, 'reference' => $a->order->cipher, 'customer' => $a->order->full_name,
                    'state' => $a->order->isPaidUp() ? 'paid' : $a->order->legacyStatus()],
            ]);

        return $unmatched->concat($prompts)->values()->all();
    }

    /** Unpaid orders this money could belong to: the same amount, around the same time, the phone first. */
    public function likelyOrders(UnmatchedPayment $u): \Illuminate\Support\Collection
    {
        $at = $u->paid_at ?? $u->created_at;

        return CustomOrder::where('balance_due', $u->amount)->where('created_at', '>=', $at->copy()->subDays(3))->where('created_at', '<=', $at->copy()->addHour())
            ->latest('id')->limit(20)->get()
            ->sortByDesc(fn (CustomOrder $o) => $this->phoneMatches($u, $o))->take(3)->values();
    }

    public function phoneMatches(UnmatchedPayment $u, CustomOrder $order): bool
    {
        $paid = Phone::normalize((string) $u->phone);

        return $paid !== '' && collect([$order->phone, $order->whatsapp_number])->filter()->contains(fn ($p) => Phone::normalize($p) === $paid);
    }

    private function transition(UnmatchedPayment $unmatched, string $from, string $to, string $action, User $by, string $note, array $extra, string $reason): void
    {
        DB::transaction(function () use ($unmatched, $from, $to, $action, $by, $note, $extra, $reason) {
            $locked = UnmatchedPayment::whereKey($unmatched->id)->lockForUpdate()->firstOrFail();
            if ($locked->state !== $from) {
                throw ValidationException::withMessages(['reason' => 'This payment is ' . str_replace('_', ' ', $locked->state) . ' already.']);
            }
            $locked->update(['state' => $to, 'notes' => $this->append($locked, $by, $note)] + $extra);
            Audit::record($action, $locked, ['status' => $from], ['status' => $to] + array_intersect_key($extra, ['refund_reference' => 1]), $reason, Actor::user($by));
        });
    }

    private function assertStale(PaymentAttempt $attempt): void
    {
        if ($attempt->status !== PaymentAttempt::PENDING || $attempt->isUnconfirmed() || $attempt->created_at > now()->subHours(self::STALE_PROMPT_HOURS)) {
            throw ValidationException::withMessages(['reason' => 'Only a prompt still unconfirmed after a day is settled here; newer ones are still being checked automatically.']);
        }
    }

    /** One M-Pesa code is one payment: not on an order, and not already in this list (except the case itself). */
    private function assertCodeFree(?string $receipt, ?int $exceptUnmatchedId = null): void
    {
        if ($receipt === null || $receipt === '') {
            return;
        }
        if ($holder = $this->settlement->referenceHolder('mpesa_stk', $receipt)) {
            throw ValidationException::withMessages(['receipt' => "M-Pesa code {$receipt} is already recorded on order {$holder->order->cipher}."]);
        }
        if (UnmatchedPayment::where('receipt', $receipt)->when($exceptUnmatchedId, fn ($q, $id) => $q->whereKeyNot($id))->exists()) {
            throw ValidationException::withMessages(['receipt' => "M-Pesa code {$receipt} is already in the payment review list."]);
        }
    }

    private function stamp(User $by, string $text): string
    {
        return '[' . now()->timezone(config('app.display_timezone'))->format('Y-m-d H:i') . "] {$by->name}: {$text}";
    }

    private function append(UnmatchedPayment $u, User $by, string $text): string
    {
        return trim(($u->notes ? $u->notes . "\n" : '') . $this->stamp($by, $text));
    }
}
