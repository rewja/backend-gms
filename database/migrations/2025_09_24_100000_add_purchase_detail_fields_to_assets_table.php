<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('purchase_type')->nullable()->after('purchase_date');
            $table->string('purchase_app')->nullable()->after('purchase_type');
            $table->string('purchase_link')->nullable()->after('purchase_app');
            $table->string('store_name')->nullable()->after('purchase_link');
            $table->string('store_location')->nullable()->after('store_name');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['purchase_type','purchase_app','purchase_link','store_name','store_location']);
        });
    }
};




