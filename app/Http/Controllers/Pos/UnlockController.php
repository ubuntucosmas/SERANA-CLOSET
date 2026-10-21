<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Support\Actor;
use App\Support\Audit;
use App\Support\PosOperator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * A PIN unlocks the till screen only (D12): it never signs anyone in to the
 * website or the Studio. Five wrong PINs in a row on a tablet lock its PIN
 * pad for five minutes, audited; a right PIN clears the count.
 */
class UnlockController extends Controller
{
    private const TRIES = 5;
    private const LOCK_SECONDS = 300;

    public function unlock(Request $request)
    {
        $key = 'pos-pin:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($key, self::TRIES)) {
            throw ValidationException::withMessages(['pin' => 'Too many wrong PINs. The till is locked for '
                . max(1, (int) ceil(RateLimiter::availableIn($key) / 60)) . ' minutes.'])->status(429);
        }
        $data = $request->validate(['pin' => ['required', 'string', 'max:12']]);
        try {
            if (! preg_match('/^\d{4,6}$/', $data['pin'])) {
                throw ValidationException::withMessages(['pin' => 'That PIN doesn’t match anyone.']);
            }
            $operator = PosOperator::unlock($request, $data['pin']);
        } catch (ValidationException $e) {
            RateLimiter::hit($key, self::LOCK_SECONDS);
            if (RateLimiter::tooManyAttempts($key, self::TRIES)) {
                Audit::record('pos.pin_lockout', $request->user(), [], ['tries' => self::TRIES, 'ip' => $request->ip()], 'Too many wrong PINs', Actor::user($request->user()));
            }
            throw $e;
        }
        RateLimiter::clear($key);

        return response()->json(['operator' => ['id' => $operator->id, 'name' => $operator->name, 'role' => $operator->role,
            'can_approve' => \App\Support\Role::allows($operator->role, 'pos.approve')]]);
    }

    public function lock(Request $request)
    {
        PosOperator::lock($request);

        return response()->json(['locked' => true]);
    }
}
