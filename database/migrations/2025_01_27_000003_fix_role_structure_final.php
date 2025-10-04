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
        // Update the role enum to the correct 4 main roles
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            // First, update existing data to match new enum values
            DB::statement("UPDATE users SET role = 'admin_ga' WHERE role = 'admin'");
            DB::statement("UPDATE users SET role = 'user' WHERE role = 'procurement'");
            
            // Then update the enum
            DB::statement("ALTER TABLE users MODIFY role ENUM('user', 'admin_ga', 'admin_ga_manager', 'super_admin') DEFAULT 'user'");
        }
        
        // Update category enum to include all user categories
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
            // Revert to previous role structure
            DB::statement("ALTER TABLE users MODIFY role ENUM('user', 'admin', 'procurement', 'admin_ga', 'admin_ga_manager', 'super_admin') DEFAULT 'user'");
        }
    }
};
