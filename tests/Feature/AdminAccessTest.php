<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_access_studio()
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $response = $this->actingAs($owner)->get('/studio');

        $response->assertStatus(200);
    }

    public function test_client_cannot_access_studio()
    {
        $client = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($client)->get('/studio');

        $response->assertRedirect('/');
    }
}
