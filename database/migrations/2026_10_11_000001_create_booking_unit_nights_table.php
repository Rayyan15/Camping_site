<?php

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OCCUPYING_STATUSES = ['paid', 'checked_in', 'needs_review'];

    public function up(): void
    {
        Schema::create('booking_unit_nights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->date('night');

            // Hard guarantee against double booking: one unit can hold one night only once.
            $table->unique(['unit_id', 'night']);
        });

        $this->backfillOccupiedNights();
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_unit_nights');
    }

    /**
     * Existing bookings that still hold units get their nights. insertOrIgnore keeps the migration
     * running if legacy data already overlaps; such overlaps stay visible through app:db-health.
     */
    private function backfillOccupiedNights(): void
    {
        $now = CarbonImmutable::now()->toDateTimeString();

        DB::table('booking_units')
            ->join('bookings', 'bookings.id', '=', 'booking_units.booking_id')
            ->where(function ($query) use ($now) {
                $query->whereIn('bookings.status', self::OCCUPYING_STATUSES)
                    ->orWhere(fn ($pending) => $pending->where('bookings.status', 'pending_payment')
                        ->where('bookings.hold_expires_at', '>', $now));
            })
            ->orderBy('booking_units.id')
            ->select('booking_units.id', 'booking_units.unit_id', 'booking_units.check_in', 'booking_units.check_out')
            ->chunk(200, function ($lines) {
                foreach ($lines as $line) {
                    $nights = CarbonPeriod::create(
                        CarbonImmutable::parse($line->check_in)->startOfDay(),
                        CarbonImmutable::parse($line->check_out)->startOfDay()->subDay(),
                    );

                    $rows = [];

                    foreach ($nights as $night) {
                        $rows[] = ['booking_unit_id' => $line->id, 'unit_id' => $line->unit_id, 'night' => $night->toDateString()];
                    }

                    DB::table('booking_unit_nights')->insertOrIgnore($rows);
                }
            });
    }
};
