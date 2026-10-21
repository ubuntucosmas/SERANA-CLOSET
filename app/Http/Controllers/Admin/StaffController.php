<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Studio → Staff (Phase E-a, design §15.1), owner only. Every change has a
 * reason and is audited. The last active owner can't be demoted or
 * suspended, and nobody changes their own role or suspends themselves.
 */
class StaffController extends Controller
{
    /** What the Staff screen shows. Never passwords or PINs. */
    public static function forStudio(): array
    {
        return [
            'members' => User::whereIn('role', Role::STAFF)->orderByRaw("CASE role WHEN 'owner' THEN 0 WHEN 'manager' THEN 1 ELSE 2 END")->orderBy('name')->get()
                ->map(fn (User $u) => [
                    'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role,
                    'suspended' => $u->isSuspended(), 'suspended_reason' => $u->suspended_reason,
                    'has_pin' => $u->pos_pin !== null, 'must_change_password' => (bool) $u->must_change_password,
                    'last_login_at' => $u->last_login_at?->copy()->timezone(config('app.display_timezone'))->format('d M Y, H:i'),
                ])->all(),
            'roles' => [
                ['value' => Role::OWNER, 'label' => 'Owner', 'help' => 'Everything, including payments settings and staff.'],
                ['value' => Role::MANAGER, 'label' => 'Manager', 'help' => 'The Studio and the till, without payments settings or staff.'],
                ['value' => Role::CASHIER, 'label' => 'Cashier', 'help' => 'The till only.'],
            ],
            // The till's rules (Phase E), set by the same owner who sets the PINs.
            'policy' => \App\Support\ShopPolicy::forStudio(),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(Role::STAFF)],
            'reason' => ['required', 'string', 'max:255'],
        ], ['email.unique' => 'Someone already signs in with that email.', 'reason.required' => 'Say why, for the record.']);

        // Shown once to the owner, changed by the person at first sign-in.
        $password = Str::password(16, symbols: false);
        $user = User::forceCreate(['name' => $data['name'], 'email' => $data['email'], 'role' => $data['role'],
            'password' => Hash::make($password), 'must_change_password' => true]);
        Audit::record('staff.added', $user, [], ['email' => $user->email, 'role' => $user->role], $data['reason']);

        return back()->with('success', "{$user->name} can now sign in.")
            ->with('staff_password', ['email' => $user->email, 'password' => $password]);
    }

    public function role(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', Rule::in(Role::STAFF)], 'reason' => ['required', 'string', 'max:255']]);
        $this->staffOnly($user);
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['role' => 'You can’t change your own role.']);
        }
        if ($user->role === Role::OWNER && $data['role'] !== Role::OWNER) {
            $this->keepAnOwner($user, 'role');
        }
        $before = $user->role;
        $user->forceFill(['role' => $data['role']])->save();
        Audit::record('staff.role_changed', $user, ['role' => $before], ['role' => $user->role], $data['reason']);

        return back()->with('success', "{$user->name} is now " . strtolower(collect(self::forStudio()['roles'])->firstWhere('value', $user->role)['label']) . '.');
    }

    public function suspend(Request $request, User $user)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Say why, for the record.']);
        $this->staffOnly($user);
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['reason' => 'You can’t suspend yourself.']);
        }
        if ($user->role === Role::OWNER) {
            $this->keepAnOwner($user, 'reason');
        }
        // A new remember token ends "remember me" sign-ins; the middleware ends open sessions.
        $user->forceFill(['suspended_at' => now(), 'suspended_reason' => $data['reason'], 'remember_token' => Str::random(60)])->save();
        Audit::record('staff.suspended', $user, [], ['suspended' => true], $data['reason']);

        return back()->with('success', "{$user->name} is suspended and signed out.");
    }

    public function reactivate(Request $request, User $user)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->staffOnly($user);
        $user->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();
        Audit::record('staff.reactivated', $user, ['suspended' => true], ['suspended' => false], $data['reason']);

        return back()->with('success', "{$user->name} can sign in again.");
    }

    /** The till PIN (D12): 4–6 digits, hashed, unique among active staff. */
    public function pin(Request $request, User $user)
    {
        $data = $request->validate(['pin' => ['required', 'regex:/^\d{4,6}$/'], 'reason' => ['required', 'string', 'max:255']],
            ['pin.regex' => 'Use 4 to 6 digits.']);
        $this->staffOnly($user);
        $taken = User::whereIn('role', Role::STAFF)->whereNull('suspended_at')->whereNotNull('pos_pin')->where('id', '!=', $user->id)->get(['id', 'pos_pin'])
            ->contains(fn (User $other) => Hash::check($data['pin'], $other->pos_pin));
        if ($taken) {
            throw ValidationException::withMessages(['pin' => 'Another staff member already uses that PIN.']);
        }
        $user->forceFill(['pos_pin' => Hash::make($data['pin'])])->save();
        Audit::record('staff.pin_set', $user, [], ['pin_set' => true], $data['reason']);

        return back()->with('success', "{$user->name}’s till PIN is set.");
    }

    private function staffOnly(User $user): void
    {
        abort_unless($user->isStaff(), 404);
    }

    private function keepAnOwner(User $user, string $field): void
    {
        $others = User::where('role', Role::OWNER)->whereNull('suspended_at')->where('id', '!=', $user->id)->exists();
        if (! $others) {
            throw ValidationException::withMessages([$field => 'The shop needs at least one active owner.']);
        }
    }
}
