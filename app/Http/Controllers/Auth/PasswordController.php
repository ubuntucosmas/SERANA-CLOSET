<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed', 'different:current_password'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $validated) {
            $user = $request->user();
            $user->forceFill(['password' => Hash::make($validated['password']), 'must_change_password' => false])->save();
            \App\Support\StaffAccess::revoke($user);
            $request->session()->regenerate(true);
            $request->session()->put(\App\Support\StaffAccess::SESSION_VERSION, (int) $user->session_version);
            if ($user->isStaff()) \App\Support\Audit::record('staff.password_changed', $user, [], ['password_changed' => true, 'sessions_ended' => true], 'Staff member changed their own password.', \App\Support\Actor::user($user));
        });

        return back();
    }
}
