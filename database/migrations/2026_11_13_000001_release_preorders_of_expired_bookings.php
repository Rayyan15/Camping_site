<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pre-orders of bookings that expired before this fix stayed in "baru" forever. They get the same
 * treatment ReleaseExpiredHolds now applies, including the flag that lets a revival restore them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->where('source', 'preorder')
            ->whereNotIn('status', ['selesai', 'dibatalkan'])
            ->whereIn('booking_id', DB::table('bookings')->select('id')->where('status', 'expired'))
            ->update(['status' => 'dibatalkan', 'auto_cancelled_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('orders')
            ->where('source', 'preorder')
            ->whereNotNull('auto_cancelled_at')
            ->whereIn('booking_id', DB::table('bookings')->select('id')->where('status', 'expired'))
            ->update(['status' => 'baru', 'auto_cancelled_at' => null]);
    }
};
