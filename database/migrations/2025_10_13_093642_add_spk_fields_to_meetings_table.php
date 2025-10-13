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
            // SPK fields
            $table->string('kode_project')->nullable();
            $table->string('nama_project')->nullable();
            $table->string('main_contractor')->nullable();
            $table->string('project_manager')->nullable();
            $table->string('no_spk')->nullable();
            $table->enum('prioritas', ['low', 'medium', 'high', 'urgent'])->nullable();
            $table->date('waktu_penyelesaian')->nullable();
            $table->string('jenis_pekerjaan')->nullable();
            $table->text('uraian_pekerjaan')->nullable();
            $table->text('catatan_pekerjaan')->nullable();
            $table->integer('jumlah_peserta')->nullable();
            $table->string('spk_file_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn([
                'kode_project',
                'nama_project',
                'main_contractor',
                'project_manager',
                'no_spk',
                'prioritas',
                'waktu_penyelesaian',
                'jenis_pekerjaan',
                'uraian_pekerjaan',
                'catatan_pekerjaan',
                'jumlah_peserta',
                'spk_file_path'
            ]);
        });
    }
};
