<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    /**
     * Get current user's activity logs.
     */
    public function mine(Request $request)
    {
        $userId = $request->user()->id;
        
        $activities = ActivityLog::with('user')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($activities);
    }

    /**
     * Get all activity logs (admin only).
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with('user');

        // Filter by user if specified
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by action if specified
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        // Filter by model if specified
        if ($request->has('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search in description
        if ($request->has('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $activities = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json($activities);
    }

    

    /**
     * Get user activity summary.
     */
    public function userSummary(Request $request, $userId)
    {
        $currentUser = $request->user();
        
        // Check permissions
        if ($currentUser->id != $userId && 
            !in_array($currentUser->role, ['admin_ga', 'admin_ga_manager', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($userId);

        $activities = ActivityLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $summary = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'total_activities' => $activities->count(),
            'last_activity' => $activities->first()?->created_at,
            'activities_by_action' => $activities->groupBy('action')->map->count(),
            'recent_activities' => $activities->take(20),
        ];

        return response()->json($summary);
    }

    /**
     * Clear old activity logs (admin only).
     */
    public function clearOld(Request $request)
    {
        $days = $request->input('days', 90); // Default to 90 days
        
        $deleted = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'message' => "Cleared {$deleted} old activity logs",
            'deleted_count' => $deleted
        ]);
    }
}
