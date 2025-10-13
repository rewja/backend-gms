<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Resources\TodoResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class TodoController extends Controller
{
    // Helper method to format duration
    private function formatDuration($minutes)
    {
        if ($minutes === null) {
            return null;
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        $seconds = floor(($minutes - floor($minutes)) * 60);

        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours . " hour" . ($hours > 1 ? 's' : '');
        }
        if ($remainingMinutes > 0) {
            $parts[] = $remainingMinutes . " minute" . ($remainingMinutes > 1 ? 's' : '');
        }
        if ($seconds > 0) {
            $parts[] = $seconds . " second" . ($seconds > 1 ? 's' : '');
        }

        return $parts ? implode(', ', $parts) : '0 seconds';
    }

    // Helper method to calculate automatic rating based on duration vs target
    private function calculateAutomaticRating($actualDuration, $targetDuration)
    {
        if (!$targetDuration || $targetDuration <= 0) {
            return null; // No target set, no rating
        }

        $ratio = $actualDuration / $targetDuration;
        
        if ($ratio <= 0.5) {
            // Completed in half the target time or less - excellent
            return 95;
        } elseif ($ratio <= 0.75) {
            // Completed in 75% of target time - very good
            return 85;
        } elseif ($ratio <= 1.0) {
            // Completed within target time - good
            return 75;
        } elseif ($ratio <= 1.25) {
            // Completed in 125% of target time - acceptable
            return 60;
        } elseif ($ratio <= 1.5) {
            // Completed in 150% of target time - below average
            return 45;
        } elseif ($ratio <= 2.0) {
            // Completed in 200% of target time - poor
            return 30;
        } else {
            // Completed in more than 200% of target time - very poor
            return 15;
        }
    }

    private function getDayNameId(Carbon $date): string
    {
        $map = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];
        return $map[$date->format('l')] ?? $date->format('l');
    }

    private function nextDailySequence(int $userId, Carbon $date): int
    {
        $count = Todo::where('user_id', $userId)
            ->whereDate('created_at', $date->toDateString())
            ->count();
        return $count + 1; // next number for that day
    }

    private function buildEvidenceFilename(string $userName, int $userId, string $ext, ?Carbon $at = null): string
    {
        $now = $at ? $at->copy() : Carbon::now();
        $seq = str_pad((string) $this->nextDailySequence($userId, $now), 2, '0', STR_PAD_LEFT);
        $day = $this->getDayNameId($now);
        $safeUser = preg_replace('/[^A-Za-z0-9\- ]/', '', $userName ?: 'User');
        $timePart = $now->format('Y-m-d H.i.s');
        return "ETD-{$seq}-{$safeUser}-{$day}-{$timePart}.{$ext}";
    }

    private function getEvidenceFolder(Carbon $date, string $userName = 'User'): string
    {
        $safeUser = preg_replace('/[^A-Za-z0-9\- ]/', '', $userName ?: 'User');
        return 'evidence/' . $date->format('Y') . '/' . $date->format('m') . '/' . $date->format('d') . '/' . $safeUser;
    }

    private function renameEvidenceFile(string $oldPath, string $suffix): ?string
    {
        if (!$oldPath || !Storage::disk('public')->exists($oldPath)) {
            return null;
        }

        $pathInfo = pathinfo($oldPath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';

        $newFilename = $filename . '-' . $suffix . ($extension ? '.' . $extension : '');
        $newPath = $directory . '/' . $newFilename;

        try {
            Storage::disk('public')->move($oldPath, $newPath);
            return $newPath;
        } catch (\Throwable $e) {
            Log::warning('Failed to rename evidence file', [
                'old_path' => $oldPath,
                'new_path' => $newPath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    // User: list own todos
    public function index(Request $request)
    {
        $todos = $request->user()->todos()->with('warnings')->latest()->get();
        return TodoResource::collection($todos);
    }

    // User: personal todo statistics
    public function statsUser(Request $request)
    {
        $userId = $request->user()->id;
        $daily = \DB::table('todos')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            ->where('user_id', $userId)
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at) DESC')
            ->limit(30)
            ->get();

        $driver = \DB::connection()->getDriverName();
        $monthExpr = $driver === 'mysql' ? 'DATE_FORMAT(created_at, "%Y-%m")' : 'strftime("%Y-%m", created_at)';
        $yearExpr = $driver === 'mysql' ? 'DATE_FORMAT(created_at, "%Y")'   : 'strftime("%Y", created_at)';

        $monthly = \DB::table('todos')
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->where('user_id', $userId)
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $yearly = \DB::table('todos')
            ->selectRaw("{$yearExpr} as y, COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->where('user_id', $userId)
            ->groupByRaw($yearExpr)
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        $avgDuration = \DB::table('todos')
            ->where('user_id', $userId)
            ->whereNotNull('total_work_time')
            ->where('total_work_time', '>', 0)
            ->where('total_work_time', '<', 1440) // Less than 24 hours (1440 minutes)
            ->avg('total_work_time');

        return response()->json([
            'daily' => $daily,
            'monthly' => $monthly,
            'yearly' => $yearly,
            'avg_duration_minutes' => $avgDuration ? round($avgDuration, 2) : 0,
        ]);
    }

    // GA/Admin: list all todos (optional filter by user_id)
    public function indexAll(Request $request)
    {
        $query = Todo::with(['user', 'warnings'])->latest();
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        $todos = $query->get();

        // If scoped to a single user, append warning totals meta
        if ($request->filled('user_id')) {
            $userId = (int) $request->input('user_id');
            $totals = \App\Models\TodoWarning::query()
                ->whereHas('todo', function ($q) use ($userId) { $q->where('user_id', $userId); })
                ->selectRaw('SUM(points) as total_points, SUM(CASE WHEN level="low" THEN points ELSE 0 END) as low_points, SUM(CASE WHEN level="medium" THEN points ELSE 0 END) as medium_points, SUM(CASE WHEN level="high" THEN points ELSE 0 END) as high_points')
                ->first();

            return TodoResource::collection($todos)->additional([
                'warning_totals' => [
                    'low_points' => (int) ($totals->low_points ?? 0),
                    'medium_points' => (int) ($totals->medium_points ?? 0),
                    'high_points' => (int) ($totals->high_points ?? 0),
                    'total_points' => (int) ($totals->total_points ?? 0),
                ]
            ]);
        }

        return TodoResource::collection($todos);
    }

    // Admin/GA: global todo statistics
    public function statsGlobal(Request $request)
    {
        $daily = \DB::table('todos')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at) DESC')
            ->limit(30)
            ->get();

        $driver = \DB::connection()->getDriverName();
        $monthExpr = $driver === 'mysql' ? 'DATE_FORMAT(created_at, "%Y-%m")' : 'strftime("%Y-%m", created_at)';
        $yearExpr = $driver === 'mysql' ? 'DATE_FORMAT(created_at, "%Y")'   : 'strftime("%Y", created_at)';

        $monthly = \DB::table('todos')
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $yearly = \DB::table('todos')
            ->selectRaw("{$yearExpr} as y, COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->groupByRaw($yearExpr)
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        $statusDist = \DB::table('todos')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        // Ranking users by total warning points
        $ranking = \DB::table('todo_warnings as tw')
            ->join('todos as t', 'tw.todo_id', '=', 't.id')
            ->join('users as u', 't.user_id', '=', 'u.id')
            ->selectRaw('u.id as user_id, u.name, SUM(tw.points) as points')
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('points')
            ->limit(10)
            ->get();

        return response()->json([
            'daily' => $daily,
            'monthly' => $monthly,
            'yearly' => $yearly,
            'status' => $statusDist,
            'ranking_warning_points' => $ranking,
        ]);
    }

    // GA/Admin: list todos by specific user
    public function indexByUser($userId)
    {
        $todos = Todo::with(['user', 'warnings'])->where('user_id', $userId)->latest()->get();

        $totals = \App\Models\TodoWarning::query()
            ->whereHas('todo', function ($q) use ($userId) { $q->where('user_id', $userId); })
            ->selectRaw('SUM(points) as total_points, SUM(CASE WHEN level="low" THEN points ELSE 0 END) as low_points, SUM(CASE WHEN level="medium" THEN points ELSE 0 END) as medium_points, SUM(CASE WHEN level="high" THEN points ELSE 0 END) as high_points')
            ->first();

        return TodoResource::collection($todos)->additional([
            'warning_totals' => [
                'low_points' => (int) ($totals->low_points ?? 0),
                'medium_points' => (int) ($totals->medium_points ?? 0),
                'high_points' => (int) ($totals->high_points ?? 0),
                'total_points' => (int) ($totals->total_points ?? 0),
            ]
        ]);
    }

    // Admin: create todo (only admin can create todos)
    public function store(Request $request)
    {
        // Check if user is admin
        if ($request->user()->role !== 'admin_ga') {
            return response()->json([
                'message' => 'Only admin can create todos'
            ], 403);
        }

        $data = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high',
            'due_date' => 'nullable|date|after_or_equal:today',
            'scheduled_date' => 'nullable|date|after_or_equal:today',
            'target_start_at' => 'nullable|date|after_or_equal:now',
            'target_end_at' => 'nullable|date|after:target_start_at',
            'target_duration_value' => 'nullable|integer|min:1',
            'target_duration_unit' => 'nullable|in:minutes,hours',
            'todo_type' => 'required|in:rutin,tambahan',
            'target_category' => 'required|in:all,ob,driver,security',
            'target_user_id' => 'nullable|exists:users,id', // legacy
            'selected_user_ids' => 'nullable|array',
            'selected_user_ids.*' => 'exists:users,id',
            // recurrence for rutin
            'recurrence_start_date' => 'nullable|date',
            'recurrence_interval' => 'nullable|integer|min:1',
            'recurrence_unit' => 'nullable|in:day,week,month',
            'recurrence_count' => 'nullable|integer|min:0',
            // best-practice additions
            'occurrences_per_interval' => 'nullable|integer|min:1',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|min:0|max:6',
        ]);

        // If targeting specific user, use that user_id, otherwise we'll need to create todos for all users in the category
        if (!empty($data['target_user_id'])) {
            $data['user_id'] = $data['target_user_id'];
            unset($data['target_user_id']);
            $data['status'] = 'not_started';
            $todo = Todo::create($data);
            
            return response()->json([
                'message' => 'Todo created successfully',
                'todo' => new TodoResource($todo)
            ], 201);
        } else {
            // Create todos for all users in the target category
            $users = collect();
            if (!empty($data['selected_user_ids'])) {
                $users = \App\Models\User::whereIn('id', $data['selected_user_ids'])
                    ->where('role', 'user') // only employees
                    ->get();
            } else {
                $query = \App\Models\User::query()
                    ->where('role', 'user'); // exclude admin_ga/procurement from All Categories
                if ($data['target_category'] !== 'all') {
                    // use category field on users
                    $query->where('category', $data['target_category']);
                }
                $users = $query->get();
            }

            $createdTodos = [];

            // Recurrence handling for rutin todos
            $isRoutine = ($data['todo_type'] ?? null) === 'rutin';
            $startDate = !empty($data['recurrence_start_date']) ? Carbon::parse($data['recurrence_start_date']) : Carbon::now();
            $interval = max(1, (int)($data['recurrence_interval'] ?? 1));
            $unit = $data['recurrence_unit'] ?? 'day';
            $repeatCount = (int)($data['recurrence_count'] ?? 0); // 0 = unlimited (windowed)
            $perInterval = (int)($data['occurrences_per_interval'] ?? 1);
            $daysOfWeek = isset($data['days_of_week']) && is_array($data['days_of_week']) ? array_values(array_unique(array_map('intval', $data['days_of_week']))) : [];

            foreach ($users as $user) {
                // Base payload per user
                $base = $data;
                $base['user_id'] = $user->id;
                $base['status'] = 'not_started';
                unset($base['target_user_id'], $base['selected_user_ids']);

                if ($isRoutine) {
                    // Rolling window horizon: generate within next 30 days for daily / next 8 weeks for weekly / next 3 months for monthly / next year for yearly
                    $windowStart = $startDate->copy()->startOfDay();
                    if ($unit === 'day') {
                        $windowEnd = $windowStart->copy()->addDays(30);
                        $cursor = $windowStart->copy();
                        $totalCreated = 0;
                        while ($cursor->lte($windowEnd)) {
                            $payload = $base;
                            $payload['scheduled_date'] = $cursor->toDateString();
                            // TODO: review this merge decision — prevent duplicate routine entries per (user,title,scheduled_date)
                            $exists = Todo::where('user_id', $payload['user_id'] ?? null)
                                ->whereRaw('LOWER(title) = ?', [mb_strtolower($payload['title'] ?? '')])
                                ->whereDate('scheduled_date', $payload['scheduled_date'])
                                ->exists();
                            if ($exists) {
                                // skip duplicate
                            } else {
                                $todo = Todo::create($payload);
                                $createdTodos[] = new TodoResource($todo);
                                $totalCreated++;
                            }
                            if ($repeatCount > 0 && $totalCreated >= $repeatCount) break;
                            $cursor->addDays($interval);
                        }
                    } elseif ($unit === 'week') {
                        $windowEnd = $windowStart->copy()->addWeeks(4);
                        $cursor = $windowStart->copy()->startOfWeek(Carbon::MONDAY); // Start from Monday
                        $totalCreated = 0;
                        while ($cursor->lte($windowEnd)) {
                            $weekDays = !empty($daysOfWeek) ? $daysOfWeek : [ (int)$cursor->copy()->dayOfWeek ];
                            sort($weekDays);
                            foreach ($weekDays as $dow) {
                                // Convert from frontend format to Carbon format
                                // Frontend: 0=Sunday, 1=Monday, 2=Tuesday, etc.
                                // Carbon with startOfWeek(MONDAY): 0=Monday, 1=Tuesday, 2=Wednesday, etc.
                                // So we need to adjust: if dow=0 (Sunday), it should be 6 (last day of week)
                                // if dow=1 (Monday), it should be 0 (first day of week), etc.
                                $carbonDow = $dow === 0 ? 6 : $dow - 1; // Convert Sunday=0 to 6, Monday=1 to 0, etc.
                                $date = $cursor->copy()->startOfWeek(Carbon::MONDAY)->addDays($carbonDow);
                                if ($date->lt($windowStart) || $date->gt($windowEnd)) continue;
                                $payload = $base;
                                $payload['scheduled_date'] = $date->toDateString();
                                // TODO: review this merge decision — prevent duplicate routine entries per (user,title,scheduled_date)
                                $exists = Todo::where('user_id', $payload['user_id'] ?? null)
                                    ->whereRaw('LOWER(title) = ?', [mb_strtolower($payload['title'] ?? '')])
                                    ->whereDate('scheduled_date', $payload['scheduled_date'])
                                    ->exists();
                                if ($exists) {
                                    // skip duplicate
                                } else {
                                    $todo = Todo::create($payload);
                                    $createdTodos[] = new TodoResource($todo);
                                    $totalCreated++;
                                }
                                if ($repeatCount > 0 && $totalCreated >= $repeatCount) break 2;
                            }
                            $cursor->addWeeks($interval);
                        }
                    } elseif ($unit === 'month') {
                        $windowEnd = $windowStart->copy()->addMonths(1);
                        $cursor = $windowStart->copy(); // Use actual start date, not start of month
                        $totalCreated = 0;
                        while ($cursor->lte($windowEnd)) {
                            $date = $cursor->copy();
                            $payload = $base;
                            $payload['scheduled_date'] = $date->toDateString();
                            // TODO: review this merge decision — prevent duplicate routine entries per (user,title,scheduled_date)
                            $exists = Todo::where('user_id', $payload['user_id'] ?? null)
                                ->whereRaw('LOWER(title) = ?', [mb_strtolower($payload['title'] ?? '')])
                                ->whereDate('scheduled_date', $payload['scheduled_date'])
                                ->exists();
                            if ($exists) {
                                // skip duplicate
                            } else {
                                $todo = Todo::create($payload);
                                $createdTodos[] = new TodoResource($todo);
                                $totalCreated++;
                            }
                            if ($repeatCount > 0 && $totalCreated >= $repeatCount) break;
                            $cursor->addMonths($interval);
                        }
                    } else { // year
                        $windowEnd = $windowStart->copy()->addYear();
                        $cursor = $windowStart->copy()->startOfYear();
                        $totalCreated = 0;
                        while ($cursor->lte($windowEnd)) {
                            $payload = $base;
                            $payload['scheduled_date'] = $cursor->toDateString();
                            // TODO: review this merge decision — prevent duplicate routine entries per (user,title,scheduled_date)
                            $exists = Todo::where('user_id', $payload['user_id'] ?? null)
                                ->whereRaw('LOWER(title) = ?', [mb_strtolower($payload['title'] ?? '')])
                                ->whereDate('scheduled_date', $payload['scheduled_date'])
                                ->exists();
                            if ($exists) {
                                // skip duplicate
                            } else {
                                $todo = Todo::create($payload);
                                $createdTodos[] = new TodoResource($todo);
                                $totalCreated++;
                            }
                            if ($repeatCount > 0 && $totalCreated >= $repeatCount) break;
                            $cursor->addYears($interval);
                        }
                    }
                } else {
                    // Non-routine: single todo per user
                    $todo = Todo::create($base);
                    $createdTodos[] = new TodoResource($todo);
                }
            }

            return response()->json([
                'message' => "Todos created successfully",
                'todos' => $createdTodos,
                'count' => count($createdTodos)
            ], 201);
        }
    }

    // Admin/GA: update any todo by id
    public function updateAny(Request $request, $id)
    {
        // Only admin/ga should reach here via middleware/route
        $todo = Todo::findOrFail($id);

        // Accept common updatable fields
        $data = $request->validate([
            'title' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high',
            'due_date' => 'nullable|date',
            'scheduled_date' => 'nullable|date',
            'target_start_at' => 'nullable|date',
            'target_end_at' => 'nullable|date|after:target_start_at',
            'target_duration_value' => 'nullable|integer|min:1',
            'target_duration_unit' => 'nullable|in:minutes,hours',
            'todo_type' => 'nullable|in:rutin,tambahan',
            'target_category' => 'nullable|in:all,ob,driver,security',
            // routine definition (if admin wants to adjust definition fields stored with each row)
            'recurrence_start_date' => 'nullable|date',
            'recurrence_interval' => 'nullable|integer|min:1',
            'recurrence_unit' => 'nullable|in:day,week,month',
            'recurrence_count' => 'nullable|integer|min:0',
            'occurrences_per_interval' => 'nullable|integer|min:1',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|min:0|max:6',
        ]);

        // Prevent illegal status rewrites here; status transitions have dedicated endpoints
        unset($data['status']);

        foreach ($data as $key => $val) {
            $todo->$key = $val;
        }

        $todo->save();

        return response()->json([
            'message' => 'Todo updated successfully',
            'todo' => new TodoResource($todo->fresh(['warnings']))
        ]);
    }

    // User: update own todo
    public function update(Request $request, $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);

        $currentStatus = $todo->status;
        // 1) After checking phase (evaluating/completed) => block any edits
        if (in_array($currentStatus, ['evaluating', 'completed'])) {
            return response()->json([
                'message' => 'Todo can no longer be edited after the checking phase'
            ], 422);
        }

        // 2) Before checking (not_started / in_progress) => allow ONLY text fields, evidence forbidden
        if (in_array($currentStatus, ['not_started', 'in_progress'])) {
            if ($request->hasFile('evidence')) {
                return response()->json([
                    'message' => 'Evidence can only be uploaded during the checking phase'
                ], 422);
            }

        $data = $request->validate([
            'title' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high',
            'due_date' => 'nullable|date|after_or_equal:today',
            'scheduled_date' => 'nullable|date|after_or_equal:today',
            'target_start_at' => 'nullable|date|after_or_equal:now',
            'target_end_at' => 'nullable|date|after:target_start_at'
        ]);

            foreach (['title','description','priority','due_date','scheduled_date','target_start_at','target_end_at'] as $key) {
                if (array_key_exists($key, $data)) {
                    $todo->$key = $data[$key];
                }
            }

            $todo->save();

            return response()->json([
                'message' => 'Todo updated successfully',
                'todo' => new TodoResource($todo->fresh())
            ]);
        }

        // 3) During checking or evaluating => allow evidence addition/replacement (text changes ignored)
        if (in_array($currentStatus, ['checking', 'evaluating'])) {
            // Evidence is mandatory for update during checking
            $hasEvidence = $request->hasFile('evidence') ||
                          (is_array($request->file('evidence')) && count(array_filter($request->file('evidence'))) > 0);

            if (!$hasEvidence) {
                return response()->json([
                    'message' => 'Evidence file is required during checking to update'
                ], 422);
            }

            // Simple validation that works with both single and array format
            $files = $request->file('evidence');
            if (!$files) {
                return response()->json([
                    'message' => 'No evidence files found'
                ], 422);
}

            $now = Carbon::now();
            $folder = $this->getEvidenceFolder($now, $request->user()->name);
            if (!Storage::disk('public')->exists($folder)) {
                Storage::disk('public')->makeDirectory($folder, 0755, true);
            }

            // Extract sequence number from original filename if exists
            $sequenceNumber = null;
            if ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
                $originalFilename = pathinfo($todo->evidence_path, PATHINFO_BASENAME);
                // Extract sequence number from filename like "ETD-03-..."
                if (preg_match('/ETD-(\d+)-/', $originalFilename, $matches)) {
                    $sequenceNumber = $matches[1];
                }
            }

            // Get sequence number for new files
            if (!$sequenceNumber) {
                $todoCreatedDate = Carbon::parse($todo->created_at);
                $sequenceNumber = str_pad((string) $this->nextDailySequence($request->user()->id, $todoCreatedDate), 2, '0', STR_PAD_LEFT);
            }

            // Handle files - support both single file and array format
            $evidenceFiles = $files;
            if (!is_array($evidenceFiles)) {
                $evidenceFiles = [$evidenceFiles];
            }

            // Filter out null files and limit to maximum 5 files
            $evidenceFiles = array_filter($evidenceFiles, function($file) {
                return $file !== null && $file->isValid();
            });
            $evidenceFiles = array_slice($evidenceFiles, 0, 5);

            if (empty($evidenceFiles)) {
                return response()->json([
                    'message' => 'No valid evidence files found'
                ], 422);
            }

            // Check maximum 5 files for new uploads
            if (count($evidenceFiles) > 5) {
                return response()->json([
                    'message' => 'Maximum 5 evidence files allowed'
                ], 422);
            }

            // IMPORTANT: User must re-upload ALL files - delete all old evidence files first
            if ($todo->evidence_paths && is_array($todo->evidence_paths)) {
                foreach ($todo->evidence_paths as $oldPath) {
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
            } elseif ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
                Storage::disk('public')->delete($todo->evidence_path);
            }

            // Store only the new files uploaded by user
            $storedPaths = [];
            $day = $this->getDayNameId($now);
            $safeUser = preg_replace('/[^A-Za-z0-9\- ]/', '', $request->user()->name ?: 'User');
            $timePart = $now->format('Y-m-d H.i.s');

            foreach ($evidenceFiles as $index => $file) {
                $ext = $file->getClientOriginalExtension();
                $filename = "ETD-{$sequenceNumber}-{$safeUser}-{$day}-{$timePart}-Updated-Checking-{$index}.{$ext}";
                $path = $file->storeAs($folder, $filename, 'public');
                $storedPaths[] = $path;
            }

            // Store the first file path as primary evidence_path and all paths in evidence_paths
            $todo->evidence_path = $storedPaths[0] ?? null;
            $todo->evidence_paths = $storedPaths;
            $todo->save();

            return response()->json([
                'message' => 'Evidence updated successfully',
                'todo' => new TodoResource($todo->fresh())
            ]);
        }

        // Fallback (should not reach)
        return response()->json([
            'message' => 'No changes applied'
        ], 200);
    }

    // User: start a todo (transition not_started -> in_progress)
    public function start(Request $request, $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);
        if (!in_array($todo->status, ['not_started', 'hold'])) {
            return response()->json(['message' => 'Invalid state transition'], 422);
        }

        // Catat waktu mulai
        $todo->update([
            'status' => 'in_progress',
            'started_at' => now()
        ]);

        return response()->json([
            'message' => 'Todo started',
            'todo' => new TodoResource($todo)
        ]);
    }

    // User: hold a todo (transition in_progress -> hold)
    public function hold(Request $request, $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);
        if ($todo->status !== 'in_progress') {
            return response()->json(['message' => 'Can only hold todos that are in progress'], 422);
        }

        $data = $request->validate([
            'hold_note' => 'required|string|max:500'
        ]);

        $todo->update([
            'status' => 'hold',
            'hold_note' => $data['hold_note']
        ]);

        return response()->json([
            'message' => 'Todo put on hold',
            'todo' => new TodoResource($todo)
        ]);
    }

    // User: complete a todo (transition in_progress -> completed)
    public function complete(Request $request, $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);
        if ($todo->status !== 'in_progress') {
            return response()->json(['message' => 'Can only complete todos that are in progress'], 422);
        }

        // Check if evidence is uploaded
        if (!$todo->evidence_path && (!$todo->evidence_paths || empty($todo->evidence_paths))) {
            return response()->json(['message' => 'Please upload evidence before completing the todo'], 422);
        }

        // Calculate work duration
        $totalMinutes = 0;
        if ($todo->started_at) {
            $totalMinutes = Carbon::parse($todo->started_at)->diffInMinutes(now());
        }

        $todo->update([
            'status' => 'completed',
            'submitted_at' => now(),
            'total_work_time' => $totalMinutes,
            'total_work_time_formatted' => $this->formatDuration($totalMinutes)
        ]);

        return response()->json([
            'message' => 'Todo completed successfully',
            'todo' => new TodoResource($todo)
        ]);
    }

    // User: submit for checking (transition in_progress -> checking)
    public function submitForChecking(Request $request, $id)
    {
        try {
            Log::info('SubmitForChecking Debug', [
                'user_id' => $request->user()->id,
                'todo_id' => $id,
                'has_evidence' => $request->hasFile('evidence'),
                'has_evidence_array' => $request->hasFile('evidence.*'),
                'all_files' => $request->allFiles(),
                'method' => $request->method()
            ]);

            $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);
            if ($todo->status !== 'in_progress') {
                return response()->json(['message' => 'Invalid state transition'], 422);
            }

            $path = null;
            $now = Carbon::now();
            $folder = $this->getEvidenceFolder($now, $request->user()->name);
            if (!Storage::disk('public')->exists($folder)) {
                Storage::disk('public')->makeDirectory($folder, 0755, true);
            }

            // Evidence is mandatory for submitForChecking
            $hasEvidence = $request->hasFile('evidence') ||
                          (is_array($request->file('evidence')) && count(array_filter($request->file('evidence'))) > 0);

            if (!$hasEvidence) {
                return response()->json([
                    'message' => 'Evidence file is required when submitting for checking'
                ], 422);
            }

            // Simple validation that works with both single and array format
            $files = $request->file('evidence');
            if (!$files) {
                return response()->json([
                    'message' => 'No evidence files found'
                ], 422);
            }

            // Extract sequence number from original filename if exists
            $sequenceNumber = null;
            if ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
                $originalFilename = pathinfo($todo->evidence_path, PATHINFO_BASENAME);
                // Extract sequence number from filename like "ETD-03-..."
                if (preg_match('/ETD-(\d+)-/', $originalFilename, $matches)) {
                    $sequenceNumber = $matches[1];
                }
                // Delete old file completely
                Storage::disk('public')->delete($todo->evidence_path);
            }

            // Get sequence number for new files
            if (!$sequenceNumber) {
                $todoCreatedDate = Carbon::parse($todo->created_at);
                $sequenceNumber = str_pad((string) $this->nextDailySequence($request->user()->id, $todoCreatedDate), 2, '0', STR_PAD_LEFT);
            }

            // Prepare filename components
            $day = $this->getDayNameId($now);
            $safeUser = preg_replace('/[^A-Za-z0-9\- ]/', '', $request->user()->name ?: 'User');
            $timePart = $now->format('Y-m-d H.i.s');

            // Handle files - support both single file and array format
            $evidenceFiles = $files;
            if (!is_array($evidenceFiles)) {
                $evidenceFiles = [$evidenceFiles];
            }

            // Filter out null files and limit to maximum 5 files
            $evidenceFiles = array_filter($evidenceFiles, function($file) {
                return $file !== null && $file->isValid();
            });
            $evidenceFiles = array_slice($evidenceFiles, 0, 5);

            if (empty($evidenceFiles)) {
                return response()->json([
                    'message' => 'No valid evidence files found'
                ], 422);
            }

            $storedPaths = [];
            foreach ($evidenceFiles as $index => $file) {
                $ext = $file->getClientOriginalExtension();
                $filename = "ETD-{$sequenceNumber}-{$safeUser}-{$day}-{$timePart}-{$index}.{$ext}";
                $path = $file->storeAs($folder, $filename, 'public');
                $storedPaths[] = $path;
            }

            // Store the first file path as the main evidence_path and all paths in evidence_paths
            $path = $storedPaths[0] ?? null;

            $payload = [
                'status' => 'checking',
                'submitted_at' => $now,
                'evidence_path' => $path,
                'evidence_paths' => $storedPaths
            ];

            if ($todo->started_at) {
                $totalMinutes = Carbon::parse($todo->started_at)->diffInMinutes($now);
                $payload['total_work_time'] = $totalMinutes;
                $payload['total_work_time_formatted'] = $this->formatDuration($totalMinutes);
            }

            $todo->update($payload);

            return response()->json([
                'message' => 'Todo submitted for checking',
                'todo' => new TodoResource($todo)
            ]);

        } catch (\Exception $e) {
            Log::error('SubmitForChecking Error', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // GA: evaluation approve -> completed
    public function evaluate(Request $request, $id)
    {
        $todo = Todo::findOrFail($id);

        $data = $request->validate([
            'action' => 'required|in:approve,rework',
            'type' => 'required|in:individual,overall',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($todo->status !== 'checking') {
            return response()->json(['message' => 'Todo is not in checking phase'], 422);
        }

        $checkerName = $request->user()->name;
        $checkerRole = $request->user()->role;
        $checkerDisplay = "{$checkerName} ({$checkerRole})";

        // Calculate total work time if not already set
        $totalMinutes = $todo->total_work_time;
        if (!$totalMinutes && $todo->started_at && $todo->submitted_at) {
            $totalMinutes = Carbon::parse($todo->started_at)->diffInMinutes(Carbon::parse($todo->submitted_at));
        }

        // Calculate target duration from new target_duration fields or fallback to old method
        $targetDuration = null;
        if ($todo->target_duration_value && $todo->target_duration_unit) {
            // Use new target_duration fields
            $targetDuration = $todo->target_duration_unit === 'hours' 
                ? $todo->target_duration_value * 60 
                : $todo->target_duration_value;
        } elseif ($todo->target_start_at && $todo->target_end_at) {
            // Fallback to old method for backward compatibility
            $targetDuration = Carbon::parse($todo->target_start_at)->diffInMinutes(Carbon::parse($todo->target_end_at));
        }

        // Calculate automatic rating based on duration vs target
        $automaticRating = null;
        if ($data['action'] === 'approve' && $totalMinutes && $targetDuration) {
            $automaticRating = $this->calculateAutomaticRating($totalMinutes, $targetDuration);
        }

        // Determine next status based on action
        $nextStatus = $data['action'] === 'approve' ? 'completed' : 'evaluating';

        $todo->update([
            'status' => $nextStatus,
            'notes' => $data['notes'] ?? $todo->notes,
            'checked_by' => $request->user()->id,
            'checker_display' => $checkerDisplay,
            'total_work_time' => $totalMinutes,
            'total_work_time_formatted' => $this->formatDuration($totalMinutes),
            'rating' => $automaticRating,
        ]);

        // Automatic warning points based on rating
        if ($automaticRating !== null && $automaticRating < 60) {
            $points = 0;
            $level = null;
            
            if ($automaticRating < 30) {
                $points = 100; // Very poor performance
                $level = 'high';
            } elseif ($automaticRating < 45) {
                $points = 75; // Poor performance
                $level = 'high';
            } elseif ($automaticRating < 60) {
                $points = 50; // Below average performance
                $level = 'medium';
            }

            if ($points > 0) {
                $todo->warnings()->create([
                    'evaluator_id' => $request->user()->id,
                    'points' => $points,
                    'level' => $level,
                    'note' => 'Automatic warning based on performance rating',
                ]);
            }
        }

        $message = $data['action'] === 'approve' 
            ? 'Todo approved and completed' 
            : 'Todo marked for rework';

        return response()->json([
            'message' => $message,
            'todo' => new TodoResource($todo)
        ]);
    }

    // User: submit improvements during evaluating status
    public function submitImprovement(Request $request, $id)
    {
        try {
            $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);

            if ($todo->status !== 'evaluating') {
                return response()->json(['message' => 'Todo is not in evaluation phase'], 422);
            }

            $path = $todo->evidence_path;
            $now = Carbon::now();
            $folder = $this->getEvidenceFolder($now, $request->user()->name);
            if (!Storage::disk('public')->exists($folder)) {
                Storage::disk('public')->makeDirectory($folder, 0755, true);
            }

            // Check if evidence files are provided
            $hasEvidence = $request->hasFile('evidence') ||
                          (is_array($request->file('evidence')) && count(array_filter($request->file('evidence'))) > 0);

            if ($hasEvidence) {
                // Simple validation that works with both single and array format
                $files = $request->file('evidence');
                if (!$files) {
                    return response()->json([
                        'message' => 'No evidence files found'
                    ], 422);
                }

                // Extract sequence number from original filename if exists
                $sequenceNumber = null;
                if ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
                    $originalFilename = pathinfo($todo->evidence_path, PATHINFO_BASENAME);
                    // Extract sequence number from filename like "ETD-03-..."
                    if (preg_match('/ETD-(\d+)-/', $originalFilename, $matches)) {
                        $sequenceNumber = $matches[1];
                    }
                }

                // Handle files - support both single file and array format
                $evidenceFiles = $files;
                if (!is_array($evidenceFiles)) {
                    $evidenceFiles = [$evidenceFiles];
                }

                // Filter out null files and limit to maximum 5 files
                $evidenceFiles = array_filter($evidenceFiles, function($file) {
                    return $file !== null && $file->isValid();
                });
                $evidenceFiles = array_slice($evidenceFiles, 0, 5);

                if (empty($evidenceFiles)) {
                    return response()->json([
                        'message' => 'No valid evidence files found'
                    ], 422);
                }

                // Check maximum 5 files for new uploads
                if (count($evidenceFiles) > 5) {
                    return response()->json([
                        'message' => 'Maximum 5 evidence files allowed'
                    ], 422);
                }

                // IMPORTANT: User must re-upload ALL files - delete all old evidence files first
                if ($todo->evidence_paths && is_array($todo->evidence_paths)) {
                    foreach ($todo->evidence_paths as $oldPath) {
                        if (Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                    }
                } elseif ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
                    Storage::disk('public')->delete($todo->evidence_path);
                }

                // Store only the new files uploaded by user
                $storedPaths = [];

                // Prepare filename components
                $day = $this->getDayNameId($now);
                $safeUser = preg_replace('/[^A-Za-z0-9\- ]/', '', $request->user()->name ?: 'User');
                $timePart = $now->format('Y-m-d H.i.s');

                foreach ($evidenceFiles as $index => $file) {
                    $ext = $file->getClientOriginalExtension();

                    // Generate filename with same sequence number but current timestamp and -Reworked suffix
                    if ($sequenceNumber) {
                        $filename = "ETD-{$sequenceNumber}-{$safeUser}-{$day}-{$timePart}-Reworked-{$index}.{$ext}";
                    } else {
                        // If no original file, use todo's created_at date for sequence number
                        $todoCreatedDate = Carbon::parse($todo->created_at);
                        $seq = str_pad((string) $this->nextDailySequence($request->user()->id, $todoCreatedDate), 2, '0', STR_PAD_LEFT);
                        $filename = "ETD-{$seq}-{$safeUser}-{$day}-{$timePart}-Reworked-{$index}.{$ext}";
                    }

                    $path = $file->storeAs($folder, $filename, 'public');
                    $storedPaths[] = $path;
                }

                $path = $storedPaths[0] ?? null;
            }

            $todo->update([
                'status' => 'checking',
                'submitted_at' => $now,
                'evidence_path' => $path,
                'evidence_paths' => $storedPaths ?? []
            ]);

            return response()->json([
                'message' => 'Improvement submitted for checking',
                'todo' => new TodoResource($todo)
            ]);
        } catch (\Exception $e) {
            Log::error('Submit Improvement Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // GA: approve improvement from evaluating status -> completed
    public function approveImprovement(Request $request, $id)
    {
        $todo = Todo::findOrFail($id);

        if ($todo->status !== 'evaluating') {
            return response()->json(['message' => 'Todo is not in evaluation phase'], 422);
        }

        $data = $request->validate([
            'notes' => 'nullable|string|max:500'
        ]);

        $checkerName = $request->user()->name;
        $checkerRole = $request->user()->role;
        $checkerDisplay = "{$checkerName} ({$checkerRole})";

        // Calculate target duration from new target_duration fields or fallback to old method
        $targetDuration = null;
        if ($todo->target_duration_value && $todo->target_duration_unit) {
            // Use new target_duration fields
            $targetDuration = $todo->target_duration_unit === 'hours' 
                ? $todo->target_duration_value * 60 
                : $todo->target_duration_value;
        } elseif ($todo->target_start_at && $todo->target_end_at) {
            // Fallback to old method for backward compatibility
            $targetDuration = Carbon::parse($todo->target_start_at)->diffInMinutes(Carbon::parse($todo->target_end_at));
        }

        // Calculate automatic rating based on duration vs target
        $automaticRating = null;
        if ($todo->total_work_time && $targetDuration) {
            $automaticRating = $this->calculateAutomaticRating($todo->total_work_time, $targetDuration);
        }

        $todo->update([
            'status' => 'completed',
            'notes' => $data['notes'] ?? $todo->notes,
            'checked_by' => $request->user()->id,
            'checker_display' => $checkerDisplay,
            'rating' => $automaticRating,
        ]);

        return response()->json([
            'message' => 'Improvement approved and todo completed',
            'todo' => new TodoResource($todo)
        ]);
    }

    // GA: overall performance evaluation
    public function evaluateOverall(Request $request, $userId)
    {
        $date = $request->input('date');
        $day = $date ? Carbon::parse($date, 'Asia/Jakarta') : Carbon::now('Asia/Jakarta');
        $startJakarta = $day->copy()->startOfDay();
        $endJakarta = $day->copy()->endOfDay();
        $startUtc = $startJakarta->copy()->timezone('UTC');
        $endUtc = $endJakarta->copy()->timezone('UTC');

        $todos = Todo::where('user_id', $userId)
            ->whereDate('submitted_at', $day->toDateString())
            ->get();

        $totalTodosToday = $todos->count();
        $completedTodosToday = $todos->where('status', 'completed')->count();
        $totalMinutes = (int) $todos->sum(function ($t) {
            return (int) ($t->total_work_time ?? 0);
        });

        // Calculate average completion time per todo
        $averageTimePerTodo = $completedTodosToday > 0 ? round($totalMinutes / $completedTodosToday, 1) : 0;

        $low = 0; $medium = 0; $high = 0; $warningsCount = 0; $warningPoints = 0;
        $warnings = \App\Models\TodoWarning::whereHas('todo', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->get();
        foreach ($warnings as $w) {
            $warningsCount++;
            $warningPoints += (int) $w->points;
            if ($w->level === 'low') $low++;
            elseif ($w->level === 'medium') $medium++;
            elseif ($w->level === 'high') $high++;
        }

        // Example performance score: 100 - clamp(sum(points)/3, 0..100)
        $scorePenalty = (int) floor($warningPoints / 3);
        if ($scorePenalty > 100) $scorePenalty = 100;
        $performanceScore = max(0, 100 - $scorePenalty);

        return response()->json([
            'message' => 'Overall todo performance evaluation (daily)',
            'user_id' => $userId,
            'date' => $day->toDateString(),
            'total_todos_today' => $totalTodosToday,
            'completed_todos_today' => $completedTodosToday,
            'total_time_formatted_today' => $this->formatDuration($totalMinutes),
            'average_time_per_todo' => $this->formatDuration($averageTimePerTodo),
            'warnings' => [
                'count' => $warningsCount,
                'sum_points' => $warningPoints,
                'breakdown' => [
                    'low' => $low,
                    'medium' => $medium,
                    'high' => $high,
                ],
            ],
            'performance_score' => $performanceScore,
        ]);
    }

    // Admin/GA: monthly leaderboard of warning points
    public function warningsLeaderboard(Request $request)
    {
        $request->validate([
            'month' => 'nullable|date_format:Y-m',
            'search' => 'nullable|string',
            'user_id' => 'nullable|integer',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $month = $request->input('month');
        $perPage = (int)($request->input('per_page', 20));
        $search = $request->input('search');
        $filterUserId = $request->input('user_id');

        $start = $month ? Carbon::createFromFormat('Y-m', $month, 'Asia/Jakarta')->startOfMonth() : now('Asia/Jakarta')->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $startUtc = $start->copy()->timezone('UTC');
        $endUtc = $end->copy()->timezone('UTC');

        $query = \App\Models\TodoWarning::query()
            ->selectRaw('users.id as user_id, users.name as user_name, users.role as user_role, SUM(todo_warnings.points) as total_points, COUNT(todo_warnings.id) as count_warnings, MAX(todo_warnings.created_at) as last_warning_at')
            ->join('todos', 'todo_warnings.todo_id', '=', 'todos.id')
            ->join('users', 'todos.user_id', '=', 'users.id')
            ->whereBetween('todo_warnings.created_at', [$startUtc, $endUtc])
            ->groupBy('users.id', 'users.name', 'users.role');

        if ($search) {
            $query->where('users.name', 'like', "%{$search}%");
        }
        if ($filterUserId) {
            $query->where('users.id', $filterUserId);
        }

        $paginator = $query->orderByDesc('total_points')->paginate($perPage)->withQueryString();

        $rankStart = ($paginator->currentPage() - 1) * $paginator->perPage();
        $data = [];
        foreach ($paginator->items() as $index => $row) {
            // Format total warning points (misal: 25/300)
            $totalPoints = (int) $row->total_points;
            $warningDisplay = "{$totalPoints}/300";

            // Format waktu terakhir mendapat peringatan
            $lastWarningAt = null;
            if ($row->last_warning_at) {
                $lastWarning = Carbon::parse($row->last_warning_at)->timezone('Asia/Jakarta');
                $dayNames = [
                    'Sunday' => 'Minggu',
                    'Monday' => 'Senin',
                    'Tuesday' => 'Selasa',
                    'Wednesday' => 'Rabu',
                    'Thursday' => 'Kamis',
                    'Friday' => 'Jumat',
                    'Saturday' => 'Sabtu'
                ];
                $dayName = $dayNames[$lastWarning->format('l')];
                $lastWarningAt = $dayName . ', ' . $lastWarning->format('d F Y H:i:s');
            }

            $data[] = [
                'rank' => $rankStart + $index + 1,
                'user_id' => $row->user_id,
                'user_name' => $row->user_name,
                'user_role' => $row->user_role,
                'warning_points' => $warningDisplay,
                'last_warning_at' => $lastWarningAt,
            ];
        }

        return response()->json([
            'month' => $start->format('Y-m'),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'data' => $data,
        ]);
    }

    // User: delete own todo
    public function destroy(Request $request, $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);

        // Rename evidence files with "Deleted" suffix instead of deleting
        if ($todo->evidence_paths && is_array($todo->evidence_paths)) {
            // Handle multiple files
            $newPaths = [];
            foreach ($todo->evidence_paths as $index => $path) {
                if (Storage::disk('public')->exists($path)) {
                    $newPath = $this->renameEvidenceFile($path, 'Deleted');
                    if ($newPath) {
                        $newPaths[] = $newPath;
                    }
                }
            }
            $todo->evidence_paths = $newPaths;
            $todo->evidence_path = $newPaths[0] ?? null;
            $todo->save(); // Save the updated paths before deleting
        } elseif ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
            // Handle single file for backward compatibility
            $newPath = $this->renameEvidenceFile($todo->evidence_path, 'Deleted');
            if ($newPath) {
                $todo->evidence_path = $newPath;
                $todo->evidence_paths = [$newPath];
                $todo->save(); // Save the updated path before deleting
            }
        }

        $todo->delete();

        return response()->json(['message' => 'Todo deleted successfully']);
    }

    // Admin/GA: delete any todo by id
    public function destroyAny(Request $request, $id)
    {
        // Only admin/ga should reach here via middleware
        $todo = Todo::findOrFail($id);

        // Rename evidence files with "Deleted" suffix instead of deleting
        if ($todo->evidence_paths && is_array($todo->evidence_paths)) {
            $newPaths = [];
            foreach ($todo->evidence_paths as $path) {
                if (Storage::disk('public')->exists($path)) {
                    $newPath = $this->renameEvidenceFile($path, 'Deleted');
                    if ($newPath) {
                        $newPaths[] = $newPath;
                    }
                }
            }
            $todo->evidence_paths = $newPaths;
            $todo->evidence_path = $newPaths[0] ?? null;
            $todo->save();
        } elseif ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
            $newPath = $this->renameEvidenceFile($todo->evidence_path, 'Deleted');
            if ($newPath) {
                $todo->evidence_path = $newPath;
                $todo->evidence_paths = [$newPath];
                $todo->save();
            }
        }

        $todo->delete();

        return response()->json(['message' => 'Todo deleted successfully']);
    }

    // Admin/GA: delete all todos in a routine group by title + recurrence signature
    public function destroyRoutineGroup(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'recurrence_interval' => 'nullable|integer',
            'recurrence_unit' => 'nullable|in:day,week,month',
            'target_category' => 'nullable|in:all,ob,driver,security',
            'user_id' => 'nullable|integer',
            'recurrence_count' => 'nullable|integer',
        ]);

        $title = $request->input('title');
        $interval = (int) ($request->input('recurrence_interval') ?? 1);
        $unit = $request->input('recurrence_unit') ?? 'day';
        $targetCategory = $request->input('target_category');
        $userId = $request->input('user_id');
        $recurrenceCount = $request->input('recurrence_count');

        $query = Todo::query()
            ->whereRaw('LOWER(title) = ?', [mb_strtolower(trim($title))])
            // Match routine tasks created under both new and legacy data
            ->where(function ($q) {
                $q->where('todo_type', 'rutin')
                  ->orWhereNotNull('recurrence_unit')
                  ->orWhereNotNull('scheduled_date');
            })
            ->where(function ($q) use ($interval, $unit) {
                $q->whereNull('recurrence_interval')->orWhere('recurrence_interval', $interval);
            })
            ->where(function ($q) use ($unit) {
                $q->whereNull('recurrence_unit')->orWhere('recurrence_unit', $unit);
            });
        if (!is_null($recurrenceCount)) {
            $query->where(function ($q) use ($recurrenceCount) {
                $q->whereNull('recurrence_count')->orWhere('recurrence_count', (int) $recurrenceCount);
            });
        }
        if ($targetCategory && $targetCategory !== 'all') {
            $query->where('target_category', $targetCategory);
        }
        if ($userId) {
            $query->where('user_id', (int) $userId);
        }
        $todos = $query->get();

        // Ultra fallback: if still nothing matched, delete by title only (case-insensitive)
        if ($todos->count() === 0) {
            $todos = Todo::query()
                ->whereRaw('LOWER(title) = ?', [mb_strtolower(trim($title))])
                ->get();
        }

        $count = 0;
        foreach ($todos as $todo) {
            // rename evidence files like individual delete
            if ($todo->evidence_paths && is_array($todo->evidence_paths)) {
                $newPaths = [];
                foreach ($todo->evidence_paths as $path) {
                    if (Storage::disk('public')->exists($path)) {
                        $newPath = $this->renameEvidenceFile($path, 'Deleted');
                        if ($newPath) $newPaths[] = $newPath;
                    }
                }
                $todo->evidence_paths = $newPaths;
                $todo->evidence_path = $newPaths[0] ?? null;
                $todo->save();
            } elseif ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
                $newPath = $this->renameEvidenceFile($todo->evidence_path, 'Deleted');
                if ($newPath) {
                    $todo->evidence_path = $newPath;
                    $todo->evidence_paths = [$newPath];
                    $todo->save();
                }
            }
            $todo->delete();
            $count++;
        }

        return response()->json([
            'message' => 'Routine group deleted',
            'deleted' => $count,
        ]);
    }

    // GA: mark todo as checked
    public function check(Request $request, $id)
    {
        $todo = Todo::findOrFail($id);

        $todo->update([
            'status' => 'checked',
            'checked_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Todo checked successfully', 'todo' => $todo]);
    }

    // GA: add note
    public function addNote(Request $request, $id)
    {
        $todo = Todo::findOrFail($id);

        $request->validate(['notes' => 'required|string']);
        $todo->update(['notes' => $request->notes]);

        return response()->json(['message' => 'Note added successfully', 'todo' => $todo]);
    }
}

