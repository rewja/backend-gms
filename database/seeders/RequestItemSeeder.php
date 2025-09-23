<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RequestItem;
use App\Models\User;

class RequestItemSeeder extends Seeder
{
    public function run()
    {
        // Get test user
        $user = User::where('email', 'testuser@example.com')->first();

        if (!$user) {
            echo "Test user not found. Please run UserSeeder first.\n";
            return;
        }

        // Create sample requests
        $requests = [
            [
                'user_id' => $user->id,
                'item_name' => 'Laptop untuk development',
                'quantity' => 1,
                'estimated_cost' => 15000000,
                'category' => 'IT Equipment',
                'reason' => 'Laptop lama sudah tidak memadai untuk development aplikasi modern',
                'status' => 'pending',
            ],
            [
                'user_id' => $user->id,
                'item_name' => 'Monitor 24 inch',
                'quantity' => 2,
                'estimated_cost' => 5000000,
                'category' => 'IT Equipment',
                'reason' => 'Monitor tambahan untuk meningkatkan produktivitas kerja',
                'status' => 'approved',
            ],
            [
                'user_id' => $user->id,
                'item_name' => 'Meja kerja ergonomis',
                'quantity' => 1,
                'estimated_cost' => 3000000,
                'category' => 'Office Furniture',
                'reason' => 'Meja kerja yang lebih nyaman untuk kesehatan tulang belakang',
                'status' => 'procurement',
            ],
            [
                'user_id' => $user->id,
                'item_name' => 'Kursi ergonomis',
                'quantity' => 1,
                'estimated_cost' => 2500000,
                'category' => 'Office Furniture',
                'reason' => 'Kursi yang mendukung postur duduk yang baik',
                'status' => 'received',
            ],
            [
                'user_id' => $user->id,
                'item_name' => 'Printer laser',
                'quantity' => 1,
                'estimated_cost' => 2000000,
                'category' => 'IT Equipment',
                'reason' => 'Printer untuk kebutuhan dokumen kantor',
                'status' => 'rejected',
            ],
        ];

        foreach ($requests as $requestData) {
            RequestItem::create($requestData);
        }

        echo "RequestItemSeeder completed!\n";
        echo "Created " . count($requests) . " sample requests for testuser@example.com\n";
    }
}
