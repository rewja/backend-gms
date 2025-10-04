<?php

use App\Models\Asset;
use App\Models\RequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('allows maintenance when an asset has been received even if the request status is still approved', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $requestItem = RequestItem::factory()->create([
        'user_id' => $user->id,
        'status' => 'approved',
        'maintenance_status' => 'idle',
    ]);

    $asset = Asset::create([
        'request_items_id' => $requestItem->id,
        'asset_code' => (string) Str::uuid(),
        'name' => 'Test Asset',
        'category' => 'General',
        'status' => 'received',
        'method' => 'purchasing',
        'maintenance_status' => 'idle',
    ]);

    actingAs($user, 'sanctum');

    $response = $this->postJson("/api/requests/{$requestItem->id}/maintenance", [
        'type' => 'repair',
        'reason' => 'Device is malfunctioning',
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Maintenance request submitted']);

    $this->assertDatabaseHas('request_items', [
        'id' => $requestItem->id,
        'maintenance_status' => 'in_progress',
        'maintenance_type' => 'repair',
        'maintenance_requested_by' => $user->id,
    ]);

    $this->assertDatabaseHas('assets', [
        'id' => $asset->id,
        'maintenance_status' => 'in_progress',
        'maintenance_type' => 'repair',
        'maintenance_requested_by' => $user->id,
    ]);
});
