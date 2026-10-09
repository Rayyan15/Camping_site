<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evaluation_criterias')) {
            return;
        }

        Schema::table('evaluation_criterias', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluation_criterias', 'is_attendance')) {
                $table->boolean('is_attendance')->default(false);
            }

            if (! Schema::hasColumn('evaluation_criterias', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
    }

    public function down(): void
    {
        foreach (['is_attendance', 'is_active'] as $column) {
            if (Schema::hasColumn('evaluation_criterias', $column)) {
                Schema::table('evaluation_criterias', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
