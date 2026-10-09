<?php

use App\Enums\AddonUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Free-text units used to be accepted, so "malam" or "Per Malam" silently skipped the
     * per-night multiplier. Anything mentioning "malam" becomes per night; the rest per item.
     * Idempotent: rows already holding an enum value are left alone.
     */
    public function up(): void
    {
        $valid = array_column(AddonUnit::cases(), 'value');

        $changed = 0;

        // Filtered in PHP: MySQL's default collation compares case-insensitively, so a SQL
        // NOT IN would treat "Per Malam" as already valid and skip it.
        DB::table('addons')->get(['id', 'unit'])->reject(fn ($addon) => in_array($addon->unit, $valid, true))->each(function ($addon) use (&$changed) {
            $unit = str_contains(mb_strtolower((string) $addon->unit), 'malam')
                ? AddonUnit::PerNight
                : AddonUnit::PerItem;

            DB::table('addons')->where('id', $addon->id)->update(['unit' => $unit->value]);
            $changed++;
        });

        Log::info('Migration normalize_addon_units', ['rows_updated' => $changed]);
    }

    /**
     * The original free-text values cannot be recovered, and the normalized ones remain valid.
     */
    public function down(): void {}
};
