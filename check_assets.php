<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Asset Status ===\n";

// Check OE-09282025-000005
echo "\n1. Asset OE-09282025-000005:\n";
$asset1 = \App\Models\Asset::where('asset_code', 'OE-09282025-000005')->first();
if ($asset1) {
    echo "   ID: " . $asset1->id . "\n";
    echo "   Status: " . $asset1->status . "\n";
    echo "   Maintenance Status: " . $asset1->maintenance_status . "\n";
    echo "   Created: " . $asset1->created_at . "\n";
} else {
    echo "   Not found\n";
}

// Check DE-09282025-000006
echo "\n2. Asset DE-09282025-000006:\n";
$asset2 = \App\Models\Asset::where('asset_code', 'DE-09282025-000006')->first();
if ($asset2) {
    echo "   ID: " . $asset2->id . "\n";
    echo "   Status: " . $asset2->status . "\n";
    echo "   Maintenance Status: " . $asset2->maintenance_status . "\n";
    echo "   Created: " . $asset2->created_at . "\n";
} else {
    echo "   Not found\n";
}

// Check RequestItems for these assets
echo "\n=== Checking Request Items ===\n";

$request1 = \App\Models\RequestItem::whereHas('assets', function($query) {
    $query->where('asset_code', 'OE-09282025-000005');
})->first();

if ($request1) {
    echo "\nRequest for OE-09282025-000005:\n";
    echo "   ID: " . $request1->id . "\n";
    echo "   Item Name: " . $request1->item_name . "\n";
    echo "   Status: " . $request1->status . "\n";
    echo "   Assets Count: " . $request1->assets->count() . "\n";
    if ($request1->assets->count() > 0) {
        $firstAsset = $request1->assets->first();
        echo "   First Asset Code: " . $firstAsset->asset_code . "\n";
        echo "   First Asset Status: " . $firstAsset->status . "\n";
        echo "   First Asset Maintenance Status: " . $firstAsset->maintenance_status . "\n";
    }
} else {
    echo "\nNo request found for OE-09282025-000005\n";
}

$request2 = \App\Models\RequestItem::whereHas('assets', function($query) {
    $query->where('asset_code', 'DE-09282025-000006');
})->first();

if ($request2) {
    echo "\nRequest for DE-09282025-000006:\n";
    echo "   ID: " . $request2->id . "\n";
    echo "   Item Name: " . $request2->item_name . "\n";
    echo "   Status: " . $request2->status . "\n";
    echo "   Assets Count: " . $request2->assets->count() . "\n";
    if ($request2->assets->count() > 0) {
        $firstAsset = $request2->assets->first();
        echo "   First Asset Code: " . $firstAsset->asset_code . "\n";
        echo "   First Asset Status: " . $firstAsset->status . "\n";
        echo "   First Asset Maintenance Status: " . $firstAsset->maintenance_status . "\n";
    }
} else {
    echo "\nNo request found for DE-09282025-000006\n";
}

echo "\n=== Done ===\n";
