<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ActivityController extends Controller
{
    /**
     * Get current user's activity logs.
     */
    public function mine(Request $request)
    {
        $userId = $request->user()->id;
        
        $query = ActivityLog::with('user')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        // Default: hide login/logout unless explicitly requested
        if (!$request->has('include_auth') || !$request->boolean('include_auth')) {
            $query->whereNotIn('action', ['login', 'logout']);
        }

        // Apply filters
        $this->applyFilters($query, $request);

        $activities = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $activities,
            'message' => 'Personal activity logs retrieved successfully'
        ]);
    }

    /**
     * Get all activity logs (admin only).
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with('user');

        // Default: hide login/logout unless explicitly requested
        if (!$request->has('include_auth') || !$request->boolean('include_auth')) {
            $query->whereNotIn('action', ['login', 'logout']);
        }

        // Apply filters
        $this->applyFilters($query, $request);

        $activities = $query->orderBy('created_at', 'desc')
                           ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $activities,
            'message' => 'Activity logs retrieved successfully'
        ]);
    }

    /**
     * Get activity statistics.
     */
    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        $isAdmin = in_array($request->user()->role, ['admin', 'admin_ga', 'admin_ga_manager', 'super_admin']);

        // Cache stats for 5 minutes
        $cacheKey = 'activity_stats_' . $userId . '_' . ($isAdmin ? 'admin' : 'user');
        
        $stats = Cache::remember($cacheKey, 300, function() use ($userId, $isAdmin) {
            return $this->calculateStats($userId, $isAdmin);
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Activity statistics retrieved successfully'
        ]);
    }

    /**
     * Get user activity summary.
     */
    public function userSummary(Request $request, $userId)
    {
        $currentUser = $request->user();
        
        // Check permissions
        if ($currentUser->id != $userId && 
            !in_array($currentUser->role, ['admin', 'admin_ga', 'admin_ga_manager', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
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

        return response()->json([
            'success' => true,
            'data' => $summary,
            'message' => 'User activity summary retrieved successfully'
        ]);
    }

    /**
     * Export activity logs.
     */
    public function export(Request $request)
    {
        $userId = $request->user()->id;
        $isAdmin = in_array($request->user()->role, ['admin', 'admin_ga', 'admin_ga_manager', 'super_admin']);
        
        $query = ActivityLog::with('user');
        
        // If not admin, only export own activities
        if (!$isAdmin) {
            $query->where('user_id', $userId);
        }
        
        // Apply filters
        $this->applyFilters($query, $request);
        
        $activities = $query->orderBy('created_at', 'desc')->get();
        
        // Log the export activity
        ActivityService::logExport($userId, 'activity_logs', $request);
        
        return response()->json([
            'success' => true,
            'data' => $activities,
            'message' => 'Activity logs exported successfully'
        ]);
    }

    /**
     * Clear old activity logs (admin only).
     */
    public function clearOld(Request $request)
    {
        $days = $request->input('days', 90); // Default to 90 days
        
        $deleted = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'success' => true,
            'message' => "Cleared {$deleted} old activity logs",
            'deleted_count' => $deleted
        ]);
    }

    /**
     * Apply filters to the query.
     */
    private function applyFilters($query, Request $request)
    {
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

        // Filter by IP address
        if ($request->has('ip_address')) {
            $query->where('ip_address', 'like', '%' . $request->ip_address . '%');
        }
    }

    /**
     * Calculate activity statistics.
     */
    private function calculateStats($userId, $isAdmin)
    {
        // Base query for filtering
        $baseQuery = ActivityLog::query();
        
        // If not admin, only show their own activities
        if (!$isAdmin) {
            $baseQuery->where('user_id', $userId);
        }

        // Daily activities for the last 30 days
        $dailyQuery = clone $baseQuery;
        $daily = $dailyQuery->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at) DESC')
            ->get();

        // Activities by action
        $byActionQuery = clone $baseQuery;
        $byAction = $byActionQuery->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->orderBy('total', 'desc')
            ->get();

        // Activities by user (if admin)
        $byUser = [];
        if ($isAdmin) {
            $byUser = ActivityLog::with('user')
                ->selectRaw('user_id, COUNT(*) as total')
                ->groupBy('user_id')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get();
        }

        // Recent activities
        $recentQuery = clone $baseQuery;
        $recent = $recentQuery->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // System stats (admin only)
        $systemStats = [];
        if ($isAdmin) {
            $systemStats = ActivityService::getSystemStats(30);
        }

        // User stats
        $userStats = ActivityService::getUserStats($userId, 30);

        return [
            'daily' => $daily,
            'by_action' => $byAction,
            'by_user' => $byUser,
            'recent' => $recent,
            'system_stats' => $systemStats,
            'user_stats' => $userStats,
        ];
    }
}

