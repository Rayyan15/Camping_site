<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_units', function (Blueprint $table) {
            $table->index(['unit_id', 'check_in', 'check_out'], 'booking_units_unit_dates_index');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['status', 'hold_expires_at'], 'bookings_status_hold_index');
        });
    }

    public function down(): void
    {
        Schema::table('booking_units', function (Blueprint $table) {
            $table->dropIndex('booking_units_unit_dates_index');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_status_hold_index');
        });
    }
};
