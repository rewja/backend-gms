<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add repairing and replacing process statuses
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE assets MODIFY status ENUM('procurement','not_received','received','needs_repair','needs_replacement','repairing','replacing') NOT NULL DEFAULT 'procurement'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE assets MODIFY status ENUM('procurement','not_received','received','needs_repair','needs_replacement') NOT NULL DEFAULT 'procurement'");
        }
    }
};









