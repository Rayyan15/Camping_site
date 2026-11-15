<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('served_at')->nullable()->after('status');
        });

        // Orders already at the table before this column existed: their last update is the closest known serve time.
        DB::table('orders')->whereIn('status', ['diantar', 'selesai'])->update(['served_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('served_at');
        });
    }
};
