<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Tax fixed when food is billed to a booking, so a later rate change never rewrites an old bill.
            // Null on older rows and on orders not billed to a booking.
            $table->unsignedInteger('tax')->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tax');
        });
    }
};
