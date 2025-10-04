<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the role enum to include new admin roles (keep user role as is)
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user', 'admin', 'procurement', 'admin_ga', 'admin_ga_manager', 'super_admin') DEFAULT 'user'");
        }
        
        // Update category enum to include new category
        if (Schema::hasColumn('users', 'category')) {
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE users MODIFY category ENUM('ob', 'driver', 'security', 'magang_pkl') NULL");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user', 'admin', 'procurement') DEFAULT 'user'");
            DB::statement("ALTER TABLE users MODIFY category ENUM('ob', 'driver', 'security') NULL");
        }
    }
};
