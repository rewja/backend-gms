<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Meeting;
use App\Models\User;

class MeetingSeeder extends Seeder
{
    public function run()
    {
        // Get test user
        $user = User::where('email', 'testuser@example.com')->first();

        if (!$user) {
            echo "Test user not found. Please run UserSeeder first.\n";
            return;
        }

        // Create sample meetings
        $meetings = [
            [
                'user_id' => $user->id,
                'agenda' => 'Weekly Team Standup',
                'room_name' => 'Meeting Room A (08)',
                'start_time' => now()->addHours(2),
                'end_time' => now()->addHours(3),
                'status' => 'scheduled',
            ],
            [
                'user_id' => $user->id,
                'agenda' => 'Project Planning Session',
                'room_name' => 'Meeting Room B (08)',
                'start_time' => now()->addDays(1)->setHour(10),
                'end_time' => now()->addDays(1)->setHour(12),
                'status' => 'scheduled',
            ],
            [
                'user_id' => $user->id,
                'agenda' => 'Client Presentation',
                'room_name' => 'Meeting Room A (689)',
                'start_time' => now()->subHours(1),
                'end_time' => now()->addHours(1),
                'status' => 'ongoing',
            ],
            [
                'user_id' => $user->id,
                'agenda' => 'Monthly Review Meeting',
                'room_name' => 'Meeting Room B (689)',
                'start_time' => now()->subDays(1)->setHour(14),
                'end_time' => now()->subDays(1)->setHour(16),
                'status' => 'ended',
            ],
            [
                'user_id' => $user->id,
                'agenda' => 'Emergency Meeting',
                'room_name' => 'Meeting Room A (08)',
                'start_time' => now()->subDays(2)->setHour(9),
                'end_time' => now()->subDays(2)->setHour(10),
                'status' => 'force_ended',
            ],
        ];

        foreach ($meetings as $meetingData) {
            Meeting::create($meetingData);
        }

        echo "MeetingSeeder completed!\n";
        echo "Created " . count($meetings) . " sample meetings for testuser@example.com\n";
    }
}
