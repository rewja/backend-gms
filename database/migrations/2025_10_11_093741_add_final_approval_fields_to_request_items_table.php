<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            // Add final approval fields
            $table->unsignedBigInteger('final_approved_by')->nullable()->after('ga_note');
            $table->timestamp('final_approved_at')->nullable()->after('final_approved_by');
            $table->text('final_note')->nullable()->after('final_approved_at');
            $table->unsignedBigInteger('final_rejected_by')->nullable()->after('final_note');
            $table->timestamp('final_rejected_at')->nullable()->after('final_rejected_by');
            $table->text('final_rejection_reason')->nullable()->after('final_rejected_at');
        });

        // Update status enum to include final approval statuses
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE request_items MODIFY status ENUM('pending','approved','rejected','procurement','not_received','purchased','final_approved','final_rejected') NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            $table->dropColumn([
                'final_approved_by',
                'final_approved_at', 
                'final_note',
                'final_rejected_by',
                'final_rejected_at',
                'final_rejection_reason'
            ]);
        });

        // Revert status enum to previous state
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE request_items MODIFY status ENUM('pending','approved','rejected','procurement','not_received','purchased') NOT NULL DEFAULT 'pending'");
        }
    }
};
