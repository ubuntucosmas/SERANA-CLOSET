<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
          // Primary Admin (.closet)
        User::updateOrCreate(
            ['email' => 'admin@serana.closet'],
            [
                'name' => 'Serana Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'invitation_code' => 'SERANA-ADMIN-001'
            ]
        );

        // Legacy Admin (.com) - Prompted to Admin for stability
        User::updateOrCreate(
            ['email' => 'admin@serana.com'],
            [
                'name' => 'Serana Principal',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'invitation_code' => 'SERANA-ADMIN-002'
            ]
        );

        Log::info('AdminSeeder: Multi-Account Authority Synchronization Complete.');
    }
}
