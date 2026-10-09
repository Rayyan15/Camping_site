<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PHONE_INDEX = 'customers_phone_index';

    private const EMAIL_INDEX = 'customers_email_index';

    public function up(): void
    {
        if (! Schema::hasIndex('customers', self::PHONE_INDEX)) {
            Schema::table('customers', fn (Blueprint $table) => $table->index('phone', self::PHONE_INDEX));
        }

        if (! Schema::hasIndex('customers', self::EMAIL_INDEX)) {
            Schema::table('customers', fn (Blueprint $table) => $table->index('email', self::EMAIL_INDEX));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('customers', self::PHONE_INDEX)) {
            Schema::table('customers', fn (Blueprint $table) => $table->dropIndex(self::PHONE_INDEX));
        }

        if (Schema::hasIndex('customers', self::EMAIL_INDEX)) {
            Schema::table('customers', fn (Blueprint $table) => $table->dropIndex(self::EMAIL_INDEX));
        }
    }
};
