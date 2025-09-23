<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Make request_items_id nullable to allow direct asset creation
            $table->foreignId('request_items_id')->nullable()->change();

            // Add new fields for enhanced asset management (only if they don't exist)
            if (!Schema::hasColumn('assets', 'name')) {
                $table->string('name', 255)->after('asset_code');
            }
            if (!Schema::hasColumn('assets', 'color')) {
                $table->string('color', 100)->nullable()->after('name');
            }
            if (!Schema::hasColumn('assets', 'location')) {
                $table->string('location', 255)->nullable()->after('color');
            }
            if (!Schema::hasColumn('assets', 'method')) {
                $table->enum('method', ['purchasing', 'data_input'])->default('data_input')->after('location');
            }
            if (!Schema::hasColumn('assets', 'supplier')) {
                $table->string('supplier', 255)->nullable()->after('method');
            }
            if (!Schema::hasColumn('assets', 'purchase_cost')) {
                $table->decimal('purchase_cost', 15, 2)->nullable()->after('supplier');
            }
            if (!Schema::hasColumn('assets', 'purchase_date')) {
                $table->date('purchase_date')->nullable()->after('purchase_cost');
            }
            if (!Schema::hasColumn('assets', 'user_notes')) {
                $table->text('user_notes')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('assets', 'receipt_proof_path')) {
                $table->string('receipt_proof_path')->nullable()->after('user_notes');
            }
            if (!Schema::hasColumn('assets', 'repair_proof_path')) {
                $table->string('repair_proof_path')->nullable()->after('receipt_proof_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'color',
                'location',
                'method',
                'supplier',
                'purchase_cost',
                'purchase_date',
                'user_notes',
                'receipt_proof_path',
                'repair_proof_path'
            ]);

            // Revert request_items_id to not nullable
            $table->foreignId('request_items_id')->nullable(false)->change();
        });
    }
};
