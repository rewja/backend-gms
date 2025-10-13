<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class FinalRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'department' => 'IT',
                'position' => 'Super Administrator',
            ]
        );

        // Create Admin (GA Manager)
        User::updateOrCreate(
            ['email' => 'gamanager@example.com'],
            [
                'name' => 'GA Manager',
                'password' => Hash::make('password'),
                'role' => 'admin_ga_manager',
                'department' => 'General Affairs',
                'position' => 'GA Manager',
            ]
        );

        // Create Admin (GA)
        User::updateOrCreate(
            ['email' => 'gaadmin@example.com'],
            [
                'name' => 'GA Admin',
                'password' => Hash::make('password'),
                'role' => 'admin_ga',
                'department' => 'General Affairs',
                'position' => 'GA Administrator',
            ]
        );

        // Create Procurement
        User::updateOrCreate(
            ['email' => 'procurement@example.com'],
            [
                'name' => 'Procurement Staff',
                'password' => Hash::make('password'),
                'role' => 'procurement',
                'department' => 'Procurement',
                'position' => 'Procurement Staff',
            ]
        );

        // Create regular users with different categories
        User::updateOrCreate(
            ['email' => 'ob@example.com'],
            [
                'name' => 'OB User',
                'password' => Hash::make('password'),
                'role' => 'user',
                'category' => 'ob',
                'department' => 'General Affairs',
                'position' => 'Office Boy',
            ]
        );

        User::updateOrCreate(
            ['email' => 'driver@example.com'],
            [
                'name' => 'Driver User',
                'password' => Hash::make('password'),
                'role' => 'user',
                'category' => 'driver',
                'department' => 'General Affairs',
                'position' => 'Driver',
            ]
        );

        User::updateOrCreate(
            ['email' => 'security@example.com'],
            [
                'name' => 'Security User',
                'password' => Hash::make('password'),
                'role' => 'user',
                'category' => 'security',
                'department' => 'General Affairs',
                'position' => 'Security',
            ]
        );

        User::updateOrCreate(
            ['email' => 'magang@example.com'],
            [
                'name' => 'Magang User',
                'password' => Hash::make('password'),
                'role' => 'user',
                'category' => 'magang_pkl',
                'department' => 'General Affairs',
                'position' => 'Intern',
            ]
        );

        echo "Final role seeder completed!\n";
        echo "Created users with final role structure:\n";
        echo "- Super Admin (superadmin@example.com)\n";
        echo "- GA Manager (gamanager@example.com)\n";
        echo "- GA Admin (gaadmin@example.com)\n";
        echo "- Procurement Staff (procurement@example.com)\n";
        echo "- OB User (ob@example.com)\n";
        echo "- Driver User (driver@example.com)\n";
        echo "- Security User (security@example.com)\n";
        echo "- Magang User (magang@example.com)\n";
    }
}
