<?php

namespace App\Http\Controllers;

use App\Models\RequestItem;
use Illuminate\Http\Request;

class RequestItemController extends Controller
{
    // User: list own requests
    public function mine(Request $request)
    {
        $items = RequestItem::with('assets')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        // If any related asset has been received, reflect it on request status for UI consistency
        foreach ($items as $item) {
            if ($item->assets && $item->assets->contains(fn($a) => $a->status === 'received')) {
                $item->status = 'received';
            }
        }
        return response()->json($items);
    }

    // User: create request
    public function store(Request $request)
    {
        $data = $request->validate([
            'item_name' => 'required|string|max:200',
            'quantity' => 'required|integer|min:1',
            'estimated_cost' => 'nullable|numeric|min:0',
            'category' => 'nullable|string|max:100',
            'reason' => 'nullable|string',
        ]);

        $data['user_id'] = $request->user()->id;

        $req = RequestItem::create($data);

        return response()->json(['message' => 'Request created successfully', 'request' => $req], 201);
    }

    // User: update own request (only when pending)
    public function update(Request $request, $id)
    {
        $req = RequestItem::where('user_id', $request->user()->id)->findOrFail($id);
        if ($req->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be updated'], 422);
        }

        $data = $request->validate([
            'item_name' => 'sometimes|required|string|max:200',
            'quantity' => 'sometimes|required|integer|min:1',
            'estimated_cost' => 'nullable|numeric|min:0',
            'category' => 'nullable|string|max:100',
            'reason' => 'nullable|string',
        ]);

        $req->update($data);
        return response()->json(['message' => 'Request updated', 'request' => $req]);
    }

    // GA: list all requests
    public function index(Request $request)
    {
        // If count_only parameter is present, return only count
        if ($request->has('count_only') && $request->count_only) {
            $count = RequestItem::count();
            return response()->json($count);
        }

        $items = RequestItem::with(['user', 'assets'])->latest()->get();
        foreach ($items as $item) {
            if ($item->assets && $item->assets->count() > 0) {
                $firstAsset = $item->assets->first();
                $item->asset_code = $firstAsset->asset_code;
                $item->maintenance_status = $firstAsset->maintenance_status;

                // Update request status based on asset status
                if ($firstAsset->status === 'received') {
                    $item->status = 'received';
                } elseif ($firstAsset->status === 'replacing' || $firstAsset->status === 'repairing') {
                    // If asset is in maintenance, consider request as received
                    $item->status = 'received';
                }
            }
        }
        return response()->json($items);
    }

    // User: statistics (counts per day/month/year and status distribution)
    public function statsUser(Request $request)
    {
        $userId = $request->user()->id;
        $daily = \DB::table('request_items')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('user_id', $userId)
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at) DESC')
            ->limit(30)
            ->get();

        $driver = \DB::connection()->getDriverName();
        $monthExpr = $driver === 'mysql' ? 'DATE_FORMAT(created_at, "%Y-%m")' : 'strftime("%Y-%m", created_at)';
        $yearExpr = $driver === 'mysql' ? 'DATE_FORMAT(created_at, "%Y")'   : 'strftime("%Y", created_at)';

        $monthly = \DB::table('request_items')
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total")
            ->where('user_id', $userId)
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $yearly = \DB::table('request_items')
            ->selectRaw("{$yearExpr} as y, COUNT(*) as total")
            ->where('user_id', $userId)
            ->groupByRaw($yearExpr)
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        $status = \DB::table('request_items')
            ->selectRaw('status, COUNT(*) as total')
            ->where('user_id', $userId)
            ->groupBy('status')
            ->get();

        return response()->json([
            'daily' => $daily,
            'monthly' => $monthly,
            'yearly' => $yearly,
            'status' => $status,
        ]);
    }

    // Admin: global statistics
    public function statsGlobal(Request $request)
    {
        $daily = \DB::table('request_items')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at) DESC')
            ->limit(30)
            ->get();

        $driver = \DB::connection()->getDriverName();
        $monthExpr = $driver === 'mysql' ? 'DATE_FORMAT(created_at, "%Y-%m")' : 'strftime("%Y-%m", created_at)';
        $yearExpr = $driver === 'mysql' ? 'DATE_FORMAT(created_at, "%Y")'   : 'strftime("%Y", created_at)';

        $monthly = \DB::table('request_items')
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total")
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $yearly = \DB::table('request_items')
            ->selectRaw("{$yearExpr} as y, COUNT(*) as total")
            ->groupByRaw($yearExpr)
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        $status = \DB::table('request_items')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        return response()->json([
            'daily' => $daily,
            'monthly' => $monthly,
            'yearly' => $yearly,
            'status' => $status,
        ]);
    }

    // GA: approve request
    public function approve(Request $request, $id)
    {
        $req = RequestItem::findOrFail($id);
        // Mark request as approved (DB enum: pending|approved|rejected|purchased)
        $req->update([
            'status' => 'approved',
            'ga_note' => $request->ga_note ?? null,
        ]);

        // Create a procurement placeholder asset so procurement can process purchase
        $assetController = new \App\Http\Controllers\AssetController();
        if (method_exists($assetController, 'generateAssetCode')) {
            $generatedCode = $assetController->generateAssetCode($req->category ?? 'Other', 'request');
        } else {
            $generatedCode = 'AST-' . str_pad($id, 6, '0', STR_PAD_LEFT);
        }

        \App\Models\Asset::create([
            'name' => $req->item_name ?? ('Asset from Request #' . $req->id),
            'request_items_id' => $req->id,
            'asset_code' => $generatedCode ?: ('AST-' . str_pad($id, 6, '0', STR_PAD_LEFT)),
            'category' => $req->category ?? 'General',
            'status' => 'procurement',
            'method' => 'purchasing',
            'notes' => null,
        ]);

        return response()->json(['message' => 'Request approved', 'request' => $req]);
    }

    // GA: reject request
    public function reject(Request $request, $id)
    {
        $req = RequestItem::findOrFail($id);
        $req->update([
            'status' => 'rejected',
            'ga_note' => $request->ga_note ?? null,
        ]);

        return response()->json(['message' => 'Request rejected', 'request' => $req]);
    }

    // Admin: update any request (content fields only)
    public function updateAdmin(Request $request, $id)
    {
        $req = RequestItem::findOrFail($id);

        $data = $request->validate([
            'item_name' => 'sometimes|required|string|max:200',
            'quantity' => 'sometimes|required|integer|min:1',
            'estimated_cost' => 'nullable|numeric|min:0',
            'category' => 'nullable|string|max:100',
            'reason' => 'nullable|string',
            'ga_note' => 'nullable|string',
        ]);

        $req->update($data);
        return response()->json(['message' => 'Request updated', 'request' => $req]);
    }

    // Admin: delete request (only when safe)
    public function destroyAdmin($id)
    {
        $req = RequestItem::with('assets')->findOrFail($id);
        // Force allow delete for any status; assets will be removed via FK cascade
        $req->delete();
        return response()->json(['message' => 'Request deleted']);
    }

    // User: delete own request (only when pending)
    public function destroy(Request $request, $id)
    {
        $req = RequestItem::where('user_id', $request->user()->id)->findOrFail($id);
        if ($req->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be deleted'], 422);
        }
        $req->delete();
        return response()->json(['message' => 'Request deleted']);
    }

    public function requestMaintenance(Request $request, $id)
    {
        $currentUser = $request->user();
        $item = RequestItem::with('assets')->findOrFail($id);

        if (($currentUser->role ?? '') === 'user' && $item->user_id !== $currentUser->id) {
            return response()->json(['message' => 'Request not found'], 404);
        }

        if (!in_array($currentUser->role ?? '', ['user', 'ga', 'admin_ga'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($item->assets->isEmpty()) {
            return response()->json(['message' => 'No assets available for maintenance'], 422);
        }

        $hasReceivedAssets = $item->assets->contains(fn ($asset) => $asset->status === 'received');
        if (!($hasReceivedAssets || in_array($item->status, ['completed', 'received']))) {
            return response()->json(['message' => 'Maintenance is only available after the items have been received'], 422);
        }

        if (in_array($item->maintenance_status, ['maintenance_pending', 'in_progress'])) {
            return response()->json(['message' => 'Maintenance already requested or in progress'], 422);
        }

        $data = $request->validate([
            'type' => 'required|in:repair,replacement',
            'reason' => 'required|string|min:5',
        ]);

        foreach ($item->assets as $asset) {
            if (in_array($asset->maintenance_status, ['maintenance_pending', 'in_progress'])) {
                return response()->json(['message' => 'Maintenance already requested for linked asset'], 422);
            }
        }

    \DB::transaction(function () use ($item, $data, $currentUser) {
            $item->update([
                'maintenance_type' => $data['type'],
                'maintenance_reason' => $data['reason'],
                'maintenance_status' => 'maintenance_pending',
                'maintenance_requested_by' => $currentUser->id,
                'maintenance_requested_at' => now(),
                'maintenance_completed_by' => null,
                'maintenance_completed_at' => null,
                'maintenance_completion_notes' => null,
            ]);

            foreach ($item->assets as $asset) {
                $asset->maintenance_type = $data['type'];
                $asset->maintenance_reason = $data['reason'];
                $asset->maintenance_status = 'maintenance_pending';
                $asset->maintenance_requested_by = $currentUser->id;
                $asset->maintenance_requested_at = now();
                $asset->maintenance_completed_by = null;
                $asset->maintenance_completed_at = null;
                $asset->maintenance_completion_notes = null;
                $asset->status = $data['type'] === 'replacement' ? 'needs_replacement' : 'needs_repair';
                $asset->save();
            }
            if ($data['type'] === 'replacement') {
                $item->status = 'procurement';
            } elseif ($data['type'] === 'repair') {
                $item->status = 'procurement';
            }
            $item->save();
        });

        return response()->json([
            'message' => 'Maintenance request submitted',
            'request' => $item->fresh(['assets']),
        ]);
    }

    public function completeMaintenance(Request $request, $id)
    {
        $currentUser = $request->user();
        if (!in_array($currentUser->role ?? '', ['admin_ga', 'admin_ga_manager', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $item = RequestItem::with('assets')->findOrFail($id);

        if ($item->maintenance_status !== 'in_progress') {
            return response()->json(['message' => 'No maintenance in progress'], 422);
        }

        $data = $request->validate([
            'notes' => 'nullable|string',
        ]);

    \DB::transaction(function () use ($item, $data, $currentUser) {
            $item->update([
                'maintenance_status' => 'completed',
                'maintenance_completed_by' => $currentUser->id,
                'maintenance_completed_at' => now(),
                'maintenance_completion_notes' => $data['notes'] ?? null,
            ]);

            foreach ($item->assets as $asset) {
                $asset->maintenance_status = 'completed';
                $asset->maintenance_completed_by = $currentUser->id;
                $asset->maintenance_completed_at = now();
                $asset->maintenance_completion_notes = $data['notes'] ?? null;
                $asset->status = 'received';
                $asset->save();
            }
            $item->status = 'received';
            $item->save();
        });

        return response()->json([
            'message' => 'Maintenance marked as completed',
            'request' => $item->fresh(['assets']),
        ]);
    }

    public function startMaintenance(Request $request, $id)
    {
        $currentUser = $request->user();
        if (!in_array($currentUser->role ?? '', ['admin_ga', 'admin_ga_manager', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $item = RequestItem::with('assets')->findOrFail($id);

        if ($item->maintenance_status !== 'maintenance_pending') {
            return response()->json(['message' => 'No pending maintenance to start'], 422);
        }

        \DB::transaction(function () use ($item, $currentUser) {
            $item->update([
                'maintenance_status' => 'in_progress',
                'maintenance_started_by' => $currentUser->id,
                'maintenance_started_at' => now(),
            ]);

            foreach ($item->assets as $asset) {
                $asset->maintenance_status = 'in_progress';
                $asset->maintenance_started_by = $currentUser->id;
                $asset->maintenance_started_at = now();
                $asset->save();
            }
        });

        return response()->json([
            'message' => 'Maintenance started',
            'request' => $item->fresh(['assets']),
        ]);
    }
}
