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
            // Add evidence_name column (single evidence name)
            if (!Schema::hasColumn('todos', 'evidence_name')) {
                $table->string('evidence_name')->nullable()->after('evidence_path')->comment('Name of single evidence file');
            }

            // Add evidence_names column (multiple evidence names)
            if (!Schema::hasColumn('todos', 'evidence_names')) {
                $table->json('evidence_names')->nullable()->after('evidence_paths')->comment('Names of multiple evidence files');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            if (Schema::hasColumn('todos', 'evidence_name')) {
                $table->dropColumn('evidence_name');
            }

            if (Schema::hasColumn('todos', 'evidence_names')) {
                $table->dropColumn('evidence_names');
            }
        });
    }
};
