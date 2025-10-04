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
            $table->string('maintenance_status', 20)->default('idle')->after('notes');
            $table->string('maintenance_type', 20)->nullable()->after('maintenance_status');
            $table->text('maintenance_reason')->nullable()->after('maintenance_type');
            $table->foreignId('maintenance_requested_by')->nullable()->after('maintenance_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('maintenance_requested_at')->nullable()->after('maintenance_requested_by');
            $table->foreignId('maintenance_completed_by')->nullable()->after('maintenance_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('maintenance_completed_at')->nullable()->after('maintenance_completed_by');
            $table->text('maintenance_completion_notes')->nullable()->after('maintenance_completed_at');
        });

        Schema::table('request_items', function (Blueprint $table) {
            $table->string('maintenance_status', 20)->default('idle')->after('ga_note');
            $table->string('maintenance_type', 20)->nullable()->after('maintenance_status');
            $table->text('maintenance_reason')->nullable()->after('maintenance_type');
            $table->foreignId('maintenance_requested_by')->nullable()->after('maintenance_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('maintenance_requested_at')->nullable()->after('maintenance_requested_by');
            $table->foreignId('maintenance_completed_by')->nullable()->after('maintenance_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('maintenance_completed_at')->nullable()->after('maintenance_completed_by');
            $table->text('maintenance_completion_notes')->nullable()->after('maintenance_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['maintenance_requested_by']);
            $table->dropForeign(['maintenance_completed_by']);
            $table->dropColumn([
                'maintenance_status',
                'maintenance_type',
                'maintenance_reason',
                'maintenance_requested_by',
                'maintenance_requested_at',
                'maintenance_completed_by',
                'maintenance_completed_at',
                'maintenance_completion_notes',
            ]);
        });

        Schema::table('request_items', function (Blueprint $table) {
            $table->dropForeign(['maintenance_requested_by']);
            $table->dropForeign(['maintenance_completed_by']);
            $table->dropColumn([
                'maintenance_status',
                'maintenance_type',
                'maintenance_reason',
                'maintenance_requested_by',
                'maintenance_requested_at',
                'maintenance_completed_by',
                'maintenance_completed_at',
                'maintenance_completion_notes',
            ]);
        });
    }
};
