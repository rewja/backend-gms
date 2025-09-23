<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure status enum includes full workflow including 'hold'
        DB::statement("ALTER TABLE todos MODIFY COLUMN status ENUM('not_started','in_progress','checking','evaluating','reworked','completed','hold') NOT NULL DEFAULT 'not_started'");
    }

    public function down(): void
    {
        // Revert to a minimal set if needed
        DB::statement("ALTER TABLE todos MODIFY COLUMN status ENUM('not_started','in_progress','checking','completed') NOT NULL DEFAULT 'not_started'");
    }
};


