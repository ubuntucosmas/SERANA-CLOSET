<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase E-a (design §15.1): Studio → Staff. The owner adds staff with a
 * one-time password (changed at first sign-in), changes roles, suspends and
 * reactivates, and sets till PINs, each with a reason and audited. The last
 * active owner can never be locked out, and a suspended account can't sign
 * in or keep using an open session.
 */
class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => Role::OWNER, 'name' => 'Owner', 'email' => 'owner@serana.test']);
    }

    private function staff(array $o = []): User
    {
        return User::factory()->create(array_merge(['role' => Role::CASHIER, 'name' => 'Wanjiku', 'email' => 'wanjiku@serana.test'], $o));
    }

    public function test_the_owner_sees_every_staff_member_and_no_customers(): void
    {
        $this->staff();
        User::factory()->create(['role' => Role::CLIENT, 'name' => 'Customer']);

        $staff = $this->actingAs($this->owner)->get(route('studio.index', ['tab' => 'staff']))->viewData('page')['props']['staff'];

        $this->assertSame(['Owner', 'Wanjiku'], collect($staff['members'])->pluck('name')->sort()->values()->all());
        $this->assertSame(['owner', 'manager', 'cashier'], array_column($staff['roles'], 'value'));
        $this->assertArrayNotHasKey('password', $staff['members'][0]);
        $this->assertArrayNotHasKey('pos_pin', $staff['members'][0]);
        $this->assertSame(10, $staff['policy']['cashier_discount_percent'], 'the till rules are set here too');
    }

    public function test_the_owner_adds_staff_with_a_one_time_password(): void
    {
        $this->actingAs($this->owner)->post(route('studio.staff.store'), ['name' => 'Akinyi', 'email' => 'akinyi@serana.test', 'role' => Role::MANAGER, 'reason' => 'New shop manager'])
            ->assertSessionHasNoErrors()->assertSessionHas('staff_password');

        $user = User::where('email', 'akinyi@serana.test')->sole();
        $password = session('staff_password')['password'];
        $this->assertSame([Role::MANAGER, true], [$user->role, $user->must_change_password]);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertGreaterThanOrEqual(12, strlen($password));
        $this->assertSame('New shop manager', AuditLog::where('action', 'staff.added')->sole()->reason);
        $this->assertStringNotContainsString($password, json_encode(AuditLog::all()), 'the password is never logged');

        // The owner's next page shows it once; the one after doesn't.
        $this->get(route('studio.index', ['tab' => 'staff']))->assertInertia(fn ($page) => $page->where('flash.staff_password.password', $password));
        $this->get(route('studio.index', ['tab' => 'staff']))->assertInertia(fn ($page) => $page->where('flash.staff_password', null));
    }

    public function test_owner_can_choose_the_temporary_password_and_cashier_must_replace_it(): void
    {
        $temporary = 'cashier-start-4821';
        $this->actingAs($this->owner)->post(route('studio.staff.store'), [
            'name' => 'New Cashier', 'email' => 'new-cashier@serana.test', 'role' => Role::CASHIER,
            'reason' => 'New shift', 'password' => $temporary, 'password_confirmation' => $temporary,
        ])->assertSessionHasNoErrors()->assertSessionHas('staff_password.password', $temporary);
        $cashier = User::where('email', 'new-cashier@serana.test')->sole();
        $this->assertTrue(Hash::check($temporary, $cashier->password));
        $this->assertTrue($cashier->must_change_password);
        $this->assertStringNotContainsString($temporary, json_encode(AuditLog::all()));
        auth()->logout();
        $this->post(route('login'), ['email' => $cashier->email, 'password' => $temporary])->assertRedirect(route('pos.index'));
        $this->get(route('pos.index'))->assertRedirect(route('profile.edit'));
        $this->put(route('password.update'), ['current_password' => $temporary, 'password' => 'cashier-own-password-9944', 'password_confirmation' => 'cashier-own-password-9944'])->assertSessionHasNoErrors();
        $this->assertFalse($cashier->fresh()->must_change_password);
        $this->assertTrue(Hash::check('cashier-own-password-9944', $cashier->fresh()->password));
    }

    public function test_chosen_temporary_password_requires_strength_and_matching_confirmation(): void
    {
        $base = ['name' => 'Cashier', 'email' => 'not-created@serana.test', 'role' => Role::CASHIER, 'reason' => 'New shift'];
        $this->actingAs($this->owner);
        foreach ([['password' => 'short', 'password_confirmation' => 'short'],
            ['password' => 'temporary-4821', 'password_confirmation' => 'different-9944'],
            ['password_confirmation' => 'temporary-4821']] as $passwords) {
            $this->post(route('studio.staff.store'), $base + $passwords)->assertSessionHasErrors('password');
        }
        $this->assertDatabaseMissing('users', ['email' => 'not-created@serana.test']);
    }

    public function test_adding_is_validated_and_owner_only(): void
    {
        $this->staff();
        $this->actingAs($this->owner)->post(route('studio.staff.store'), ['name' => 'X', 'email' => 'wanjiku@serana.test', 'role' => Role::CASHIER, 'reason' => 'x'])
            ->assertSessionHasErrors('email');
        $this->actingAs($this->owner)->post(route('studio.staff.store'), ['name' => 'X', 'email' => 'x@serana.test', 'role' => Role::CLIENT, 'reason' => 'x'])
            ->assertSessionHasErrors('role');
        $this->actingAs($this->owner)->post(route('studio.staff.store'), ['name' => 'X', 'email' => 'x@serana.test', 'role' => Role::CASHIER, 'reason' => ''])
            ->assertSessionHasErrors('reason');

        $manager = $this->staff(['role' => Role::MANAGER, 'email' => 'm@serana.test']);
        $this->actingAs($manager)->post(route('studio.staff.store'), ['name' => 'X', 'email' => 'x@serana.test', 'role' => Role::CASHIER, 'reason' => 'x'])->assertForbidden();
    }

    public function test_roles_change_with_a_reason_but_never_lock_the_owner_out(): void
    {
        $cashier = $this->staff();

        $this->actingAs($this->owner)->put(route('studio.staff.role', $cashier), ['role' => Role::MANAGER, 'reason' => 'Promoted'])->assertSessionHasNoErrors();
        $this->assertSame(Role::MANAGER, $cashier->fresh()->role);
        $this->assertSame(['role' => 'cashier'], AuditLog::where('action', 'staff.role_changed')->sole()->before);

        $this->actingAs($this->owner)->put(route('studio.staff.role', $this->owner), ['role' => Role::MANAGER, 'reason' => 'x'])
            ->assertSessionHasErrors(['role' => 'You can’t change your own role.']);
        $second = $this->staff(['role' => Role::OWNER, 'email' => 'second@serana.test', 'name' => 'Second Owner']);
        $this->actingAs($second)->put(route('studio.staff.role', $this->owner), ['role' => Role::MANAGER, 'reason' => 'x'])->assertSessionHasNoErrors();
        $this->actingAs($second)->put(route('studio.staff.role', $second), ['role' => Role::MANAGER, 'reason' => 'x'])->assertSessionHasErrors('role');
        $this->assertSame(Role::OWNER, $second->fresh()->role, 'the last active owner stays an owner');
    }

    public function test_a_suspended_account_cant_sign_in_or_keep_its_session(): void
    {
        $cashier = $this->staff(['password' => Hash::make('a-strong-password-1')]);
        $this->actingAs($cashier)->get(route('profile.edit'))->assertOk();
        $token = $cashier->fresh()->remember_token;

        $this->actingAs($this->owner)->post(route('studio.staff.suspend', $cashier), ['reason' => 'Left the shop'])->assertSessionHasNoErrors();
        $fresh = $cashier->fresh();
        $this->assertNotNull($fresh->suspended_at);
        $this->assertSame('Left the shop', $fresh->suspended_reason);
        $this->assertNotSame($token, $fresh->remember_token, 'remembered sign-ins stop working');

        $this->actingAs($fresh)->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
        auth()->logout();
        $this->post(route('login'), ['email' => 'wanjiku@serana.test', 'password' => 'a-strong-password-1'])
            ->assertSessionHasErrors(['email' => 'This account is suspended. Ask the owner.']);
        $this->assertGuest();

        $this->actingAs($this->owner)->post(route('studio.staff.reactivate', $cashier), ['reason' => 'Back after leave'])->assertSessionHasNoErrors();
        $this->assertNull($cashier->fresh()->suspended_at);
        $this->assertSame(2, AuditLog::whereIn('action', ['staff.suspended', 'staff.reactivated'])->count());
    }

    public function test_the_last_active_owner_and_yourself_cant_be_suspended(): void
    {
        $this->actingAs($this->owner)->post(route('studio.staff.suspend', $this->owner), ['reason' => 'x'])
            ->assertSessionHasErrors(['reason' => 'You can’t suspend yourself.']);
        $second = $this->staff(['role' => Role::OWNER, 'email' => 'second@serana.test']);
        $this->actingAs($second)->post(route('studio.staff.suspend', $this->owner), ['reason' => 'x'])->assertSessionHasNoErrors();
        $third = $this->staff(['role' => Role::MANAGER, 'email' => 'third@serana.test']);
        $this->actingAs($second)->post(route('studio.staff.suspend', $third), ['reason' => 'x'])->assertSessionHasNoErrors();
        $this->assertNull($second->fresh()->suspended_at);
    }

    public function test_the_owner_sets_a_till_pin_unique_among_active_staff(): void
    {
        $cashier = $this->staff();
        $other = $this->staff(['email' => 'other@serana.test', 'name' => 'Other']);

        $this->actingAs($this->owner)->put(route('studio.staff.pin', $cashier), ['pin' => '12ab', 'reason' => 'x'])->assertSessionHasErrors('pin');
        $this->actingAs($this->owner)->put(route('studio.staff.pin', $cashier), ['pin' => '123', 'reason' => 'x'])->assertSessionHasErrors('pin');
        $this->actingAs($this->owner)->put(route('studio.staff.pin', $cashier), ['pin' => '4821', 'reason' => 'Till access'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('4821', $cashier->fresh()->pos_pin));
        $this->actingAs($this->owner)->put(route('studio.staff.pin', $other), ['pin' => '4821', 'reason' => 'x'])
            ->assertSessionHasErrors(['pin' => 'Another staff account (active or suspended) already uses that PIN. Choose a different one.']);
        $log = AuditLog::where('action', 'staff.pin_set')->sole();
        $this->assertStringNotContainsString('4821', json_encode($log));
    }

    public function test_a_new_password_is_required_at_first_sign_in(): void
    {
        $user = $this->staff(['password' => Hash::make('one-time-password-x9'), 'must_change_password' => true]);

        $this->actingAs($user)->get(route('studio.index'))->assertRedirect(route('profile.edit'));
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();

        $this->actingAs($user)->put(route('password.update'), ['current_password' => 'one-time-password-x9', 'password' => 'my-own-password-77', 'password_confirmation' => 'my-own-password-77'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->must_change_password);
    }
}
