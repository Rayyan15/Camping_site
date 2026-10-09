<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'special_prices_unit_type_id_date_unique';

    public function up(): void
    {
        if (Schema::hasIndex('special_prices', self::INDEX)) {
            return;
        }

        // Keep the newest row (highest id) per unit type and date.
        $keepIds = DB::table('special_prices')
            ->selectRaw('MAX(id) as id')
            ->groupBy('unit_type_id', 'date')
            ->pluck('id');

        $removedIds = DB::table('special_prices')->whereNotIn('id', $keepIds)->pluck('id');
        DB::table('special_prices')->whereNotIn('id', $keepIds)->delete();

        Log::info('Migration add_unique_to_special_prices', ['rows_deleted' => $removedIds->count()]);
        Log::debug('Migration add_unique_to_special_prices deleted ids', ['ids' => $removedIds->all()]);

        Schema::table('special_prices', function (Blueprint $table) {
            $table->unique(['unit_type_id', 'date'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('special_prices', self::INDEX)) {
            Schema::table('special_prices', fn (Blueprint $table) => $table->dropUnique(self::INDEX));
        }
    }
};
