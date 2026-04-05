<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    public function test_admin_can_access_studio()
    {
        $admin = User::where('role', 'admin')->first();
        
        $response = $this->actingAs($admin)->get('/studio');

        $response->assertStatus(200);
    }

    public function test_client_cannot_access_studio()
    {
        $client = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($client)->get('/studio');

        $response->assertRedirect('/');
    }
}
