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
            // Make user_id nullable for public bookings
            $table->foreignId('user_id')->nullable()->change();
            
            // Add fields for public booking
            $table->string('organizer_name')->nullable()->after('agenda');
            $table->string('organizer_email')->nullable()->after('organizer_name');
            $table->string('organizer_phone', 20)->nullable()->after('organizer_email');
            $table->json('attendees')->nullable()->after('organizer_phone');
            $table->text('special_requirements')->nullable()->after('attendees');
            
            // Add booking type to distinguish between internal and public bookings
            $table->enum('booking_type', ['internal', 'public'])->default('internal')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn([
                'organizer_name',
                'organizer_email', 
                'organizer_phone',
                'attendees',
                'special_requirements',
                'booking_type'
            ]);
            
            // Revert user_id to not nullable
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
