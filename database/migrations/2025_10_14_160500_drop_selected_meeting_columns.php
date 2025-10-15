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
            $columnsToDrop = [
                'user_id',
                'organizer_email',
                'organizer_phone',
                'attendees',
                'kebutuhan',
                'makanan_detail',
                'minuman_detail',
                'prioritas',
            ];

            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('meetings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (!Schema::hasColumn('meetings', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('meetings', 'organizer_email')) {
                $table->string('organizer_email')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'organizer_phone')) {
                $table->string('organizer_phone', 20)->nullable();
            }
            if (!Schema::hasColumn('meetings', 'attendees')) {
                $table->json('attendees')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'kebutuhan')) {
                $table->json('kebutuhan')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'makanan_detail')) {
                $table->string('makanan_detail')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'minuman_detail')) {
                $table->string('minuman_detail')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'prioritas')) {
                $table->string('prioritas')->nullable();
            }
        });
    }
};





