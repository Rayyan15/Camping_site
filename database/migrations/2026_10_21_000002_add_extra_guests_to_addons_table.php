<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            // Guests one unit of this add-on lets into the tent above its capacity (an extra bed is 1).
            $table->unsignedTinyInteger('extra_guests')->default(0)->after('unit');
        });

        // Until now every per-night add-on counted as an extra bed; keep that only for the extra bed itself.
        DB::table('addons')->whereRaw('LOWER(name) LIKE ?', ['%extra bed%'])->update(['extra_guests' => 1]);
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn('extra_guests');
        });
    }
};
