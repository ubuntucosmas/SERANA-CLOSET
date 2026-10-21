<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\TillSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudioTillHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_reach_sessions_older_than_the_first_thirty(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $location = Location::firstOrFail();
        for ($i = 0; $i < 31; $i++) {
            TillSession::create([
                'location_id' => $location->id, 'register' => 'Till 1',
                'open_marker' => null, 'status' => 'closed', 'opened_by' => $manager->id,
                'opened_at' => now()->subDays($i + 1), 'closed_at' => now()->subDays($i + 1)->addHour(),
                'opening_float' => 0, 'expected_cash' => 0, 'counted_cash' => 0, 'variance' => 0,
            ]);
        }
        $this->actingAs($manager)->get('/studio?tab=tills')->assertInertia(fn (Assert $page) => $page
            ->where('tills.total', 31)->where('tills.page', 1)->where('tills.last_page', 2)->has('tills.sessions', 30));
        $this->get('/studio?tab=tills&till_page=2')->assertInertia(fn (Assert $page) => $page
            ->where('tills.page', 2)->has('tills.sessions', 1));
    }
}
