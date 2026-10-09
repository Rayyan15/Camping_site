<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'review_started_at')) {
                $table->timestamp('review_started_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'cancellation_note')) {
                $table->text('cancellation_note')->nullable();
            }
        });

        DB::table('bookings')
            ->where('status', 'needs_review')
            ->whereNull('review_started_at')
            ->update(['review_started_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['review_started_at', 'cancelled_at', 'cancellation_note']);
        });
    }
};
