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
        // Add maintenance started fields to assets table
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedBigInteger('maintenance_started_by')->nullable()->after('maintenance_requested_at');
            $table->timestamp('maintenance_started_at')->nullable()->after('maintenance_started_by');
        });

        // Add maintenance started fields to request_items table
        Schema::table('request_items', function (Blueprint $table) {
            $table->unsignedBigInteger('maintenance_started_by')->nullable()->after('maintenance_requested_at');
            $table->timestamp('maintenance_started_at')->nullable()->after('maintenance_started_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove maintenance started fields from assets table
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['maintenance_started_by', 'maintenance_started_at']);
        });

        // Remove maintenance started fields from request_items table
        Schema::table('request_items', function (Blueprint $table) {
            $table->dropColumn(['maintenance_started_by', 'maintenance_started_at']);
        });
    }
};


