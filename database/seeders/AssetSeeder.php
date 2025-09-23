<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Asset;
use App\Models\RequestItem;
use Illuminate\Support\Str;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing request items to link assets
        $requestItems = RequestItem::all();
        if ($requestItems->isEmpty()) {
            // Create some sample request items if none exist
            $users = User::all();
            if ($users->isNotEmpty()) {
                $requestItems = RequestItem::factory(3)->create([
                    'user_id' => $users->first()->id,
                    'status' => 'approved'
                ]);
            }
        }

        $assets = [
            [
                'name' => 'Dell Laptop XPS 13',
                'category' => 'IT Equipment',
                'color' => 'Silver',
                'location' => 'IT Department',
                'status' => 'received',
                'method' => 'purchasing',
                'supplier' => 'Dell Technologies',
                'purchase_cost' => 15000000,
                'purchase_date' => '2024-01-15',
                'notes' => 'High-performance laptop for development team',
                'asset_code' => 'AST-' . Str::upper(Str::random(8)),
            ],
            [
                'name' => 'Office Chair Ergonomic',
                'category' => 'Office Furniture',
                'color' => 'Black',
                'location' => 'Meeting Room A',
                'status' => 'received',
                'method' => 'data_input',
                'supplier' => 'IKEA',
                'purchase_cost' => 2500000,
                'purchase_date' => '2024-02-10',
                'notes' => 'Ergonomic office chair for meeting room',
                'asset_code' => 'AST-' . Str::upper(Str::random(8)),
            ],
            [
                'name' => 'HP LaserJet Printer',
                'category' => 'IT Equipment',
                'color' => 'White',
                'location' => 'Admin Office',
                'status' => 'needs_repair',
                'method' => 'purchasing',
                'supplier' => 'HP Inc',
                'purchase_cost' => 3500000,
                'purchase_date' => '2023-12-05',
                'notes' => 'Printer needs maintenance - paper jam issue',
                'asset_code' => 'AST-' . Str::upper(Str::random(8)),
            ],
            [
                'name' => 'Whiteboard 4x6 feet',
                'category' => 'Office Supplies',
                'color' => 'White',
                'location' => 'Conference Room',
                'status' => 'received',
                'method' => 'data_input',
                'supplier' => 'Office Depot',
                'purchase_cost' => 750000,
                'purchase_date' => '2024-03-20',
                'notes' => 'Large whiteboard for presentations',
                'asset_code' => 'AST-' . Str::upper(Str::random(8)),
            ],
            [
                'name' => 'Air Conditioner 2 PK',
                'category' => 'Maintenance',
                'color' => 'White',
                'location' => 'Server Room',
                'status' => 'repairing',
                'method' => 'purchasing',
                'supplier' => 'Daikin',
                'purchase_cost' => 8000000,
                'purchase_date' => '2023-11-15',
                'notes' => 'AC for server room cooling - under repair',
                'asset_code' => 'AST-' . Str::upper(Str::random(8)),
            ],
            [
                'name' => 'Projector Epson',
                'category' => 'IT Equipment',
                'color' => 'Black',
                'location' => 'Meeting Room B',
                'status' => 'needs_replacement',
                'method' => 'purchasing',
                'supplier' => 'Epson',
                'purchase_cost' => 12000000,
                'purchase_date' => '2023-10-30',
                'notes' => 'Projector bulb needs replacement',
                'asset_code' => 'AST-' . Str::upper(Str::random(8)),
            ],
            [
                'name' => 'Desk Lamp LED',
                'category' => 'Office Supplies',
                'color' => 'Black',
                'location' => 'Reception Desk',
                'status' => 'received',
                'method' => 'data_input',
                'supplier' => 'IKEA',
                'purchase_cost' => 350000,
                'purchase_date' => '2024-04-05',
                'notes' => 'LED desk lamp for reception area',
                'asset_code' => 'AST-' . Str::upper(Str::random(8)),
            ],
            [
                'name' => 'Network Switch 24 Port',
                'category' => 'IT Equipment',
                'color' => 'Black',
                'location' => 'Server Room',
                'status' => 'received',
                'method' => 'purchasing',
                'supplier' => 'Cisco',
                'purchase_cost' => 5000000,
                'purchase_date' => '2024-01-20',
                'notes' => '24-port network switch for office network',
                'asset_code' => 'AST-' . Str::upper(Str::random(8)),
            ],
        ];

        foreach ($assets as $asset) {
            $asset['request_items_id'] = $requestItems->random()->id;
            Asset::create($asset);
        }
    }
}
