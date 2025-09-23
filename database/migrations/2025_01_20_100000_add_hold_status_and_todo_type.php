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
        // Add new status values to existing enum
        DB::statement("ALTER TABLE todos MODIFY COLUMN status ENUM('not_started', 'in_progress', 'checking', 'evaluating', 'reworked', 'completed', 'hold') DEFAULT 'not_started'");
        
        // Add todo type field
        Schema::table('todos', function (Blueprint $table) {
            $table->enum('todo_type', ['rutin', 'tambahan'])->default('rutin')->after('priority');
            $table->enum('target_category', ['all', 'ob', 'driver', 'security'])->default('all')->after('todo_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert status enum
        DB::statement("ALTER TABLE todos MODIFY COLUMN status ENUM('not_started', 'in_progress', 'checking', 'evaluating', 'reworked', 'completed') DEFAULT 'not_started'");
        
        // Drop new columns
        Schema::table('todos', function (Blueprint $table) {
            $table->dropColumn(['todo_type', 'target_category']);
        });
    }
};

