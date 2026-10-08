<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('booking_id');
            $table->string('customer_phone', 32)->nullable()->after('customer_name');
            $table->index(['source', 'status']);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('is_available');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['source', 'status']);
            $table->dropColumn(['customer_name', 'customer_phone']);
        });
    }
};
