<?php

namespace App\Http\Controllers;

use App\Models\Procurement;
use App\Models\RequestItem;
use App\Services\ActivityService;
use Illuminate\Http\Request;

class ProcurementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $procurements = Procurement::with(['request', 'user'])->latest()->get();

        return response()->json($procurements);
    }

    // Procurement stats: counts and total value per month/year, top vendors if available
    public function stats()
    {
        $driver = \DB::connection()->getDriverName();
        $monthExpr = $driver === 'mysql' ? 'DATE_FORMAT(purchase_date, "%Y-%m")' : 'strftime("%Y-%m", purchase_date)';
        $yearExpr = $driver === 'mysql' ? 'DATE_FORMAT(purchase_date, "%Y")'   : 'strftime("%Y", purchase_date)';

        $monthlyCount = \DB::table('procurements')
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total")
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $monthlyAmount = \DB::table('procurements')
            ->selectRaw("{$monthExpr} as ym, SUM(amount) as total_amount")
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $yearlyCount = \DB::table('procurements')
            ->selectRaw("{$yearExpr} as y, COUNT(*) as total")
            ->groupByRaw($yearExpr)
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        $yearlyAmount = \DB::table('procurements')
            ->selectRaw("{$yearExpr} as y, SUM(amount) as total_amount")
            ->groupByRaw($yearExpr)
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        return response()->json([
            'monthly' => [ 'count' => $monthlyCount, 'amount' => $monthlyAmount ],
            'yearly' => [ 'count' => $yearlyCount, 'amount' => $yearlyAmount ],
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
        // Normalize incoming amount formats like "10.000" or "10,000"
        $normalizedAmount = $request->amount;
        if (is_string($normalizedAmount)) {
            $normalizedAmount = str_replace(['.', ','], ['', ''], $normalizedAmount);
        }

        // Normalize purchase_date if browser sends MM/DD/YYYY
        $normalizedDate = $request->purchase_date;
        if (is_string($normalizedDate) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $normalizedDate)) {
            $parts = explode('/', $normalizedDate); // [MM, DD, YYYY]
            $normalizedDate = $parts[2] . '-' . $parts[0] . '-' . $parts[1];
        }

        // Let Laravel handle validation exceptions with 422 response
        $data = validator([
            'request_items_id' => $request->request_items_id,
            'purchase_date' => $normalizedDate,
            'amount' => $normalizedAmount,
            'notes' => $request->notes,
        ], [
            'request_items_id' => 'required|exists:request_items,id',
            'purchase_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ])->validate();

        try {
            $data['executed_by'] = $request->user()->id;

            $procurement = Procurement::create($data);

            // Log activity
            $req = RequestItem::find($data['request_items_id']);
            $assetName = $req ? ($req->item_name ?? "Asset from Request #{$req->id}") : "Asset";
            $amountFormatted = number_format($data['amount'], 0, ',', '.');
            $description = "Membeli {$assetName} (Rp {$amountFormatted}) - Request #{$data['request_items_id']}";
            
            ActivityService::log(
                $request->user()->id,
                'purchase',
                $description,
                'App\\Models\\Procurement',
                $procurement->id,
                null,
                $procurement->toArray(),
                $request
            );

            // After purchase, ensure asset exists and mark states appropriately
            if ($req) {
                // Keep request status as approved (requests only have pending/approved/rejected per your flow)
                $req->update(['status' => 'approved']);

                // If no asset exists yet (approval no longer creates it), create it now
                $asset = \App\Models\Asset::where('request_items_id', $req->id)->latest()->first();
                if (!$asset) {
                    $assetController = new \App\Http\Controllers\AssetController();
                    if (method_exists($assetController, 'generateAssetCode')) {
                        $generatedCode = $assetController->generateAssetCode($req->category ?? 'Other', 'request');
                    } else {
                        $generatedCode = 'AST-' . str_pad($req->id, 6, '0', STR_PAD_LEFT);
                    }

                    $asset = \App\Models\Asset::create([
                        'name' => $req->item_name ?? ('Asset from Request #' . $req->id),
                        'request_items_id' => $req->id,
                        'asset_code' => $generatedCode ?: ('AST-' . str_pad($req->id, 6, '0', STR_PAD_LEFT)),
                        'category' => $req->category ?? 'General',
                        // After purchase, move out from procurement into shipping
                        'status' => 'shipping',
                        'method' => 'purchasing',
                        'notes' => $data['notes'] ?? null,
                        'purchase_date' => $data['purchase_date'] ?? null,
                        'purchase_cost' => $data['amount'] ?? null,
                        // store structured purchase details
                        'purchase_type' => $request->purchase_type ?? null,
                        'purchase_app' => $request->purchase_app ?? null,
                        'purchase_link' => $request->purchase_link ?? null,
                        'store_name' => $request->store_name ?? null,
                        'store_location' => $request->store_location ?? null,
                    ]);
                    
                    // Log asset creation
                    ActivityService::logCreate($asset, $request->user()->id, $request);
                } else {
                    // If it existed in procurement, move it into shipping upon purchase
                    $oldValues = [
                        'status' => $asset->status,
                        'purchase_date' => $asset->purchase_date,
                        'purchase_cost' => $asset->purchase_cost,
                    ];
                    
                    $asset->update([
                        'status' => 'shipping',
                        'purchase_date' => $data['purchase_date'] ?? $asset->purchase_date,
                        'purchase_cost' => $data['amount'] ?? $asset->purchase_cost,
                        'notes' => $data['notes'] ?? $asset->notes,
                        'purchase_type' => $request->purchase_type ?? $asset->purchase_type,
                        'purchase_app' => $request->purchase_app ?? $asset->purchase_app,
                        'purchase_link' => $request->purchase_link ?? $asset->purchase_link,
                        'store_name' => $request->store_name ?? $asset->store_name,
                        'store_location' => $request->store_location ?? $asset->store_location,
                    ]);
                    
                    // Log asset update
                    ActivityService::logUpdate($asset, $request->user()->id, $oldValues, $request);
                }
            }

            return response()->json(['message' => 'Procurement recorded', 'procurement' => $procurement], 201);
        } catch (\Throwable $e) {
            \Log::error('Failed to store procurement: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to record procurement', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Procurement $procurement)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Procurement $procurement)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Procurement $procurement)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Procurement $procurement)
    {
        //
    }
}
