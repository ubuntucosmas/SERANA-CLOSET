<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Who is at the till right now (D12, design §15.3). The tablet stays signed
 * in as a staff account; each person unlocks it with their own PIN, and
 * every till action is recorded as them. It locks after the idle minutes.
 */
final class PosOperator
{
    private const ID = 'pos.operator_id';
    private const SEEN = 'pos.operator_seen';
    private const VERSION = 'pos.operator_version';

    /** The active staff member with this PIN, or null. */
    public static function byPin(string $pin): ?User
    {
        return User::whereIn('role', Role::STAFF)->whereNull('suspended_at')->where('must_change_password', false)->whereNotNull('pos_pin')->get()
            ->first(fn (User $u) => Hash::check($pin, $u->pos_pin) && Role::allows($u->role, 'pos.sell'));
    }

    public static function unlock(Request $request, string $pin): User
    {
        $user = self::byPin($pin) ?? throw ValidationException::withMessages(['pin' => 'That PIN doesn’t match anyone.']);
        $request->session()->put([self::ID => $user->id, self::SEEN => now()->timestamp, self::VERSION => (int) $user->session_version]);

        return $user;
    }

    public static function lock(Request $request): void
    {
        $request->session()->forget([self::ID, self::SEEN, self::VERSION]);
    }

    /** The unlocked operator, still active and not idle too long; touches the idle clock. */
    public static function current(Request $request): ?User
    {
        $id = $request->session()->get(self::ID);
        $seen = (int) $request->session()->get(self::SEEN, 0);
        if (! $id || now()->timestamp - $seen > ShopPolicy::posIdleMinutes() * 60) {
            self::lock($request);

            return null;
        }
        $user = User::whereKey($id)->whereNull('suspended_at')->first();
        if (! $user || $user->must_change_password || (int) $request->session()->get(self::VERSION, 0) !== (int) $user->session_version || ! Role::allows($user->role, 'pos.sell')) {
            self::lock($request);

            return null;
        }
        $request->session()->put(self::SEEN, now()->timestamp);

        return $user;
    }

    /**
     * A manager's (or the owner's) approval on the same screen: their PIN,
     * checked here, returns them so the action records both people.
     */
    public static function approver(?string $pin, string $field = 'approval_pin'): User
    {
        $user = $pin !== null && $pin !== '' ? self::byPin($pin) : null;
        if (! $user || ! Role::allows($user->role, 'pos.approve')) {
            throw ValidationException::withMessages([$field => $pin ? 'A manager or the owner must approve with their PIN.' : 'This needs a manager’s PIN.']);
        }

        return $user;
    }
}
