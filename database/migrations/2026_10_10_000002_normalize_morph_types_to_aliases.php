<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Polymorphic type columns that stored fully qualified class names before the morph map was enforced.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'payments' => 'payable_type',
        'activity_logs' => 'subject_type',
        'model_has_roles' => 'model_type',
        'model_has_permissions' => 'model_type',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            foreach (config('morph_map') as $alias => $class) {
                DB::table($table)->where($column, $class)->update([$column => $alias]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            foreach (config('morph_map') as $alias => $class) {
                DB::table($table)->where($column, $alias)->update([$column => $class]);
            }
        }
    }
};
