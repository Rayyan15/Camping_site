<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing rows referenced users.id; they cannot be mapped to employees reliably.
        DB::table('cleaning_logs')->delete();

        Schema::table('cleaning_logs', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });

        Schema::table('cleaning_logs', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->string('status')->default('pending')->index()->after('cleaned_at');
        });
    }

    public function down(): void
    {
        DB::table('cleaning_logs')->delete();

        Schema::table('cleaning_logs', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });

        Schema::table('cleaning_logs', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
