<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\StaffAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class StaffAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'owner']);
        $this->cashier = User::factory()->create(['role' => 'cashier', 'name' => 'Cashier', 'pos_pin' => Hash::make('2468')]);
        $this->actingAs($this->owner);
    }

    public function test_temporary_reset_revokes_password_sessions_and_old_reset_links_without_logging_secrets(): void
    {
        $token = Password::broker()->createToken($this->cashier);
        $oldHash = $this->cashier->password;
        $this->post(route('studio.staff.reset_password', $this->cashier), ['password' => 'temporary-password-5566', 'password_confirmation' => 'temporary-password-5566', 'reason' => 'Forgot sign-in password'])
            ->assertSessionHasNoErrors()->assertSessionHas('staff_password.password', 'temporary-password-5566');
        $user = $this->cashier->fresh();
        $this->assertTrue($user->must_change_password);
        $this->assertSame(1, $user->session_version);
        $this->assertTrue(Hash::check('temporary-password-5566', $user->password));
        $this->assertNotSame($oldHash, $user->password);
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
        $log = AuditLog::where('action', 'staff.password_reset')->sole();
        $this->assertSame($this->owner->id, $log->actor_id);
        foreach (['temporary-password-5566', $user->password, $token] as $secret) $this->assertStringNotContainsString($secret, json_encode($log));
        $this->actingAs($user)->withSession([StaffAccess::SESSION_VERSION => 0])->get('/pos')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'temporary-password-5566'])->assertRedirect(route('pos.index'));
        $this->get('/pos')->assertRedirect(route('profile.edit'));
        $this->put(route('password.update'), ['current_password' => 'temporary-password-5566', 'password' => 'temporary-password-5566', 'password_confirmation' => 'temporary-password-5566'])->assertSessionHasErrors('password');
        $this->assertTrue($user->fresh()->must_change_password);
        $oldSession = session()->getId();
        $this->put(route('password.update'), ['current_password' => 'temporary-password-5566', 'password' => 'own-password-7788', 'password_confirmation' => 'own-password-7788'])->assertSessionHasNoErrors();
        $this->assertNotSame($oldSession, session()->getId());
        $this->get('/pos')->assertOk();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.password_changed', 'actor_id' => $user->id]);
    }

    public function test_generated_reset_is_supported_and_invalid_passwords_do_not_change_account(): void
    {
        $this->post(route('studio.staff.reset_password', $this->cashier), ['password' => 'short', 'password_confirmation' => 'other', 'reason' => 'Recovery'])->assertSessionHasErrors('password');
        $this->assertSame(0, $this->cashier->fresh()->session_version);
        $this->post(route('studio.staff.reset_password', $this->cashier), ['reason' => 'Recovery'])->assertSessionHasNoErrors()->assertSessionHas('staff_password');
        $this->assertTrue(Hash::check(session('staff_password')['password'], $this->cashier->fresh()->password));
    }

    public function test_end_sessions_revokes_shared_tablet_operator_without_closing_the_register_or_disabling_the_account(): void
    {
        $this->postJson(route('pos.unlock'), ['pin' => '2468'])->assertOk();
        $this->postJson(route('pos.till.open'), ['register' => 'Till 1', 'float' => 1000])->assertOk();
        $password = $this->cashier->password;
        $remember = $this->cashier->remember_token;
        $this->post(route('studio.staff.end_sessions', $this->cashier), ['reason' => 'Lost tablet'])->assertSessionHasNoErrors();
        $this->getJson(route('pos.catalogue'))->assertStatus(423);
        $this->assertSame($password, $this->cashier->fresh()->password);
        $this->assertNotSame($remember, $this->cashier->fresh()->remember_token);
        $this->assertNull($this->cashier->fresh()->suspended_at);
        $this->assertDatabaseHas('till_sessions', ['register' => 'Till 1', 'status' => 'open']);
        $this->postJson(route('pos.unlock'), ['pin' => '2468'])->assertOk();
        $this->getJson(route('pos.catalogue'))->assertOk();
    }

    public function test_revoked_account_session_is_rejected_for_json_requests(): void
    {
        $this->post(route('studio.staff.end_sessions', $this->cashier), ['reason' => 'Lost device'])->assertSessionHasNoErrors();
        $this->actingAs($this->cashier->fresh())->withSession([StaffAccess::SESSION_VERSION => 0])->getJson(route('pos.catalogue'))->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_reset_blocks_pin_until_staff_selects_their_own_password(): void
    {
        $this->postJson(route('pos.unlock'), ['pin' => '2468'])->assertOk();
        $this->post(route('studio.staff.reset_password', $this->cashier), ['reason' => 'Forgot password'])->assertSessionHasNoErrors();
        $this->getJson(route('pos.catalogue'))->assertStatus(423);
        $this->postJson(route('pos.unlock'), ['pin' => '2468'])->assertUnprocessable();
    }

    public function test_name_change_preserves_sessions_email_change_revokes_sessions_and_old_email_tokens(): void
    {
        $url = route('studio.staff.details', $this->cashier);
        $this->put($url, ['name' => 'Updated Name', 'email' => $this->cashier->email, 'reason' => 'Correct spelling'])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->cashier->fresh()->session_version);
        $token = Password::broker()->createToken($this->cashier);
        $this->put($url, ['name' => 'Updated Name', 'email' => 'New.Email@example.test', 'role' => 'owner', 'reason' => 'New sign-in email'])->assertSessionHasNoErrors();
        $user = $this->cashier->fresh();
        $this->assertSame('new.email@example.test', $user->email);
        $this->assertSame('cashier', $user->role);
        $this->assertSame(1, $user->session_version);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse(Password::broker()->tokenExists($this->cashier, $token));
        $this->put($url, ['name' => 'X', 'email' => $this->owner->email, 'reason' => 'Duplicate'])->assertSessionHasErrors('email');
        $this->assertSame('new.email@example.test', $user->fresh()->email);
    }

    public function test_suspension_then_reactivation_does_not_revive_old_sessions(): void
    {
        $this->post(route('studio.staff.suspend', $this->cashier), ['reason' => 'Left shift'])->assertSessionHasNoErrors();
        $this->post(route('studio.staff.reactivate', $this->cashier), ['reason' => 'Returned'])->assertSessionHasNoErrors();
        $this->actingAs($this->cashier->fresh())->withSession([StaffAccess::SESSION_VERSION => 0])->get('/pos')->assertRedirect(route('login'));
    }

    public function test_access_history_is_scoped_paginated_readable_and_allowlists_payloads(): void
    {
        for ($i = 0; $i < 22; $i++) AuditLog::create(['actor_type' => 'user', 'actor_id' => $this->owner->id, 'subject_type' => $this->cashier->getMorphClass(), 'subject_id' => $this->cashier->id,
            'action' => 'staff.role_changed', 'reason' => 'Role review', 'before' => ['role' => 'cashier', 'password' => 'secret-old'], 'after' => ['role' => 'manager', 'password' => 'secret-new', 'pos_pin' => '9876']]);
        $response = $this->getJson(route('studio.staff.history', $this->cashier))->assertOk()->assertJsonCount(20, 'rows')->assertJsonPath('total', 22)->assertJsonPath('rows.0.action', 'Role changed');
        $this->assertStringNotContainsString('secret-', $response->getContent());
        $this->assertStringNotContainsString('9876', $response->getContent());
        $this->getJson(route('studio.staff.history', [$this->cashier, 'page' => 2]))->assertOk()->assertJsonCount(2, 'rows');
        $this->getJson(route('studio.staff.history', $this->owner))->assertOk()->assertJsonCount(0, 'rows');
    }

    public function test_all_controls_are_owner_only_and_refuse_customer_targets(): void
    {
        foreach (['manager', 'cashier', 'client'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->withSession([StaffAccess::SESSION_VERSION => 0]);
            $this->post(route('studio.staff.reset_password', $this->cashier), ['reason' => 'No'])->assertStatus($role === 'manager' ? 403 : 302);
            $this->post(route('studio.staff.end_sessions', $this->cashier), ['reason' => 'No'])->assertStatus($role === 'manager' ? 403 : 302);
            $this->put(route('studio.staff.details', $this->cashier), ['name' => 'No', 'email' => 'no@example.test', 'reason' => 'No'])->assertStatus($role === 'manager' ? 403 : 302);
            $this->get(route('studio.staff.history', $this->cashier))->assertStatus($role === 'manager' ? 403 : 302);
        }
        $customer = User::factory()->create(['role' => 'client']);
        $this->actingAs($this->owner);
        $this->post(route('studio.staff.reset_password', $customer), ['reason' => 'No'])->assertNotFound();
        $this->post(route('studio.staff.end_sessions', $customer), ['reason' => 'No'])->assertNotFound();
        $this->put(route('studio.staff.details', $customer), [])->assertNotFound();
        $this->get(route('studio.staff.history', $customer))->assertNotFound();
        $this->post(route('studio.staff.reset_password', $this->owner), ['reason' => 'Self'])->assertSessionHasErrors('password');
    }

    public function test_staff_self_service_details_are_audited_and_keep_only_the_current_session(): void
    {
        $this->actingAs($this->cashier)->patch(route('profile.update'), ['name' => 'New Name', 'email' => 'self-edited@example.test'])->assertSessionHasNoErrors();
        $this->get(route('profile.edit'))->assertOk();
        $this->assertSame(1, $this->cashier->fresh()->session_version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.details_changed', 'actor_id' => $this->cashier->id]);
        $this->withSession([StaffAccess::SESSION_VERSION => 0])->get('/pos')->assertRedirect(route('login'));
    }

    public function test_owner_can_end_their_own_sessions_without_suspending_the_last_owner(): void
    {
        $this->post(route('studio.staff.end_sessions', $this->owner), ['reason' => 'Lost device'])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull($this->owner->fresh()->suspended_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.sessions_ended', 'actor_id' => $this->owner->id]);
    }

    public function test_email_password_recovery_clears_temporary_flag_revokes_sessions_and_records_actor(): void
    {
        $this->cashier->forceFill(['must_change_password' => true])->save();
        $token = Password::broker()->createToken($this->cashier);
        auth()->logout();
        $this->post(route('password.store'), ['email' => $this->cashier->email, 'token' => $token, 'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasErrors('password');
        $this->assertTrue($this->cashier->fresh()->must_change_password);
        $this->post(route('password.store'), ['email' => $this->cashier->email, 'token' => $token, 'password' => 'recovered-password-6655', 'password_confirmation' => 'recovered-password-6655'])->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $user = $this->cashier->fresh();
        $this->assertFalse($user->must_change_password);
        $this->assertSame(1, $user->session_version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.password_recovered', 'actor_id' => $user->id]);
    }
}
