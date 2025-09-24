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
    public function index()
    {
        $items = RequestItem::with(['user', 'assets'])->latest()->get();
        foreach ($items as $item) {
            if ($item->assets && $item->assets->contains(fn($a) => $a->status === 'received')) {
                $item->status = 'received';
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
}
