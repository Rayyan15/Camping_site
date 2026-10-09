<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'evaluations_employee_id_period_unique';

    public function up(): void
    {
        if (! Schema::hasTable('evaluations') || Schema::hasIndex('evaluations', self::INDEX)) {
            return;
        }

        // Older duplicates are kept but renamed, because deleting an assessment would lose owner input.
        $duplicates = DB::table('evaluations')
            ->select('employee_id', 'period')
            ->groupBy('employee_id', 'period')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $keepId = DB::table('evaluations')
                ->where('employee_id', $duplicate->employee_id)
                ->where('period', $duplicate->period)
                ->max('id');

            $older = DB::table('evaluations')
                ->where('employee_id', $duplicate->employee_id)
                ->where('period', $duplicate->period)
                ->where('id', '!=', $keepId)
                ->pluck('id');

            foreach ($older as $id) {
                DB::table('evaluations')->where('id', $id)->update(['period' => $duplicate->period.'-dup'.$id]);
            }
        }

        Schema::table('evaluations', function (Blueprint $table) {
            $table->unique(['employee_id', 'period'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('evaluations') && Schema::hasIndex('evaluations', self::INDEX)) {
            Schema::table('evaluations', function (Blueprint $table) {
                $table->dropUnique(self::INDEX);
            });
        }
    }
};
