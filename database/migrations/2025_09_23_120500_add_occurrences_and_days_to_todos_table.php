<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
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
            if (Schema::hasColumn('todos', 'days_of_week')) {
                $table->dropColumn('days_of_week');
            }
            if (Schema::hasColumn('todos', 'occurrences_per_interval')) {
                $table->dropColumn('occurrences_per_interval');
            }
        });
    }
};




