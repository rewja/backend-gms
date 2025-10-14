<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    // List all meetings for authenticated user (or all for admin)
    public function index(Request $request)
    {
        $query = Meeting::query();
        // user_id removed for meeting-room flow; list all for admin, else public endpoints are used
        if ($request->user()->role !== 'admin_ga') {
            // No user filter since user_id column is dropped
        }
        return response()->json($query->latest()->get());
    }

    // Stats: counts per day/month/year, average duration, top rooms
    public function stats(Request $request)
    {
        $isAdmin = $request->user()->role === 'admin_ga';
        $base = \DB::table('meetings');
        if (!$isAdmin) {
            $base->where('user_id', $request->user()->id);
        }

        $daily = (clone $base)
            ->selectRaw('DATE(start_time) as date, COUNT(*) as total')
            ->groupByRaw('DATE(start_time)')
            ->orderByRaw('DATE(start_time) DESC')
            ->limit(30)
            ->get();

        $driver = \DB::connection()->getDriverName();
        $monthExpr = $driver === 'mysql' ? 'DATE_FORMAT(start_time, "%Y-%m")' : 'strftime("%Y-%m", start_time)';
        $yearExpr = $driver === 'mysql' ? 'DATE_FORMAT(start_time, "%Y")'   : 'strftime("%Y", start_time)';

        $monthly = (clone $base)
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total")
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $yearly = (clone $base)
            ->selectRaw("{$yearExpr} as y, COUNT(*) as total")
            ->groupByRaw($yearExpr)
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        if ($driver === 'mysql') {
            $avgDuration = (clone $base)
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_minutes')
                ->value('avg_minutes');
        } else {
            $avgDuration = (clone $base)
                ->selectRaw('AVG((julianday(end_time) - julianday(start_time)) * 24 * 60) as avg_minutes')
                ->value('avg_minutes');
        }

        $topRooms = (clone $base)
            ->selectRaw('room_name, COUNT(*) as total')
            ->groupBy('room_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return response()->json([
            'daily' => $daily,
            'monthly' => $monthly,
            'yearly' => $yearly,
            'avg_duration_minutes' => $avgDuration ? round($avgDuration, 2) : 0,
            'top_rooms' => $topRooms,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'room_name' => 'required|string|max:100',
            'agenda' => 'required|string',
            'start_time' => 'required|date|after_or_equal:now',
            'end_time' => 'required|date|after:start_time',
        ]);

        // user_id removed; keep booking_type for internal flows if needed
        $data['booking_type'] = 'internal';

        $meeting = Meeting::create($data);

        return response()->json(['message' => 'Meeting booked', 'meeting' => $meeting], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Meeting $meeting)
    {
        // Check if user has access to this meeting
        if ($request->user()->role !== 'admin_ga') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        return response()->json($meeting);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Meeting $meeting)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);
        $this->authorizeUser($request, $meeting);
        
        $data = $request->validate([
            'room_name' => 'required|string|max:100',
            'agenda' => 'required|string',
            'start_time' => 'required|date|after_or_equal:now',
            'end_time' => 'required|date|after:start_time',
        ]);

        $meeting->update($data);
        return response()->json(['message' => 'Meeting updated', 'meeting' => $meeting]);
    }

    public function start(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);
        $this->authorizeUser($request, $meeting);
        $meeting->update(['status' => 'ongoing']);
        return response()->json(['message' => 'Meeting started', 'meeting' => $meeting]);
    }

    public function end(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);
        $this->authorizeUser($request, $meeting);
        $meeting->update(['status' => 'ended']);
        return response()->json(['message' => 'Meeting ended', 'meeting' => $meeting]);
    }

    public function forceEnd(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);
        $meeting->update(['status' => 'force_ended']);
        return response()->json(['message' => 'Meeting force ended', 'meeting' => $meeting]);
    }

    private function authorizeUser(Request $request, Meeting $meeting): void
    {
        if ($request->user()->role !== 'admin_ga') {
            abort(response()->json(['message' => 'Unauthorized'], 403));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Meeting $meeting)
    {
        // Only creator or admin can delete
        // Note: route model binding provides $meeting
        // We will authorize using same rule as other actions
        request()->user()->role === 'admin_ga' ?: abort(response()->json(['message' => 'Unauthorized'], 403));

        $meeting->delete();
        return response()->json(['message' => 'Meeting deleted']);
    }

    // ==================== MEETING ROOM PUBLIC ENDPOINTS ====================
    
    /**
     * Get available rooms for public booking
     */
    public function getRooms()
    {
        $rooms = [
            [
                'id' => 'room-a-08',
                'name' => 'Meeting Room A (08)',
                'capacity' => 8,
                'location' => 'Floor 1',
                'amenities' => ['Projector', 'Whiteboard', 'Air Conditioning']
            ],
            [
                'id' => 'room-b-08',
                'name' => 'Meeting Room B (08)',
                'capacity' => 8,
                'location' => 'Floor 1',
                'amenities' => ['Projector', 'Whiteboard', 'Air Conditioning']
            ],
            [
                'id' => 'room-a-689',
                'name' => 'Meeting Room A (689)',
                'capacity' => 12,
                'location' => 'Floor 2',
                'amenities' => ['Projector', 'Whiteboard', 'Air Conditioning', 'Video Conference']
            ],
            [
                'id' => 'room-b-689',
                'name' => 'Meeting Room B (689)',
                'capacity' => 12,
                'location' => 'Floor 2',
                'amenities' => ['Projector', 'Whiteboard', 'Air Conditioning', 'Video Conference']
            ]
        ];

        return response()->json($rooms);
    }

    /**
     * Get public bookings (for display purposes)
     */
    public function getPublicBookings(Request $request)
    {
        $query = Meeting::query();
        
        // Filter by date if provided
        if ($request->has('date')) {
            $date = $request->get('date');
            $query->whereDate('start_time', $date);
        }
        
        // Filter by room if provided
        if ($request->has('room')) {
            $query->where('room_name', $request->get('room'));
        }

        $bookings = $query->orderBy('start_time')->get();

        return response()->json($bookings);
    }

    /**
     * Public booking endpoint (no authentication required)
     */
    public function publicBook(Request $request)
    {
        $data = $request->validate([
            'room_name' => 'required|string|max:100',
            'agenda' => 'required|string',
            'start_time' => 'required|date|after_or_equal:now',
            'end_time' => 'required|date|after:start_time',
            'organizer_name' => 'required|string|max:255',
            'organizer_email' => 'nullable|email',
            'jumlah_peserta' => 'required|integer|min:1',
            'prioritas' => 'required|string|in:reguler,vip,regular',
            'kebutuhan' => 'nullable|array',
            'makanan_detail' => 'nullable|string',
            'minuman_detail' => 'nullable|string',
        ]);

        // Handle SPK file upload
        $spkFilePath = null;
        if ($request->hasFile('spk_file')) {
            $file = $request->file('spk_file');
            
            // Validate file
            $request->validate([
                'spk_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240' // 10MB max
            ]);
            
            // Generate unique filename
            $filename = 'spk_' . time() . '_' . $file->getClientOriginalName();
            $spkFilePath = $file->storeAs('spk_files', $filename, 'public');
        } else {
            return response()->json([
                'message' => 'File SPK wajib diupload'
            ], 422);
        }

        // Check for conflicts
        $conflict = Meeting::where('room_name', $data['room_name'])
            ->where(function ($query) use ($data) {
                $query->whereBetween('start_time', [$data['start_time'], $data['end_time']])
                    ->orWhereBetween('end_time', [$data['start_time'], $data['end_time']])
                    ->orWhere(function ($q) use ($data) {
                        $q->where('start_time', '<=', $data['start_time'])
                          ->where('end_time', '>=', $data['end_time']);
                    });
            })
            ->where('status', '!=', 'canceled')
            ->first();

        if ($conflict) {
            return response()->json([
                'message' => 'Room is not available for the selected time slot',
                'conflict' => $conflict
            ], 409);
        }

        // Priority directly uses business terms now
        $normalizedPriority = strtolower($data['prioritas']) === 'regular' ? 'reguler' : strtolower($data['prioritas']);

        // Create a meeting for public booking
        $meetingData = [
            'room_name' => $data['room_name'],
            'agenda' => $data['agenda'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => 'scheduled',
            'booking_type' => 'external',
            'organizer_name' => $data['organizer_name'],
            'organizer_email' => $data['organizer_email'] ?? null,
            'jumlah_peserta' => $data['jumlah_peserta'],
            'prioritas' => $normalizedPriority,
            'spk_file_path' => $spkFilePath,
            'kebutuhan' => $data['kebutuhan'] ?? null,
            'makanan_detail' => $data['makanan_detail'] ?? null,
            'minuman_detail' => $data['minuman_detail'] ?? null,
        ];

        $meeting = Meeting::create($meetingData);

        return response()->json([
            'message' => 'Meeting room booked successfully',
            'meeting' => $meeting,
            'booking_id' => $meeting->id
        ], 201);
    }

    /**
     * Check room availability
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'room_name' => 'required|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $conflict = Meeting::where('room_name', $request->room_name)
            ->where(function ($query) use ($request) {
                $query->whereBetween('start_time', [$request->start_time, $request->end_time])
                    ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('start_time', '<=', $request->start_time)
                          ->where('end_time', '>=', $request->end_time);
                    });
            })
            ->where('status', '!=', 'canceled')
            ->first();

        return response()->json([
            'available' => !$conflict,
            'conflict' => $conflict
        ]);
    }
}
