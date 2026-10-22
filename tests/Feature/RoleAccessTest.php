<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The batch-2 role matrix (docs/design/BATCH-2-DESIGN.md §5), enforced server-side. */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_owner_and_manager_use_the_studio_cashier_and_client_do_not(): void
    {
        $this->actingAs($this->as(Role::OWNER))->get('/studio')->assertOk();
        $this->actingAs($this->as(Role::MANAGER))->get('/studio')->assertOk();
        $this->actingAs($this->as(Role::CASHIER))->get('/studio')->assertRedirect(route('pos.index'));
        $this->actingAs($this->as(Role::CLIENT))->get('/studio')->assertRedirect('/');
    }

    public function test_only_the_owner_changes_payment_settings_or_tests_mpesa(): void
    {
        $settings = ['mpesa_enabled' => true, 'whatsapp_enabled' => true, 'instructions' => '', 'whatsapp_hold_hours' => 48];

        $this->actingAs($this->as(Role::MANAGER))->put(route('admin.payment_settings.update'), $settings)->assertForbidden();
        $this->actingAs($this->as(Role::MANAGER))->postJson(route('admin.payment_settings.test_mpesa'))->assertForbidden();

        $this->actingAs($this->as(Role::OWNER))->put(route('admin.payment_settings.update'), $settings)->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_owner_and_manager_may_record_money_cashiers_may_not(): void
    {
        $order = CustomOrder::create(['full_name' => 'A', 'whatsapp_number' => '254712345678', 'type' => 'custom', 'price_quoted' => 3000]);

        $this->actingAs($this->as(Role::CASHIER))->post(route('studio.order.record_payment', $order), ['amount' => 3000, 'reference' => 'X'])->assertRedirect(route('pos.index'));
        $this->actingAs($this->as(Role::MANAGER))->post(route('studio.order.record_payment', $order), ['amount' => 3000, 'method' => 'mpesa_manual', 'reference' => 'QX1'])->assertSessionHasNoErrors();
    }

    public function test_the_ui_receives_the_same_abilities_the_server_enforces(): void
    {
        $this->actingAs($this->as(Role::MANAGER))->get('/studio')
            ->assertInertia(fn ($page) => $page->where('auth.user.studio', true)
                ->where('auth.user.abilities', fn ($a) => in_array('studio.access', $a->all(), true) && ! in_array('settings.payments', $a->all(), true)));

        $this->actingAs($this->as(Role::CLIENT))->get('/my-vault')
            ->assertInertia(fn ($page) => $page->where('auth.user.studio', false)->where('auth.user.abilities', []));
    }

    public function test_staff_sign_in_lands_on_the_right_screen(): void
    {
        User::factory()->create(['email' => 'm@serana.test', 'role' => Role::MANAGER]);
        $this->post('/login', ['email' => 'm@serana.test', 'password' => 'password'])->assertRedirect(route('studio.index'));

        auth()->logout();
        User::factory()->create(['email' => 'c@serana.test', 'role' => Role::CASHIER]);
        // A cashier works the till only (Phase E).
        $this->post('/login', ['email' => 'c@serana.test', 'password' => 'password'])->assertRedirect(route('pos.index'));
    }

    public function test_the_role_migration_renames_admin_to_owner_and_reverses(): void
    {
        $migration = require database_path('migrations/2026_10_19_000001_rename_admin_role_to_owner.php');
        $legacy = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);

        $migration->up();
        $this->assertSame('owner', $legacy->fresh()->role);
        $this->assertSame('client', $client->fresh()->role);

        $cashier = User::factory()->create(['role' => 'cashier']);
        $migration->down();
        $this->assertSame('admin', $legacy->fresh()->role);
        $this->assertSame('client', $cashier->fresh()->role, 'cashiers had no access before roles existed');
        $this->assertSame(0, DB::table('users')->whereIn('role', ['owner', 'manager', 'cashier'])->count());
    }

    public function test_serana_admin_creates_staff_of_a_chosen_role_and_keeps_roles_on_reset(): void
    {
        $this->artisan('serana:admin', ['email' => 'm2@serana.test', '--role' => 'manager', '--generate' => true])->assertSuccessful();
        $this->assertSame('manager', User::where('email', 'm2@serana.test')->value('role'));

        $this->artisan('serana:admin', ['email' => 'm2@serana.test', '--generate' => true])->assertSuccessful();
        $this->assertSame('manager', User::where('email', 'm2@serana.test')->value('role'), 'a password reset keeps the role');

        $this->artisan('serana:admin', ['email' => 'x@serana.test', '--role' => 'king', '--generate' => true])->assertFailed();
    }
}
