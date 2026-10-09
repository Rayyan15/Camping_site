<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const TOKEN_LENGTH = 40;

    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'access_token')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('access_token', self::TOKEN_LENGTH)->nullable()->unique()->after('code');
            });
        }

        // Safe to re-run: only rows that still lack a token are touched.
        DB::table('bookings')->whereNull('access_token')->orderBy('id')->each(function (object $booking) {
            DB::table('bookings')->where('id', $booking->id)->update([
                'access_token' => Str::random(self::TOKEN_LENGTH),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['access_token']);
            $table->dropColumn('access_token');
        });
    }
};
