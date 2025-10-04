<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class NewRolesSeeder extends Seeder
{
    public function run()
    {
        // Create Super Admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'department' => 'IT',
            'position' => 'Super Administrator',
        ]);

        // Create Admin (GA Manager)
        User::create([
            'name' => 'GA Manager',
            'email' => 'gamanager@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin_ga_manager',
            'department' => 'General Affairs',
            'position' => 'GA Manager',
        ]);

        // Create Admin (GA)
        User::create([
            'name' => 'GA Admin',
            'email' => 'gaadmin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin_ga',
            'department' => 'General Affairs',
            'position' => 'GA Administrator',
        ]);

        // Create regular user with Magang/PKL category
        User::create([
            'name' => 'Magang User',
            'email' => 'magang@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'category' => 'magang_pkl',
            'department' => 'General Affairs',
            'position' => 'Intern',
        ]);

        echo "New roles seeder completed!\n";
        echo "Created users with new roles:\n";
        echo "- Super Admin (superadmin@example.com)\n";
        echo "- GA Manager (gamanager@example.com)\n";
        echo "- GA Admin (gaadmin@example.com)\n";
        echo "- Magang/PKL User (magang@example.com)\n";
    }
}
