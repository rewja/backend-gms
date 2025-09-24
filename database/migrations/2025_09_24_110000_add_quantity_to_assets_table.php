<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'quantity')) {
                // Match requested spec: default 1 and placed after purchase_date
                $table->integer('quantity')->default(1)->after('purchase_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'quantity')) {
                $table->dropColumn('quantity');
            }
        });
    }
};


