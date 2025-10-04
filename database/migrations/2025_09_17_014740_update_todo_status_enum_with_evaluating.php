<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ubah enum untuk mendukung status baru
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE todos MODIFY COLUMN status ENUM('not_started', 'in_progress', 'checking', 'evaluating', 'completed') DEFAULT 'not_started'");

        // Update existing records
        DB::table('todos')
            ->where('status', 'checking')
            ->update(['status' => 'evaluating']);
    }

    public function down(): void
    {
        // Kembalikan ke enum sebelumnya
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE todos MODIFY COLUMN status ENUM('not_started', 'in_progress', 'checking', 'completed') DEFAULT 'not_started'");
    }
};
