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
            // Drop fields not used by meeting-room flow
            $columnsToDrop = [
                'kode_project',
                'nama_project',
                'main_contractor',
                'project_manager',
                'no_spk',
                'jenis_pekerjaan',
                'uraian_pekerjaan',
                'catatan_pekerjaan',
                // We replaced special_requirements with structured kebutuhan/makanan/minuman
                'special_requirements',
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
            // Recreate previously dropped columns as nullable strings/texts
            if (!Schema::hasColumn('meetings', 'kode_project')) $table->string('kode_project')->nullable();
            if (!Schema::hasColumn('meetings', 'nama_project')) $table->string('nama_project')->nullable();
            if (!Schema::hasColumn('meetings', 'main_contractor')) $table->string('main_contractor')->nullable();
            if (!Schema::hasColumn('meetings', 'project_manager')) $table->string('project_manager')->nullable();
            if (!Schema::hasColumn('meetings', 'no_spk')) $table->string('no_spk')->nullable();
            if (!Schema::hasColumn('meetings', 'jenis_pekerjaan')) $table->string('jenis_pekerjaan')->nullable();
            if (!Schema::hasColumn('meetings', 'uraian_pekerjaan')) $table->text('uraian_pekerjaan')->nullable();
            if (!Schema::hasColumn('meetings', 'catatan_pekerjaan')) $table->text('catatan_pekerjaan')->nullable();
            if (!Schema::hasColumn('meetings', 'special_requirements')) $table->text('special_requirements')->nullable();
        });
    }
};
