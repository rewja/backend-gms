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
        Schema::table('meetings', function (Blueprint $table) {
            // Add checking status fields
            $table->enum('ga_check_status', ['pending', 'approved', 'rejected'])->default('pending')->after('status');
            $table->enum('ga_manager_check_status', ['pending', 'approved', 'rejected'])->default('pending')->after('ga_check_status');
            
            // Add checking user IDs
            $table->unsignedBigInteger('checked_by_ga')->nullable()->after('ga_manager_check_status');
            $table->unsignedBigInteger('checked_by_ga_manager')->nullable()->after('checked_by_ga');
            
            // Add checking timestamps
            $table->timestamp('ga_checked_at')->nullable()->after('checked_by_ga_manager');
            $table->timestamp('ga_manager_checked_at')->nullable()->after('ga_checked_at');
            
            // Add checking notes
            $table->text('ga_check_notes')->nullable()->after('ga_manager_checked_at');
            $table->text('ga_manager_check_notes')->nullable()->after('ga_check_notes');
            
            // Add foreign key constraints
            $table->foreign('checked_by_ga')->references('id')->on('users')->onDelete('set null');
            $table->foreign('checked_by_ga_manager')->references('id')->on('users')->onDelete('set null');
            
            // Update status enum to include canceled
            $table->enum('status', ['scheduled', 'ongoing', 'ended', 'force_ended', 'canceled'])->default('scheduled')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['checked_by_ga']);
            $table->dropForeign(['checked_by_ga_manager']);
            
            // Drop columns
            $table->dropColumn([
                'ga_check_status',
                'ga_manager_check_status',
                'checked_by_ga',
                'checked_by_ga_manager',
                'ga_checked_at',
                'ga_manager_checked_at',
                'ga_check_notes',
                'ga_manager_check_notes'
            ]);
            
            // Revert status enum
            $table->enum('status', ['scheduled', 'ongoing', 'ended', 'force_ended'])->default('scheduled')->change();
        });
    }
};













