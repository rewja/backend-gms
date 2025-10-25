<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use App\Models\Todo;
use App\Models\RequestItem;
use App\Models\Asset;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            $today = Carbon::today('Asia/Jakarta');
            $yesterday = $today->copy()->subDay();
            $thisWeek = $today->copy()->startOfWeek();
            $thisMonth = $today->copy()->startOfMonth();
            $thisYear = $today->copy()->startOfYear();

            // Visitor statistics
            $visitorStats = [
                'total_today' => Visitor::whereDate('created_at', $today)->count(),
                'total_yesterday' => Visitor::whereDate('created_at', $yesterday)->count(),
                'total_this_week' => Visitor::where('created_at', '>=', $thisWeek)->count(),
                'total_this_month' => Visitor::where('created_at', '>=', $thisMonth)->count(),
                'total_this_year' => Visitor::where('created_at', '>=', $thisYear)->count(),
                'pending' => Visitor::where('status', 'pending')->count(),
                'checked_in' => Visitor::where('status', 'checked_in')->count(),
                'checked_out' => Visitor::where('status', 'checked_out')->count(),
            ];

            // Todo statistics
            $todoStats = [
                'total_today' => Todo::whereDate('created_at', $today)->count(),
                'completed_today' => Todo::whereDate('updated_at', $today)
                    ->where('status', 'completed')->count(),
                'in_progress' => Todo::where('status', 'in_progress')->count(),
                'pending' => Todo::where('status', 'not_started')->count(),
                'completed' => Todo::where('status', 'completed')->count(),
            ];

            // Request statistics
            $requestStats = [
                'total_today' => RequestItem::whereDate('created_at', $today)->count(),
                'pending' => RequestItem::where('status', 'pending')->count(),
                'approved' => RequestItem::where('status', 'approved')->count(),
                'rejected' => RequestItem::where('status', 'rejected')->count(),
                'completed' => RequestItem::where('status', 'completed')->count(),
            ];

            // Asset statistics
            $assetStats = [
                'total' => Asset::count(),
                'active' => Asset::where('status', 'active')->count(),
                'maintenance' => Asset::where('status', 'maintenance')->count(),
                'inactive' => Asset::where('status', 'inactive')->count(),
            ];

            // Meeting statistics
            $meetingStats = [
                'total_today' => Meeting::whereDate('start_time', $today)->count(),
                'ongoing' => Meeting::where('status', 'ongoing')->count(),
                'scheduled' => Meeting::where('status', 'scheduled')->count(),
                'completed' => Meeting::where('status', 'completed')->count(),
            ];

            // User statistics
            $userStats = [
                'total' => User::count(),
                'active' => User::count(), // All users are considered active since there's no status column
                'inactive' => 0, // No inactive users since there's no status column
            ];

            // Recent activities (last 7 days)
            $recentActivities = [
                'visitors' => Visitor::where('created_at', '>=', $today->copy()->subDays(7))
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'name', 'meet_with', 'status', 'created_at']),
                'todos' => Todo::where('created_at', '>=', $today->copy()->subDays(7))
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'title', 'status', 'created_at']),
                'requests' => RequestItem::where('created_at', '>=', $today->copy()->subDays(7))
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'item_name', 'status', 'created_at']),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'visitors' => $visitorStats,
                    'todos' => $todoStats,
                    'requests' => $requestStats,
                    'assets' => $assetStats,
                    'meetings' => $meetingStats,
                    'users' => $userStats,
                    'recent_activities' => $recentActivities,
                ],
                'generated_at' => Carbon::now('Asia/Jakarta')->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

