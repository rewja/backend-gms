<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE assets MODIFY status ENUM('not_received','received','needs_repair','needs_replacement','procurement','shipping') DEFAULT 'not_received'");
        } else {
            // Fallback for sqlite/postgres: store as TEXT to avoid enum issues
            DB::statement("UPDATE assets SET status = 'not_received' WHERE status IS NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE assets MODIFY status ENUM('not_received','received','needs_repair','needs_replacement') DEFAULT 'not_received'");
        }
    }
};




