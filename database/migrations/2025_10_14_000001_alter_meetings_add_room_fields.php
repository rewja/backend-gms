<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (!Schema::hasColumn('meetings', 'booking_type')) {
                $table->string('booking_type', 20)->nullable()->after('status');
            }
            if (!Schema::hasColumn('meetings', 'organizer_name')) {
                $table->string('organizer_name', 255)->nullable()->after('agenda');
            }
            if (!Schema::hasColumn('meetings', 'organizer_email')) {
                $table->string('organizer_email', 255)->nullable()->after('organizer_name');
            }
            if (!Schema::hasColumn('meetings', 'jumlah_peserta')) {
                $table->integer('jumlah_peserta')->nullable()->after('organizer_email');
            }
            if (!Schema::hasColumn('meetings', 'prioritas')) {
                $table->string('prioritas', 20)->nullable()->after('jumlah_peserta');
            }
            if (!Schema::hasColumn('meetings', 'special_requirements')) {
                $table->text('special_requirements')->nullable()->after('prioritas');
            }
            if (!Schema::hasColumn('meetings', 'kebutuhan')) {
                $table->json('kebutuhan')->nullable()->after('special_requirements');
            }
            if (!Schema::hasColumn('meetings', 'makanan_detail')) {
                $table->text('makanan_detail')->nullable()->after('kebutuhan');
            }
            if (!Schema::hasColumn('meetings', 'minuman_detail')) {
                $table->text('minuman_detail')->nullable()->after('makanan_detail');
            }
            if (!Schema::hasColumn('meetings', 'spk_file_path')) {
                $table->string('spk_file_path', 255)->nullable()->after('minuman_detail');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            // We won't drop columns on down to avoid data loss in production
        });
    }
};


