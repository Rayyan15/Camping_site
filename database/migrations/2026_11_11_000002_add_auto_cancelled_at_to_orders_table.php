<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Set only when the system cancels an unpaid transfer order, so a late proof may reopen it
            // while an order staff cancelled stays closed.
            $table->timestamp('auto_cancelled_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('auto_cancelled_at');
        });
    }
};
