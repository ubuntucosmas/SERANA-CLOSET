<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            // Staff accounts carry the audit trail: the owner suspends them in Studio → Staff instead.
            'canDeleteAccount' => ! in_array($request->user()->role, \App\Support\Role::STAFF, true),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        if ($request->user()->isStaff()) {
            $version = (int) $request->user()->session_version;
            \App\Support\StaffAccess::updateDetails($request->user(), $request->validated(), 'Staff member updated their own account details.');
            if ($version !== (int) $request->user()->session_version) $request->session()->regenerate(true);
            $request->session()->put(\App\Support\StaffAccess::SESSION_VERSION, (int) $request->user()->session_version);
            return Redirect::route('profile.edit');
        }
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // A staff account is never deleted by its holder: the ledger, audit log and
        // till history name it (deleting would erase that, or fail on the till's
        // records). Staff leave by being suspended in Studio → Staff.
        if (in_array($request->user()->role, \App\Support\Role::STAFF, true)) {
            abort(403, 'Staff accounts can’t be deleted here. Ask the owner to suspend the account in Studio → Staff.');
        }

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
