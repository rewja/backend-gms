<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate(
            ['email' => 'ga@example.com'],
            [
                'name' => 'General Admin',
                'password' => Hash::make('password123'), // ganti sesuai kebutuhan
                'role' => 'admin',
            ]
        );

        // Procurement
        User::updateOrCreate(
            ['email' => 'procurement@example.com'],
            [
                'name' => 'Procurement Officer',
                'password' => Hash::make('password123'), // ganti sesuai kebutuhan
                'role' => 'procurement',
            ]
        );

        User::updateOrCreate(
            ['email' => 'testuser@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password123'), // ganti sesuai kebutuhan
                'role' => 'user',
            ]
        );

        // Seed meetings
        $this->call(MeetingSeeder::class);
    }
}
