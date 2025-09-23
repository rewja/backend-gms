<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            if (!Schema::hasColumn('todos', 'recurrence_start_date')) {
                $table->date('recurrence_start_date')->nullable()->after('due_date');
            }
            if (!Schema::hasColumn('todos', 'recurrence_interval')) {
                $table->unsignedInteger('recurrence_interval')->nullable()->after('recurrence_start_date');
            }
            if (!Schema::hasColumn('todos', 'recurrence_unit')) {
                $table->enum('recurrence_unit', ['day','week','month','year'])->nullable()->after('recurrence_interval');
            }
            if (!Schema::hasColumn('todos', 'recurrence_count')) {
                $table->unsignedInteger('recurrence_count')->nullable()->after('recurrence_unit')->comment('0 means unlimited');
            }
            if (!Schema::hasColumn('todos', 'occurrences_per_interval')) {
                $table->unsignedInteger('occurrences_per_interval')->nullable()->after('recurrence_count')->comment('for weekly/monthly: number of tasks per interval');
            }
            if (!Schema::hasColumn('todos', 'days_of_week')) {
                $table->json('days_of_week')->nullable()->after('occurrences_per_interval')->comment('array of 0-6 for weekly schedules');
            }
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            if (Schema::hasColumn('todos', 'recurrence_start_date')) {
                $table->dropColumn('recurrence_start_date');
            }
            if (Schema::hasColumn('todos', 'recurrence_interval')) {
                $table->dropColumn('recurrence_interval');
            }
            if (Schema::hasColumn('todos', 'recurrence_unit')) {
                $table->dropColumn('recurrence_unit');
            }
            if (Schema::hasColumn('todos', 'recurrence_count')) {
                $table->dropColumn('recurrence_count');
            }
        });
    }
};









