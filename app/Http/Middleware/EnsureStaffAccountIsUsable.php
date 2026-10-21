<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase E-a (design §15.1): a suspended account is signed out on its next
 * request, whatever the session driver; an account still on the owner's
 * one-time password goes to its profile until it sets its own.
 */
class EnsureStaffAccountIsUsable
{
    private const ALLOWED_WHILE_CHANGING = ['profile.edit', 'profile.update', 'password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->isSuspended()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'This account is suspended. Ask the owner.');
        }
        if ($user?->must_change_password && ! in_array($request->route()?->getName(), self::ALLOWED_WHILE_CHANGING, true)) {
            return redirect()->route('profile.edit')->with('error', 'Set your own password to continue.');
        }

        return $next($request);
    }
}
