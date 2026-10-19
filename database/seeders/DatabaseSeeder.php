<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Demo seeding is restricted to local and testing environments. Provision production accounts separately.');
        }

        // 1. Create Admin Studio Account
        User::updateOrCreate(
            ['email' => 'admin@serana.com'],
            [
                'name' => 'Serana Admin',
                'password' => bcrypt('serana2026'),
                'role' => 'owner',
            ]
        );

        // 2. Create Demo Client Account
        User::updateOrCreate(
            ['email' => 'jane@example.com'],
            [
                'name' => 'Jane Couture',
                'password' => bcrypt('serana2026'),
            ]
        );

        // 3. Run Shop Inventory Seeder
        $this->call(ShopSeeder::class);

        // 4. Run Gallery Seeder for Social Proof
        $this->call(GallerySeeder::class);
    }
}
