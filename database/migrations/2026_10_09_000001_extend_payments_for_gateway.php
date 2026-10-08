<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('direction')->default('in')->after('payable_id');
            $table->json('raw_payload')->nullable()->after('proof_path');
            // Nullable columns allow many NULLs, so manual payments without a gateway reference are fine.
            $table->unique('gateway_ref');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['gateway_ref']);
            $table->dropColumn(['direction', 'raw_payload']);
        });
    }
};
