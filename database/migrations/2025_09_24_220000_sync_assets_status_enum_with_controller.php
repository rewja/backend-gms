<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE assets MODIFY status ENUM('procurement','not_received','received','needs_repair','needs_replacement','repairing','replacing','shipping') NOT NULL DEFAULT 'procurement'");
        } else {
            // For sqlite/postgres in local tests, ensure values are accepted
            DB::statement("UPDATE assets SET status = COALESCE(status, 'procurement')");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE assets MODIFY status ENUM('procurement','not_received','received','needs_repair','needs_replacement') NOT NULL DEFAULT 'procurement'");
        }
    }
};


