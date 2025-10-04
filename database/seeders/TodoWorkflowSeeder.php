<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Todo;
use Carbon\Carbon;

class TodoWorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test users
        $user = User::firstOrCreate([
            'email' => 'user@example.com'
        ], [
            'name' => 'User',
            'password' => bcrypt('password123'),
            'role' => 'user'
        ]);

        $ga = User::firstOrCreate([
            'email' => 'ga@example.com'
        ], [
            'name' => 'GA User',
            'password' => bcrypt('password123'),
            'role' => 'admin'
        ]);

        // Clear existing todos for test users
        Todo::whereIn('user_id', [$user->id, $ga->id])->delete();

        // Create todos for each status to test the workflow
        $todos = [
            [
                'title' => 'Todo Not Started - Test Basic Function',
                'description' => 'This todo is in not_started status. User can start it.',
                'status' => 'not_started',
                'priority' => 'high',
                'todo_type' => 'rutin',
                'target_category' => 'all',
                'due_date' => Carbon::now()->addDays(1),
                'created_at' => Carbon::now()->subHours(2),
            ],
            [
                'title' => 'Todo In Progress - Test Upload Function',
                'description' => 'This todo is in_progress. User can upload evidence and submit for checking.',
                'status' => 'in_progress',
                'priority' => 'medium',
                'todo_type' => 'rutin',
                'target_category' => 'all',
                'due_date' => Carbon::now()->addDays(2),
                'started_at' => Carbon::now()->subHours(1),
                'created_at' => Carbon::now()->subHours(3),
            ],
            [
                'title' => 'Todo Checking - Test Evidence Update',
                'description' => 'This todo is checking. User can update evidence during checking phase.',
                'status' => 'checking',
                'priority' => 'low',
                'due_date' => Carbon::now()->addDays(3),
                'started_at' => Carbon::now()->subHours(2),
                'submitted_at' => Carbon::now()->subHours(1),
                'evidence_path' => 'evidence/test-evidence.jpg',
                'created_at' => Carbon::now()->subHours(4),
            ],
            [
                'title' => 'Todo Evaluating - GA Review Phase',
                'description' => 'This todo is being evaluated by GA. User cannot edit.',
                'status' => 'evaluating',
                'priority' => 'high',
                'due_date' => Carbon::now()->addDays(4),
                'started_at' => Carbon::now()->subHours(3),
                'submitted_at' => Carbon::now()->subHours(2),
                'evidence_path' => 'evidence/test-evidence-evaluating.jpg',
                'checked_by' => $ga->id,
                'checker_display' => 'GA User (ga)',
                'created_at' => Carbon::now()->subHours(5),
            ],
            [
                'title' => 'Todo Reworked - Needs Improvement',
                'description' => 'This todo needs rework. User can submit improvements.',
                'status' => 'reworked',
                'priority' => 'medium',
                'due_date' => Carbon::now()->addDays(5),
                'started_at' => Carbon::now()->subHours(4),
                'submitted_at' => Carbon::now()->subHours(3),
                'evidence_path' => 'evidence/test-evidence-reworked.jpg',
                'checked_by' => $ga->id,
                'checker_display' => 'GA User (ga)',
                'created_at' => Carbon::now()->subHours(6),
            ],
            [
                'title' => 'Todo Completed - Finished Successfully',
                'description' => 'This todo is completed. Final status.',
                'status' => 'completed',
                'priority' => 'low',
                'due_date' => Carbon::now()->addDays(6),
                'started_at' => Carbon::now()->subHours(5),
                'submitted_at' => Carbon::now()->subHours(4),
                'evidence_path' => 'evidence/test-evidence-completed.jpg',
                'checked_by' => $ga->id,
                'checker_display' => 'GA User (ga)',
                'total_work_time' => 120, // 2 hours
                'total_work_time_formatted' => '2 hours',
                'rating' => 85,
                'created_at' => Carbon::now()->subHours(7),
            ],
            [
                'title' => 'Todo with Warning Points - Performance Issue',
                'description' => 'This todo has warning points for performance issues.',
                'status' => 'completed',
                'priority' => 'high',
                'due_date' => Carbon::now()->addDays(7),
                'started_at' => Carbon::now()->subHours(6),
                'submitted_at' => Carbon::now()->subHours(5),
                'evidence_path' => 'evidence/test-evidence-warning.jpg',
                'checked_by' => $ga->id,
                'checker_display' => 'GA User (ga)',
                'total_work_time' => 180, // 3 hours
                'total_work_time_formatted' => '3 hours',
                'rating' => 60,
                'created_at' => Carbon::now()->subHours(8),
            ],
            [
                'title' => 'Todo Overdue - Past Due Date',
                'description' => 'This todo is overdue and needs attention.',
                'status' => 'in_progress',
                'priority' => 'high',
                'due_date' => Carbon::now()->subDays(1), // Overdue
                'started_at' => Carbon::now()->subHours(2),
                'created_at' => Carbon::now()->subHours(3),
            ],
            [
                'title' => 'Todo Scheduled for Tomorrow',
                'description' => 'This todo is scheduled for tomorrow.',
                'status' => 'not_started',
                'priority' => 'medium',
                'due_date' => Carbon::now()->addDays(1),
                'scheduled_date' => Carbon::now()->addDays(1),
                'target_start_at' => Carbon::now()->addDays(1)->setTime(9, 0),
                'target_end_at' => Carbon::now()->addDays(1)->setTime(17, 0),
                'created_at' => Carbon::now()->subHours(1),
            ],
            [
                'title' => 'Todo with Long Description',
                'description' => 'This is a todo with a very long description to test how the UI handles long text content. It should wrap properly and not break the layout. The description contains multiple sentences and should be displayed correctly in the todo list view.',
                'status' => 'not_started',
                'priority' => 'low',
                'todo_type' => 'rutin',
                'target_category' => 'all',
                'due_date' => Carbon::now()->addDays(3),
                'created_at' => Carbon::now()->subMinutes(30),
            ],
            [
                'title' => 'Todo On Hold - Test Hold Function',
                'description' => 'This todo is on hold. User can resume it when ready.',
                'status' => 'hold',
                'priority' => 'medium',
                'todo_type' => 'tambahan',
                'target_category' => 'ob',
                'due_date' => Carbon::now()->addDays(1),
                'started_at' => Carbon::now()->subHours(2),
                'created_at' => Carbon::now()->subHours(3),
            ]
        ];

        // Create todos for regular user
        foreach ($todos as $todoData) {
            $todoData['user_id'] = $user->id;
            Todo::create($todoData);
        }

        // Create additional todos for GA user to test admin view
        $gaTodos = [
            [
                'title' => 'GA Todo - Admin Review Task',
                'description' => 'This is a todo created by GA user for admin testing.',
                'status' => 'in_progress',
                'priority' => 'high',
                'due_date' => Carbon::now()->addDays(1),
                'started_at' => Carbon::now()->subHours(1),
                'created_at' => Carbon::now()->subHours(2),
            ],
            [
                'title' => 'GA Todo - System Maintenance',
                'description' => 'System maintenance task for admin user.',
                'status' => 'completed',
                'priority' => 'medium',
                'due_date' => Carbon::now()->addDays(2),
                'started_at' => Carbon::now()->subHours(3),
                'submitted_at' => Carbon::now()->subHours(2),
                'evidence_path' => 'evidence/ga-maintenance.jpg',
                'checked_by' => $ga->id,
                'checker_display' => 'GA User (admin)',
                'total_work_time' => 60,
                'total_work_time_formatted' => '1 hour',
                'rating' => 90,
                'created_at' => Carbon::now()->subHours(4),
            ],
            [
                'title' => 'GA Todo - User Support',
                'description' => 'Support task for helping users.',
                'status' => 'checking',
                'priority' => 'low',
                'due_date' => Carbon::now()->addDays(3),
                'started_at' => Carbon::now()->subHours(2),
                'submitted_at' => Carbon::now()->subHours(1),
                'evidence_path' => 'evidence/ga-support.jpg',
                'created_at' => Carbon::now()->subHours(3),
            ]
        ];

        foreach ($gaTodos as $todoData) {
            $todoData['user_id'] = $ga->id;
            Todo::create($todoData);
        }

        // Create some warning points for the completed todo
        $completedTodo = Todo::where('user_id', $user->id)
            ->where('title', 'Todo with Warning Points - Performance Issue')
            ->first();

        if ($completedTodo) {
            $completedTodo->warnings()->create([
                'evaluator_id' => $ga->id,
                'points' => 25,
                'level' => 'low',
                'note' => 'Performance could be improved',
            ]);
        }

        $this->command->info('Todo workflow seeder completed!');
        $this->command->info('Created 10 test todos for regular user');
        $this->command->info('Created 3 test todos for GA user');
        $this->command->info('Total: 13 todos with different statuses');
        $this->command->info('Test user: user@example.com / password123');
        $this->command->info('GA user: ga@example.com / password123');
    }
}
