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
        Schema::table('meetings', function (Blueprint $table) {
            // Add lainnya_detail column
            if (!Schema::hasColumn('meetings', 'lainnya_detail')) {
                $table->text('lainnya_detail')->nullable()->after('minuman_detail');
            }
            
            // Drop special_requirements column if it exists
            if (Schema::hasColumn('meetings', 'special_requirements')) {
                $table->dropColumn('special_requirements');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            // Drop lainnya_detail column
            if (Schema::hasColumn('meetings', 'lainnya_detail')) {
                $table->dropColumn('lainnya_detail');
            }
            
            // Add back special_requirements column
            if (!Schema::hasColumn('meetings', 'special_requirements')) {
                $table->text('special_requirements')->nullable()->after('prioritas');
            }
        });
    }
};
