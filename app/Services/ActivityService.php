<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
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
        // Debug trace to ensure logging path is executed
        try {
            \Log::info('ActivityService.log invoked', [
                'user_id' => $userId,
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
            ]);
        } catch (\Throwable $e) {
            // ignore logging errors
        }
        $ipAddress = null;
        $userAgent = null;

        if ($request) {
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
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
        ]);
    }

    /**
     * Log model creation.
     */
    public static function logCreate(Model $model, int $userId, ?Request $request = null): ActivityLog
    {
        return self::log(
            $userId,
            'create',
            "Created {$model->getTable()} #{$model->id}",
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
        return self::log(
            $userId,
            'update',
            "Updated {$model->getTable()} #{$model->id}",
            get_class($model),
            $model->id,
            $oldValues,
            $model->toArray(),
            $request
        );
    }

    /**
     * Log model deletion.
     */
    public static function logDelete(Model $model, int $userId, ?Request $request = null): ActivityLog
    {
        return self::log(
            $userId,
            'delete',
            "Deleted {$model->getTable()} #{$model->id}",
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
            "User logged in",
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
            "User logged out",
            null,
            null,
            null,
            null,
            $request
        );
    }

    /**
     * Log approval action.
     */
    public static function logApprove(Model $model, int $userId, string $description, ?Request $request = null): ActivityLog
    {
        return self::log(
            $userId,
            'approve',
            $description,
            get_class($model),
            $model->id,
            null,
            $model->toArray(),
            $request
        );
    }

    /**
     * Log rejection action.
     */
    public static function logReject(Model $model, int $userId, string $description, ?Request $request = null): ActivityLog
    {
        return self::log(
            $userId,
            'reject',
            $description,
            get_class($model),
            $model->id,
            null,
            $model->toArray(),
            $request
        );
    }
}
