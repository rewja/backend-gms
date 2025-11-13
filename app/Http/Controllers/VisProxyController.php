<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class VisProxyController extends Controller
{
    public function visitors(Request $request)
    {
        $conn = $this->pickConnection();
        $dateFilter = $request->query('date_filter', 'today');
        $location = $request->query('location');
        $status = $request->query('status');
        $search = $request->query('search');

        $query = DB::connection($conn)->table('visitors')->whereNull('deleted_at');

        switch ($dateFilter) {
            case 'today':
                $query->whereDate('created_at', now()->toDateString());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
        }

        if ($location && $location !== 'all') {
            $query->where('location_code', $location);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('visitor_name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('phone_number', 'like', "%$search%")
                  ->orWhere('company_name', 'like', "%$search%")
                  ->orWhere('id_number', 'like', "%$search%")
                  ->orWhere('qr_code', 'like', "%$search%");
            });
        }

        $visitors = $query->orderBy('created_at', 'desc')->limit(100)->get();
        return response()->json(['success' => true, 'data' => $visitors]);
    }

    public function stats(Request $request)
    {
        try {
            $conn = $this->pickConnection();
            // Stats tidak terpengaruh filter search dan status, hanya location dan date (untuk Check In/Out saja)
            $location = $request->query('location', 'all');
            $dateFilter = $request->query('date_filter', 'today');

            $base = DB::connection($conn)->table('visitors')->whereNull('deleted_at');
            if ($location !== 'all' && $location) {
                $base->where('location_code', $location);
            }

            // Apply date filter untuk Check In dan Check Out saja
            $dateFilteredBase = clone $base;
            switch ($dateFilter) {
                case 'today':
                    $dateFilteredBase->whereDate('created_at', now()->toDateString());
                    break;
                case 'week':
                    $dateFilteredBase->whereBetween('created_at', [
                        now()->subWeek()->startOfDay(),
                        now()->endOfDay()
                    ]);
                    break;
                case 'month':
                    $dateFilteredBase->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year);
                    break;
                case 'year':
                    $dateFilteredBase->whereYear('created_at', now()->year);
                    break;
                default:
                    // 'today' as default
                    $dateFilteredBase->whereDate('created_at', now()->toDateString());
            }

            // Total Tahun Ini - selalu tahun ini (tidak terpengaruh date filter, hanya terpengaruh location)
            $totalYearBase = DB::connection($conn)->table('visitors')->whereNull('deleted_at');
            if ($location !== 'all' && $location) {
                $totalYearBase->where('location_code', $location);
            }
            $totalYear = $totalYearBase->whereYear('created_at', now()->year)->count();

            // Total Bulan Ini - selalu bulan ini (tidak terpengaruh date filter, hanya terpengaruh location)
            $totalMonthBase = DB::connection($conn)->table('visitors')->whereNull('deleted_at');
            if ($location !== 'all' && $location) {
                $totalMonthBase->where('location_code', $location);
            }
            $totalMonth = $totalMonthBase->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            // Total Minggu Ini - selalu minggu ini (tidak terpengaruh date filter, hanya terpengaruh location)
            $totalWeekBase = DB::connection($conn)->table('visitors')->whereNull('deleted_at');
            if ($location !== 'all' && $location) {
                $totalWeekBase->where('location_code', $location);
            }
            $startOfWeek = now()->copy()->startOfWeek()->startOfDay();
            $endOfWeek = now()->copy()->endOfWeek()->endOfDay();
            $totalWeek = $totalWeekBase->whereBetween('created_at', [
                $startOfWeek->format('Y-m-d H:i:s'),
                $endOfWeek->format('Y-m-d H:i:s')
            ])->count();

            // Total Hari Ini - selalu hari ini (tidak terpengaruh date filter, hanya terpengaruh location)
            $totalTodayBase = DB::connection($conn)->table('visitors')->whereNull('deleted_at');
            if ($location !== 'all' && $location) {
                $totalTodayBase->where('location_code', $location);
            }
            $totalToday = $totalTodayBase->whereDate('created_at', now()->toDateString())->count();

            // Check In - terpengaruh date filter dan location
            $checkedIn = (clone $dateFilteredBase)->where('status', 'checked_in')->count();

            // Check Out - terpengaruh date filter dan location
            $checkedOut = (clone $dateFilteredBase)->where('status', 'checked_out')->count();

            $result = [
                'success' => true,
                'data' => [
                    'totalYear' => (int)$totalYear,
                    'totalMonth' => (int)$totalMonth,
                    'totalWeek' => (int)$totalWeek,
                    'totalToday' => (int)$totalToday,
                    'checkedIn' => (int)$checkedIn,
                    'checkedOut' => (int)$checkedOut,
                ],
            ];

            // Log for debugging
            Log::info('Stats calculation result', [
                'location' => $location,
                'dateFilter' => $dateFilter,
                'stats' => $result['data'],
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Stats calculation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [
                    'totalYear' => 0,
                    'totalMonth' => 0,
                    'totalWeek' => 0,
                    'totalToday' => 0,
                    'checkedIn' => 0,
                    'checkedOut' => 0,
                ],
            ], 500);
        }
    }

    public function locations(Request $request)
    {
        // Fetch locations directly from VIS database to ensure sync
        // We fetch from database directly instead of API to avoid auth issues
        try {
            $conn = $this->pickConnection();
            $locations = DB::connection($conn)
                ->table('locations')
                ->where('is_active', true)
                ->orderBy('is_headquarter', 'desc')
                ->orderBy('name')
                ->get(['code', 'name'])
                ->map(function ($loc) {
                    return [
                        'code' => $loc->code,
                        'name' => $loc->name,
                    ];
                })
                ->toArray();

            Log::info('Fetched locations from VIS database', ['count' => count($locations)]);

            return response()->json(['success' => true, 'data' => $locations]);
        } catch (\Exception $e) {
            // Log error for debugging
            Log::error('Failed to fetch locations from VIS database: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            // KOMENTAR: Fetch dari VIS backend API (mati lampu - server tidak bisa diakses)
            // Try fetching from VIS backend API as fallback
            // try {
            //     $visBackendUrl = env('VIS_BACKEND_URL', 'http://172.15.3.141:8015');
            //     // Try public endpoint first
            //     $urls = [
            //         $visBackendUrl . '/api/v1/locations',
            //         $visBackendUrl . '/api/v1/admin/locations',
            //     ];
            //
            //     $client = new Client(['timeout' => 5, 'verify' => false]);
            //     $locations = [];
            //
            //     foreach ($urls as $url) {
            //         try {
            //             $response = $client->get($url, [
            //                 'headers' => [
            //                     'Accept' => 'application/json',
            //                 ],
            //                 'http_errors' => false,
            //             ]);
            //
            //             if ($response->getStatusCode() === 200) {
            //                 $body = $response->getBody()->getContents();
            //                 $data = json_decode($body, true);
            //
            //                 if (isset($data['data']) && is_array($data['data'])) {
            //                     foreach ($data['data'] as $location) {
            //                         if (is_array($location) && isset($location['code'])) {
            //                             $locations[] = [
            //                                 'code' => $location['code'],
            //                                 'name' => $location['name'] ?? $location['code'],
            //                             ];
            //                         }
            //                     }
            //                 } elseif (is_array($data) && isset($data[0]) && is_array($data[0])) {
            //                     foreach ($data as $location) {
            //                         if (is_array($location) && isset($location['code'])) {
            //                             $locations[] = [
            //                                 'code' => $location['code'],
            //                                 'name' => $location['name'] ?? $location['code'],
            //                             ];
            //                         }
            //                     }
            //                 }
            //
            //                 if (!empty($locations)) {
            //                     Log::info('Fetched locations from VIS API', ['url' => $url, 'count' => count($locations)]);
            //                     return response()->json(['success' => true, 'data' => $locations]);
            //                 }
            //             }
            //         } catch (\Exception $apiError) {
            //             Log::warning('Failed to fetch from ' . $url . ': ' . $apiError->getMessage());
            //             continue;
            //         }
            //     }
            // } catch (\Exception $apiError) {
            //     Log::error('All location fetch methods failed: ' . $apiError->getMessage());
            // }

            // Return empty array if database connection fails
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    public function destroy($id)
    {
        $conn = $this->pickConnection();
        DB::connection($conn)->table('visitors')->where('id', $id)->update(['deleted_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Visitor deleted']);
    }

    private function pickConnection(): string
    {
        // Use local database connection (mati lampu - server tidak bisa diakses)
        // Connect langsung ke database VIS lokal
        config(['database.connections.vis_mysql.host' => env('VIS_DB_HOST', '127.0.0.1')]);
        config(['database.connections.vis_mysql.port' => env('VIS_DB_PORT', 3306)]);
        config(['database.connections.vis_mysql.database' => env('VIS_DB_DATABASE')]);
        config(['database.connections.vis_mysql.username' => env('VIS_DB_USERNAME')]);
        config(['database.connections.vis_mysql.password' => env('VIS_DB_PASSWORD')]);

        // KOMENTAR: Koneksi ke server via tunnel (untuk saat mati lampu)
        // If tunnel env is set, prefer it; otherwise use direct host/port
        // $tunnelHost = env('VIS_TUNNEL_HOST');
        // $tunnelPort = env('VIS_TUNNEL_PORT');
        // if (!empty($tunnelHost) && !empty($tunnelPort)) {
        //     config(['database.connections.vis_mysql.host' => $tunnelHost]);
        //     config(['database.connections.vis_mysql.port' => $tunnelPort]);
        // } else {
        //     config(['database.connections.vis_mysql.host' => env('VIS_DB_HOST')]);
        //     config(['database.connections.vis_mysql.port' => env('VIS_DB_PORT', 3306)]);
        // }

        return 'vis_mysql';
    }
}
