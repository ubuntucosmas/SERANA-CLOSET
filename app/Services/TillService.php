<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Location;
use App\Models\TillSession;
use App\Models\User;
use App\Support\Actor;
use App\Support\Audit;
use App\Support\ShopPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Till sessions and their cash (design §15.2). The only writer of
 * cash_movements. The session row is locked last in the global lock order
 * (stock levels, holds, order, ledger, then the till).
 */
class TillService
{
    public function open(Location $at, string $register, int $float, User $by): TillSession
    {
        if ($float < 0) {
            throw ValidationException::withMessages(['float' => 'The float can’t be negative.']);
        }

        return DB::transaction(function () use ($at, $register, $float, $by) {
            if (TillSession::where('location_id', $at->id)->where('register', $register)->where('status', 'open')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['register' => "{$register} is already open."]);
            }
            $session = TillSession::create(['location_id' => $at->id, 'register' => $register, 'opened_by' => $by->id, 'opened_at' => now(), 'opening_float' => $float]);
            $this->cash($session, CashMovement::FLOAT, $float, Actor::user($by), 'Opening float');
            Audit::record('till.opened', $session, [], ['register' => $register, 'float' => $float], null, Actor::user($by));

            return $session;
        }, 3);
    }

    /** The open session on this register (the tablet's), or a clear refusal. */
    public function current(?Location $at = null, string $register = 'Till 1'): TillSession
    {
        $at ??= Location::query()->online()->firstOrFail();

        return TillSession::where('location_id', $at->id)->where('register', $register)->where('status', 'open')->first()
            ?? throw ValidationException::withMessages(['till' => "Open {$register} first."]);
    }

    /** Cash out of the drawer for an expense (payout) or to the bank (pickup): a reason and a manager. */
    public function takeOut(TillSession $session, string $type, int $amount, string $reason, User $by, User $approver): CashMovement
    {
        if ($amount < 1) {
            throw ValidationException::withMessages(['amount' => 'Enter at least KSh 1.']);
        }

        return DB::transaction(function () use ($session, $type, $amount, $reason, $by, $approver) {
            $locked = $this->lock($session);
            $available = $this->expectedCash($locked);
            if ($amount > $available) {
                throw ValidationException::withMessages(['amount' => 'Only KSh ' . number_format($available) . ' should be in the drawer.']);
            }
            $movement = $this->cash($locked, $type, -$amount, Actor::user($by), $reason, ['approved_by' => $approver->id]);
            Audit::record("till.{$type}", $locked, [], ['amount' => $amount, 'approved_by' => $approver->id], $reason, Actor::user($by));

            return $movement;
        }, 3);
    }

    /**
     * Close the drawer: expected cash from the movements, the variance
     * against the count; over the alert it needs a reason and a manager.
     */
    public function close(TillSession $session, int $counted, User $by, ?string $varianceReason, ?User $approver): array
    {
        return DB::transaction(function () use ($session, $counted, $by, $varianceReason, $approver) {
            $locked = $this->lock($session);
            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['till' => 'This till is already closed.']);
            }
            $expected = $this->expectedCash($locked);
            $variance = $counted - $expected;
            $locked->update(['status' => 'closed', 'open_marker' => null, 'closed_by' => $by->id, 'closed_at' => now(),
                'expected_cash' => $expected, 'counted_cash' => $counted, 'variance' => $variance,
                'variance_reason' => $varianceReason, 'acknowledged_by' => $approver?->id]);
            Audit::record('till.closed', $locked, [], ['expected' => $expected, 'counted' => $counted, 'variance' => $variance, 'acknowledged_by' => $approver?->id],
                $varianceReason, Actor::user($by));

            return $this->report($locked->fresh());
        }, 3);
    }

    /** Is this variance big enough to need a reason and a manager? */
    public function needsAcknowledgement(int $variance): bool
    {
        return abs($variance) > ShopPolicy::tillVarianceAlert();
    }

    public function expectedCash(TillSession $session): int
    {
        return (int) CashMovement::where('till_session_id', $session->id)->sum('amount');
    }

    /** The Z report: cash by kind, sales and refunds by method, discounts, voids, the count. */
    public function report(TillSession $session): array
    {
        $cash = CashMovement::where('till_session_id', $session->id)->selectRaw('type, SUM(amount) as total, COUNT(*) as n')->groupBy('type')->get()->keyBy('type');
        $payments = DB::table('order_payments')->where('till_session_id', $session->id)
            ->selectRaw('type, method, SUM(amount) as total, COUNT(*) as n')->groupBy('type', 'method')->get();
        $orders = DB::table('custom_orders')->where('till_session_id', $session->id);

        return [
            'register' => $session->register,
            'opened_at' => $session->opened_at?->copy()->timezone(config('app.display_timezone'))->format('d M Y, H:i'),
            'closed_at' => $session->closed_at?->copy()->timezone(config('app.display_timezone'))->format('d M Y, H:i'),
            'opened_by' => $session->openedBy?->name,
            'opening_float' => $session->opening_float,
            'cash' => collect([CashMovement::SALE, CashMovement::REFUND, CashMovement::PAYOUT, CashMovement::PICKUP])
                ->mapWithKeys(fn ($t) => [$t => (int) ($cash[$t]->total ?? 0)])->all(),
            'sales_by_method' => $payments->where('type', 'payment')->mapWithKeys(fn ($p) => [$p->method => (int) $p->total])->all(),
            'refunds_by_method' => $payments->where('type', 'refund')->mapWithKeys(fn ($p) => [$p->method => (int) $p->total])->all(),
            'sales_count' => (clone $orders)->where('order_state', '!=', 'cancelled')->count(),
            'voids' => (clone $orders)->where('order_state', 'cancelled')->count(),
            'discounts' => (int) DB::table('order_lines')->whereIn('custom_order_id', (clone $orders)->pluck('id'))->where('line_type', 'discount')->sum('amount') * -1,
            'expected_cash' => $session->expected_cash ?? $this->expectedCash($session),
            'counted_cash' => $session->counted_cash,
            'variance' => $session->variance,
            'variance_reason' => $session->variance_reason,
        ];
    }

    /** Write a cash movement inside the caller's transaction (sales and refunds use this too). */
    public function cash(TillSession $session, string $type, int $amount, Actor $actor, ?string $reason = null, array $extra = []): CashMovement
    {
        return CashMovement::create(array_merge(['till_session_id' => $session->id, 'type' => $type, 'amount' => $amount,
            'actor_type' => $actor->type, 'actor_id' => $actor->id, 'reason' => $reason, 'occurred_at' => now()], $extra));
    }

    /** The last step of the global lock order. */
    public function lock(TillSession $session): TillSession
    {
        return TillSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
    }
}
