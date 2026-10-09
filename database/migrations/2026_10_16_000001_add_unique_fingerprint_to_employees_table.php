<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'employees_fingerprint_id_unique';

    public function up(): void
    {
        if (! Schema::hasTable('employees') || Schema::hasIndex('employees', self::INDEX)) {
            return;
        }

        DB::table('employees')->where('fingerprint_id', '')->update(['fingerprint_id' => null]);

        // The earliest employee keeps the machine id; later duplicates are cleared so the owner re-enters them.
        $duplicated = DB::table('employees')
            ->whereNotNull('fingerprint_id')
            ->groupBy('fingerprint_id')
            ->havingRaw('count(*) > 1')
            ->pluck('fingerprint_id');

        foreach ($duplicated as $fingerprintId) {
            $keepId = DB::table('employees')->where('fingerprint_id', $fingerprintId)->min('id');

            DB::table('employees')
                ->where('fingerprint_id', $fingerprintId)
                ->where('id', '!=', $keepId)
                ->update(['fingerprint_id' => null]);
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('fingerprint_id', self::INDEX);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('employees') && Schema::hasIndex('employees', self::INDEX)) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique(self::INDEX);
            });
        }
    }
};
