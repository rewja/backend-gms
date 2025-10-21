<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make sure the enum contains the new value used by the application
        // Using raw statement to alter the enum safely.
        DB::statement("ALTER TABLE `todos` MODIFY `target_category` ENUM('all','ob','driver','security','magang_pkl') NOT NULL DEFAULT 'all'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to previous enum (without magang_pkl). If you need a different revert, adjust accordingly.
        DB::statement("ALTER TABLE `todos` MODIFY `target_category` ENUM('all','ob','driver','security') NOT NULL DEFAULT 'all'");
    }
};
