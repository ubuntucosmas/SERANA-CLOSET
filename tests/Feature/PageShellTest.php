<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every page's HTML carries the route names its scripts may call. Shoppers
 * get the storefront list only; staff get theirs. Signing in as staff must
 * reload the page, or the Studio would open with the shopper's list.
 */
class PageShellTest extends TestCase
{
    use RefreshDatabase;

    private function routeNames(\Illuminate\Testing\TestResponse $response): array
    {
        preg_match('/const Ziggy=(\{.*?\});/s', $response->assertOk()->getContent(), $m);

        return array_keys(json_decode($m[1], true)['routes']);
    }

    private function page(): \Illuminate\Testing\TestResponse
    {
        \Tighten\Ziggy\BladeRouteGenerator::$generated = false; // printed once per PHP process; each request here is a new page

        return $this->get(route('contact'));
    }

    public function test_shoppers_get_storefront_routes_and_staff_get_theirs(): void
    {
        $guest = $this->routeNames($this->page());
        $this->assertContains('mpesa.stk', $guest);
        $this->assertContains('track.lookup', $guest);
        $this->assertEmpty(array_filter($guest, fn ($n) => preg_match('/^(studio|admin|pos)\.|^mpesa\.callback$/', $n)), 'no staff or server-only routes for shoppers');

        $this->actingAs(User::factory()->create(['role' => 'client']));
        $customer = $this->routeNames($this->page());
        $this->assertSame($guest, $customer);

        foreach ([Role::OWNER, Role::CASHIER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $staff = $this->routeNames($this->page());
            $this->assertContains('studio.index', $staff, $role);
            $this->assertContains('pos.sales.store', $staff, $role);
        }
    }

    public function test_signing_in_as_staff_reloads_the_page_with_staff_routes(): void
    {
        $guestVersion = app(HandleInertiaRequests::class)->version(request());
        $owner = User::factory()->create(['role' => Role::OWNER]);

        // The browser still holds the shopper's page: Inertia must do a full visit (409 + location).
        $this->actingAs($owner)->get(route('studio.index'), ['X-Inertia' => 'true', 'X-Inertia-Version' => $guestVersion])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', route('studio.index'));
        $this->get(route('studio.index'), ['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request())])->assertOk();
    }
}
