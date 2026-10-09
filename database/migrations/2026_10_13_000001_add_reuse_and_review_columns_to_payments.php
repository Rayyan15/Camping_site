<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->text('redirect_url')->nullable()->after('gateway_ref');
            $table->timestamp('expires_at')->nullable()->after('paid_at');
            $table->string('failure_reason')->nullable()->after('status');
            $table->string('review_reason')->nullable()->after('failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['redirect_url', 'expires_at', 'failure_reason', 'review_reason']);
        });
    }
};
