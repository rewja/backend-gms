<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\ActivityService;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    private function resolveCategoryPrefix(string $category): string
    {
        $normalized = strtolower(trim($category));
        $map = [
            'ob equipment' => 'OBE',
            'security equipment' => 'SE',
            'driver equipment' => 'DE',
            'other' => 'OE',
            'general' => 'OE',
            'it equipment' => 'OE',
            'office furniture' => 'OE',
            'office supplies' => 'OE',
            'maintenance' => 'OE',
        ];
        $prefixCat = $map[$normalized] ?? null;
        if ($prefixCat === null) {
            if (str_contains($normalized, 'driver')) {
                return 'DE';
            }
            if (str_contains($normalized, 'security')) {
                return 'SE';
            }
            if (str_contains($normalized, 'ob ')
                || str_contains($normalized, 'ob equipment')
                || str_contains($normalized, 'operational building')
            ) {
                return 'OBE';
            }
            return 'OE';
        }
        return $prefixCat;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $assets = Asset::with(['request', 'procurement'])->latest()->get();
        return response()->json($assets);
    }

    // Admin: asset statistics
    public function stats()
    {
        $byCategory = DB::table('assets')
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->get();

        $byStatus = DB::table('assets')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'mysql' ? 'DATE_FORMAT(created_at, "%Y-%m")' : 'strftime("%Y-%m", created_at)';

        $timeline = DB::table('assets')
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total")
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        return response()->json([
            'by_category' => $byCategory,
            'by_status' => $byStatus,
            'timeline' => $timeline,
        ]);
    }

    // Generate next asset code preview for admin UI
    public function nextCode(Request $request)
    {
        $validated = $request->validate([
            'category' => 'nullable|string',
            'context' => 'nullable|in:request,addition',
            // backward-compat: accept legacy 'source'
            'source' => 'nullable|string',
        ]);
        $category = trim((string)($validated['category'] ?? $request->get('category', 'Other')));
        if ($category === '') {
            $category = 'Other';
        }
        $context = $validated['context'] ?? ($request->get('source') ? 'request' : 'addition');
        $prefixCat = $this->resolveCategoryPrefix($category);
        $code = $this->generateAssetCode($category, $context);
        $count = Asset::where('asset_code', 'like', $prefixCat . '-%')->count();
        return response()->json([
            // new keys
            'asset_code' => $code,
            'count' => $count,
            'prefix' => $prefixCat,
            // compatibility for existing frontend
            'next_code' => $code,
        ]);
    }

    // Helper to generate asset code by category and context (date + increasing suffix)
    public function generateAssetCode(string $category = 'General', string $context = 'request'): string
    {
        $prefixCat = $this->resolveCategoryPrefix($category);
        $date = now()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('mdY');
        $prefixForToday = $prefixCat . '-' . $date . '-';

        return DB::transaction(function () use ($prefixCat, $prefixForToday) {
            $catPrefix = $prefixCat . '-';
            $latestCodes = Asset::where('asset_code', 'like', $catPrefix . '%')
                ->select('asset_code')
                ->lockForUpdate()
                ->get()
                ->pluck('asset_code');

            $maxSuffix = 0;
            foreach ($latestCodes as $code) {
                $parts = explode('-', $code);
                $last = end($parts);
                $num = (int) $last;
                if ($num > $maxSuffix) {
                    $maxSuffix = $num;
                }
            }
            $next = $maxSuffix + 1;

            $code = $prefixForToday . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
            while (Asset::where('asset_code', $code)->exists()) {
                $next++;
                $code = $prefixForToday . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
            }
            return $code;
        });
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
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'color' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|in:not_received,received,needs_repair,needs_replacement',
            'supplier' => 'nullable|string|max:255',
            'purchase_cost' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'quantity' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'request_items_id' => 'nullable|exists:request_items,id',
        ]);

        // Generate asset code based on category and date sequence
        $category = $data['category'] ?? 'Other';
        $context = $request->input('request_items_id') ? 'request' : 'addition';
        $data['asset_code'] = $this->generateAssetCode($category, $context);

        // Set method based on whether it comes from request or direct input
        $requestItemsId = $request->input('request_items_id');
        $data['method'] = $requestItemsId ? 'purchasing' : 'data_input';

        // Set default status if not provided
        if (!isset($data['status'])) {
            $data['status'] = 'not_received';
        }

		$asset = Asset::create($data);


		return response()->json(['message' => 'Asset created successfully', 'asset' => $asset], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Asset $asset)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Asset $asset)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'color' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'status' => 'required|in:not_received,received,needs_repair,needs_replacement,repairing,replacing,shipping,procurement',
            'supplier' => 'nullable|string|max:255',
            'purchase_cost' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'quantity' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'received_date' => 'nullable|date',
        ]);

		$oldValues = $asset->toArray();
		$asset->update($data);


        return response()->json(['message' => 'Asset updated successfully', 'asset' => $asset]);
    }

    /**
     * Update asset status only
     */
    public function updateStatus(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:procurement,not_received,received,needs_repair,needs_replacement,repairing,replacing,shipping',
            'received_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

		$oldValues = $asset->toArray();
		$asset->update($data);


        return response()->json(['message' => 'Asset status updated', 'asset' => $asset]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
		$asset = Asset::findOrFail($id);
		$asset->delete();

        return response()->json(['message' => 'Asset deleted successfully']);
    }

    // User: list own assets
    public function mine(Request $request)
    {
        // Filter assets by the related request's owner since assets table has no user_id column
        $assets = Asset::with(['request', 'procurement'])
            ->whereHas('request', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->latest()
            ->get();
        return response()->json($assets);
    }

    // User: update asset status (received, not_received, needs_repair, needs_replacement)
    public function updateUserStatus(Request $request, $id)
    {
        $currentUser = $request->user();
        // Allow procurement/admin to update any asset; normal users only their own
        if (in_array($currentUser->role ?? '', ['admin_ga', 'admin_ga_manager', 'super_admin'])) {
            $asset = Asset::findOrFail($id);
        } else {
            $asset = Asset::whereHas('request', function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id);
                })->findOrFail($id);
        }

        // Validation: end user can set received/not_received/needs_*; procurement can also set repairing/replacing
        $allowedStatuses = ['received','not_received','needs_repair','needs_replacement','shipping','procurement'];
        if (in_array($currentUser->role ?? '', ['admin_ga', 'admin_ga_manager', 'super_admin'])) {
            $allowedStatuses = array_merge($allowedStatuses, ['repairing','replacing']);
        }

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', $allowedStatuses),
            'receipt_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'repair_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'notes' => 'nullable|string',
        ]);

        $updateData = [
            'status' => $data['status'],
            'user_notes' => $data['notes'] ?? null,
        ];

        // If marking as received by end user, receipt proof is mandatory
        if (($data['status'] ?? null) === 'received' && !$request->hasFile('receipt_proof') && !in_array($currentUser->role ?? '', ['admin_ga','admin_ga_manager','super_admin'])) {
            return response()->json(['message' => 'Receipt proof is required to mark as received'], 422);
        }

        // Handle file uploads
        if ($request->hasFile('receipt_proof')) {
            $file = $request->file('receipt_proof');
            $filename = 'receipt_' . $asset->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('asset-proofs', $filename, 'public');
            $updateData['receipt_proof_path'] = $path;
        }

        if ($request->hasFile('repair_proof')) {
            $file = $request->file('repair_proof');
            $filename = 'repair_' . $asset->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('asset-proofs', $filename, 'public');
            $updateData['repair_proof_path'] = $path;
        }

        // Log activity before update
        $oldValues = $asset->toArray();
        $asset->update($updateData);
        
        // Log activity: update asset status
        ActivityService::logUpdate($asset, $request->user()->id, $oldValues, $request);

        // If user marks asset as received, align related request status to completed/received
        if (($updateData['status'] ?? null) === 'received') {
            $req = $asset->request;
            if ($req) {
                // Reflect received state on the request for GA/User views
                $req->update(['status' => 'received']);
            }
        } elseif (($updateData['status'] ?? null) === 'needs_repair' || ($updateData['status'] ?? null) === 'needs_replacement') {
            // Keep request visible for admin/repair pipeline
            $req = $asset->request;
            if ($req) {
                $req->update(['status' => 'approved']); // show under admin; procurement will see via assets
            }
        } elseif (($updateData['status'] ?? null) === 'repairing' || ($updateData['status'] ?? null) === 'replacing') {
            // Asset is being processed by procurement; reflect on request as not_received during processing
            $req = $asset->request;
            if ($req) {
                $req->update(['status' => 'not_received']);
            }
        }

        return response()->json(['message' => 'Asset status updated', 'asset' => $asset->fresh(['request'])]);
    }

    public function requestMaintenance(Request $request, $id)
    {
        $user = $request->user();
        $asset = Asset::with('request')->findOrFail($id);

        if (!in_array($user->role ?? '', ['admin_ga', 'ga', 'user'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (($user->role ?? '') === 'user') {
            if (!$asset->request || $asset->request->user_id !== $user->id) {
                return response()->json(['message' => 'Asset not found'], 404);
            }
        }

        if (in_array($asset->maintenance_status, ['pending', 'in_progress']) ) {
            return response()->json(['message' => 'Maintenance already requested or in progress'], 422);
        }

        $data = $request->validate([
            'type' => 'required|in:repair,replacement',
            'reason' => 'required|string|min:5',
        ]);

        if ($asset->request) {
            if ($asset->request->status !== 'completed') {
                return response()->json(['message' => 'Maintenance can only be requested after completion'], 422);
            }
        }

        $asset->maintenance_type = $data['type'];
        $asset->maintenance_reason = $data['reason'];
        // Use maintenance_pending for maintenance requests to distinguish from regular requests
        $asset->maintenance_status = 'maintenance_pending';
        $asset->maintenance_requested_by = $user->id;
        $asset->maintenance_requested_at = now();
        $asset->maintenance_completed_by = null;
        $asset->maintenance_completed_at = null;
        $asset->maintenance_completion_notes = null;
        $asset->save();

        if ($asset->request) {
            $asset->request->update([
                'maintenance_type' => $data['type'],
                'maintenance_reason' => $data['reason'],
                'maintenance_status' => 'maintenance_pending',
                'maintenance_requested_by' => $user->id,
                'maintenance_requested_at' => now(),
                'maintenance_completed_by' => null,
                'maintenance_completed_at' => null,
                'maintenance_completion_notes' => null,
            ]);
        }


		return response()->json([
            'message' => 'Maintenance request submitted',
            'asset' => $asset->fresh(['request']),
        ]);
    }

    public function completeMaintenance(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role ?? '', ['procurement', 'admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $asset = Asset::with('request')->findOrFail($id);

        if ($asset->maintenance_status !== 'in_progress') {
            return response()->json(['message' => 'No maintenance in progress'], 422);
        }

        $data = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $asset->maintenance_status = 'completed';
        $asset->maintenance_completed_by = $user->id;
        $asset->maintenance_completed_at = now();
        $asset->maintenance_completion_notes = $data['notes'] ?? null;
        $asset->save();

        if ($asset->request) {
            $asset->request->update([
                'maintenance_status' => 'completed',
                'maintenance_completed_by' => $user->id,
                'maintenance_completed_at' => now(),
                'maintenance_completion_notes' => $data['notes'] ?? null,
            ]);
        }


		return response()->json([
            'message' => 'Maintenance marked as completed',
            'asset' => $asset->fresh(['request']),
        ]);
    }

    public function startMaintenance(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role ?? '', ['procurement', 'admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $asset = Asset::with('request')->findOrFail($id);

        if (!in_array($asset->maintenance_status, ['approved', 'procurement'])) {
            return response()->json(['message' => 'No approved maintenance to start'], 422);
        }

        // Update maintenance status and also update the main status
        $asset->maintenance_status = 'in_progress';
        $asset->maintenance_started_by = $user->id;
        $asset->maintenance_started_at = now();

        // Update main status based on maintenance type
        if ($asset->maintenance_type === 'repair') {
            $asset->status = 'repairing';
        } elseif ($asset->maintenance_type === 'replacement') {
            $asset->status = 'replacing';
        }

        $asset->save();

        if ($asset->request) {
            $asset->request->update([
                'maintenance_status' => 'in_progress',
                'maintenance_started_by' => $user->id,
                'maintenance_started_at' => now(),
            ]);
        }


		return response()->json([
            'message' => 'Maintenance started',
            'asset' => $asset->fresh(['request']),
        ]);
    }

    public function approveMaintenance(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role ?? '', ['admin_ga', 'ga'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $asset = Asset::with('request')->findOrFail($id);

        if ($asset->maintenance_status !== 'maintenance_pending') {
            return response()->json(['message' => 'No pending maintenance to approve'], 422);
        }

        $asset->maintenance_status = 'approved';
        $asset->maintenance_approved_by = $user->id;
        $asset->maintenance_approved_at = now();
        $asset->save();

        if ($asset->request) {
            $asset->request->update([
                'maintenance_status' => 'approved',
                'maintenance_approved_by' => $user->id,
                'maintenance_approved_at' => now(),
            ]);
        }


		return response()->json([
            'message' => 'Maintenance request approved',
            'asset' => $asset->fresh(['request']),
        ]);
    }

    public function rejectMaintenance(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role ?? '', ['admin_ga', 'ga'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $asset = Asset::with('request')->findOrFail($id);

        if ($asset->maintenance_status !== 'maintenance_pending') {
            return response()->json(['message' => 'No pending maintenance to reject'], 422);
        }

        $asset->maintenance_status = 'rejected';
        $asset->save();

        if ($asset->request) {
            $asset->request->update([
                'maintenance_status' => 'rejected',
                'ga_note' => $request->ga_note,
            ]);
        }


		return response()->json([
            'message' => 'Maintenance request rejected',
            'asset' => $asset->fresh(['request']),
        ]);
    }
}
