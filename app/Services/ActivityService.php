<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Todo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityService
{
    /**
     * Log user activity.
     */
    public static function log(
        int $userId,
        string $action,
        string $description,
        ?string $modelType = null,
        ?int $modelId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null
    ): ActivityLog {
        $ipAddress = null;
        $userAgent = null;
        $routeName = null;
        $method = null;

        if ($request) {
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $routeName = $request->route()?->getName();
            $method = $request->method();
        }

        return ActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'route_name' => $routeName,
            'method' => $method,
        ]);
    }

    /**
     * Log aggregate creation for routine todos (single entry per batch).
     */
    public static function logCreateRoutineBatch(int $userId, array $summary, ?Request $request = null): ActivityLog
    {
        $title = $summary['title'] ?? '-';
        $interval = $summary['recurrence_interval'] ?? 1;
        $unit = $summary['recurrence_unit'] ?? 'day';
        $userCount = $summary['user_count'] ?? null;
        $occurrenceCount = $summary['occurrence_count'] ?? null;

        $parts = [];
        $parts[] = "Created Routine '{$title}'";
        $parts[] = "({$interval} {$unit})";
        if ($userCount !== null) $parts[] = "for {$userCount} user(s)";
        if ($occurrenceCount !== null) $parts[] = "{$occurrenceCount} occurrence(s)";
        $description = implode(' ', $parts);

        return self::log(
            $userId,
            'create_routine_batch',
            $description,
            null,
            null,
            null,
            $summary,
            $request
        );
    }

    /**
     * Log todo status transitions and actions.
     */
    public static function logTodoStart(Todo $todo, int $userId, ?Request $request = null): ActivityLog
    {
        return self::log(
            $userId,
            'start_todo',
            "Started Todo #{$todo->id} ({$todo->title})",
            get_class($todo),
            $todo->id,
            null,
            ['status' => $todo->status, 'started_at' => $todo->started_at],
            $request
        );
    }

    public static function logTodoHold(Todo $todo, int $userId, ?Request $request = null): ActivityLog
    {
        return self::log(
            $userId,
            'hold_todo',
            "Put On Hold Todo #{$todo->id} ({$todo->title})",
            get_class($todo),
            $todo->id,
            null,
            ['status' => $todo->status, 'hold_note' => $todo->hold_note],
            $request
        );
    }

    public static function logTodoComplete(Todo $todo, int $userId, ?Request $request = null): ActivityLog
    {
        return self::log(
            $userId,
            'complete_todo',
            "Completed Todo #{$todo->id} ({$todo->title})",
            get_class($todo),
            $todo->id,
            null,
            ['status' => $todo->status, 'submitted_at' => $todo->submitted_at],
            $request
        );
    }

    /**
     * Log model creation.
     */
    public static function logCreate(Model $model, int $userId, ?Request $request = null): ActivityLog
    {
        $modelName = class_basename($model);
        $description = "Created {$modelName} #{$model->id}";

        return self::log(
            $userId,
            'create',
            $description,
            get_class($model),
            $model->id,
            null,
            $model->toArray(),
            $request
        );
    }

    /**
     * Log model update.
     */
    public static function logUpdate(Model $model, int $userId, array $oldValues, ?Request $request = null): ActivityLog
    {
        $modelName = class_basename($model);
        $description = "Updated {$modelName} #{$model->id}";

        return self::log(
            $userId,
            'update',
            $description,
            get_class($model),
            $model->id,
            $oldValues,
            $model->toArray(),
            $request
        );
    }

    /**
     * Log meeting-specific actions with detailed descriptions.
     */
    public static function logMeetingUpdate(Model $model, int $userId, array $oldValues, ?Request $request = null): ActivityLog
    {
        $newValues = $model->toArray();
        $action = 'update';
        $description = "Updated Meeting #{$model->id}";
        
        // Detect specific meeting actions based on changes
        // Priority: Check route name first, then field changes
        
        $routeName = $request?->route()?->getName();
        
        // Check for force start (status changed to ongoing via force-start route)
        if ($routeName && str_contains($routeName, 'force-start')) {
            $action = 'force_start_meeting';
            $description = "Paksa Mulai Rapat #{$model->id}";
        }
        // Check for force end (status changed to force_ended)
        elseif (isset($newValues['status']) && $newValues['status'] === 'force_ended' && 
                isset($oldValues['status']) && $oldValues['status'] !== 'force_ended') {
            $action = 'force_end_meeting';
            $description = "Paksa Berhenti Rapat #{$model->id}";
        }
        // Check for cancel (status changed to canceled)
        elseif (isset($newValues['status']) && $newValues['status'] === 'canceled' && 
                isset($oldValues['status']) && $oldValues['status'] !== 'canceled') {
            $action = 'cancel_meeting';
            $description = "Membatalkan Rapat #{$model->id}";
        }
        // Check for GA approval/rejection (prioritize over GA Manager if both changed)
        elseif (isset($newValues['ga_check_status']) && isset($oldValues['ga_check_status']) && 
                $newValues['ga_check_status'] !== $oldValues['ga_check_status']) {
            if ($newValues['ga_check_status'] === 'approved') {
                $action = 'ga_approve_meeting';
                $description = "GA Menyetujui Rapat #{$model->id}";
            } elseif ($newValues['ga_check_status'] === 'rejected') {
                $action = 'ga_reject_meeting';
                $description = "GA Menolak Rapat #{$model->id}";
            }
        }
        // Check for GA Manager approval/rejection
        elseif (isset($newValues['ga_manager_check_status']) && isset($oldValues['ga_manager_check_status']) && 
                $newValues['ga_manager_check_status'] !== $oldValues['ga_manager_check_status']) {
            if ($newValues['ga_manager_check_status'] === 'approved') {
                $action = 'ga_manager_approve_meeting';
                $description = "GA Manager Menyetujui Rapat #{$model->id}";
            } elseif ($newValues['ga_manager_check_status'] === 'rejected') {
                $action = 'ga_manager_reject_meeting';
                $description = "GA Manager Menolak Rapat #{$model->id}";
            }
        }

        return self::log(
            $userId,
            $action,
            $description,
            get_class($model),
            $model->id,
            $oldValues,
            $newValues,
            $request
        );
    }

    /**
     * Log model deletion.
     */
    public static function logDelete(Model $model, int $userId, ?Request $request = null): ActivityLog
    {
        $modelName = class_basename($model);
        $description = "Deleted {$modelName} #{$model->id}";

        return self::log(
            $userId,
            'delete',
            $description,
            get_class($model),
            $model->id,
            $model->toArray(),
            null,
            $request
        );
    }

    /**
     * Log user login.
     */
    public static function logLogin(User $user, ?Request $request = null): ActivityLog
    {
        return self::log(
            $user->id,
            'login',
            "User {$user->name} logged in",
            null,
            null,
            null,
            null,
            $request
        );
    }

    /**
     * Log user logout.
     */
    public static function logLogout(User $user, ?Request $request = null): ActivityLog
    {
        return self::log(
            $user->id,
            'logout',
            "User {$user->name} logged out",
            null,
            null,
            null,
            null,
            $request
        );
    }

    /**
     * Log failed login attempt.
     */
    public static function logFailedLogin(string $email, ?Request $request = null): ActivityLog
    {
        $ipAddress = $request ? $request->ip() : null;
        $userAgent = $request ? $request->userAgent() : null;

        return ActivityLog::create([
            'user_id' => null, // No user for failed login
            'action' => 'failed_login',
            'description' => "Failed login attempt for email: {$email}",
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'route_name' => $request?->route()?->getName(),
            'method' => $request?->method(),
        ]);
    }

    /**
     * Log data export with richer context.
     *
     * Example description: "Ekspor Aset (Excel) — Menu: Admin > Manajemen Aset"
     */
    public static function logExport(int $userId, string $featureLabel, string $format, ?string $menuPath = null, ?Request $request = null): ActivityLog
    {
        $formatUpper = strtoupper($format);
        $menuSuffix = $menuPath ? " — Menu: {$menuPath}" : '';
        $description = "Ekspor {$featureLabel} ({$formatUpper}){$menuSuffix}";

        return self::log(
            $userId,
            'export',
            $description,
            null,
            null,
            null,
            null,
            $request
        );
    }

    /**
     * Log data import.
     */
    public static function logImport(int $userId, string $importType, int $recordCount, ?Request $request = null): ActivityLog
    {
        return self::log(
            $userId,
            'import',
            "Imported {$recordCount} {$importType} records",
            null,
            null,
            null,
            null,
            $request
        );
    }

    /**
     * Get activity statistics for a user.
     */
    public static function getUserStats(int $userId, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $stats = ActivityLog::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_activities,
                COUNT(CASE WHEN action = "create" THEN 1 END) as creates,
                COUNT(CASE WHEN action = "update" THEN 1 END) as updates,
                COUNT(CASE WHEN action = "delete" THEN 1 END) as deletes,
                COUNT(CASE WHEN action = "login" THEN 1 END) as logins,
                COUNT(CASE WHEN action = "export" THEN 1 END) as exports
            ')
            ->first();

        return [
            'total_activities' => $stats->total_activities ?? 0,
            'creates' => $stats->creates ?? 0,
            'updates' => $stats->updates ?? 0,
            'deletes' => $stats->deletes ?? 0,
            'logins' => $stats->logins ?? 0,
            'exports' => $stats->exports ?? 0,
        ];
    }

    /**
     * Get system-wide activity statistics (admin only).
     */
    public static function getSystemStats(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $stats = ActivityLog::where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_activities,
                COUNT(DISTINCT user_id) as active_users,
                COUNT(CASE WHEN action = "login" THEN 1 END) as total_logins,
                COUNT(CASE WHEN action = "failed_login" THEN 1 END) as failed_logins
            ')
            ->first();

        return [
            'total_activities' => $stats->total_activities ?? 0,
            'active_users' => $stats->active_users ?? 0,
            'total_logins' => $stats->total_logins ?? 0,
            'failed_logins' => $stats->failed_logins ?? 0,
        ];
    }
}






