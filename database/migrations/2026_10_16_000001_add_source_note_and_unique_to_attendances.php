<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'attendances_employee_id_date_unique';

    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'source')) {
                $table->string('source')->default('manual')->after('status');
            }
            if (! Schema::hasColumn('attendances', 'note')) {
                $table->string('note', 255)->nullable()->after('source');
            }
        });

        $this->removeDuplicateRows();

        if (! Schema::hasIndex('attendances', self::UNIQUE_INDEX)) {
            Schema::table('attendances', fn (Blueprint $table) => $table->unique(['employee_id', 'date'], self::UNIQUE_INDEX));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('attendances', self::UNIQUE_INDEX)) {
            Schema::table('attendances', fn (Blueprint $table) => $table->dropUnique(self::UNIQUE_INDEX));
        }

        Schema::table('attendances', function (Blueprint $table) {
            foreach (['note', 'source'] as $column) {
                if (Schema::hasColumn('attendances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /** Keeps the oldest row of each employee and date pair; later duplicates carry no extra information. */
    private function removeDuplicateRows(): void
    {
        $pairs = DB::table('attendances')
            ->select('employee_id', 'date', DB::raw('MIN(id) as keep_id'))
            ->groupBy('employee_id', 'date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($pairs as $pair) {
            DB::table('attendances')
                ->where('employee_id', $pair->employee_id)
                ->where('date', $pair->date)
                ->where('id', '!=', $pair->keep_id)
                ->delete();
        }
    }
};
