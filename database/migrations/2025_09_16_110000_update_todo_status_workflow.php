<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Update enum values for status workflow
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE todos MODIFY COLUMN status ENUM('not_started','in_progress','checking','completed') NOT NULL DEFAULT 'not_started'");
    }

    public function down(): void
    {
        // Revert to previous enum
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE todos MODIFY COLUMN status ENUM('pending','in_progress','done','checked') NOT NULL DEFAULT 'pending'");
    }
};


