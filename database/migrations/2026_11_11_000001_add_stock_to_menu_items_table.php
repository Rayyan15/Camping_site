<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Null stock means the item is not counted. Remaining stock is this count minus what
            // live orders took after stock_counted_at, so cancelled or expired orders give it back.
            $table->unsignedInteger('stock')->nullable()->after('is_available');
            $table->timestamp('stock_counted_at')->nullable()->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['stock', 'stock_counted_at']);
        });
    }
};
