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
            $table->unsignedBigInteger('maintenance_approved_by')->nullable()->after('maintenance_requested_at');
            $table->timestamp('maintenance_approved_at')->nullable()->after('maintenance_approved_by');
        });

        Schema::table('request_items', function (Blueprint $table) {
            $table->unsignedBigInteger('maintenance_approved_by')->nullable()->after('maintenance_requested_at');
            $table->timestamp('maintenance_approved_at')->nullable()->after('maintenance_approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['maintenance_approved_by', 'maintenance_approved_at']);
        });

        Schema::table('request_items', function (Blueprint $table) {
            $table->dropColumn(['maintenance_approved_by', 'maintenance_approved_at']);
        });
    }
};
