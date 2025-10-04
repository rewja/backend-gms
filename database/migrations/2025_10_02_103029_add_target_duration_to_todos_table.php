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
        Schema::table('todos', function (Blueprint $table) {
            $table->integer('target_duration_value')->nullable()->after('target_end_at')->comment('Target duration value (number)');
            $table->enum('target_duration_unit', ['minutes', 'hours'])->default('minutes')->after('target_duration_value')->comment('Target duration unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropColumn(['target_duration_value', 'target_duration_unit']);
        });
    }
};
