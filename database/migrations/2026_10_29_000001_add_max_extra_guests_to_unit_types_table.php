<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Capacity and extra guest allowance per tent type, from the owner on 29 Oct 2026. */
    private const OWNER_RULES = [
        'indian' => ['capacity' => 4, 'max_extra_guests' => 0],
        'safari' => ['capacity' => 4, 'max_extra_guests' => 0],
        'snail' => ['capacity' => 4, 'max_extra_guests' => 0],
        'dome' => ['capacity' => 4, 'max_extra_guests' => 2],
        'salak' => ['capacity' => 2, 'max_extra_guests' => 2],
        'pancar' => ['capacity' => 4, 'max_extra_guests' => 2],
    ];

    public function up(): void
    {
        Schema::table('unit_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_extra_guests')->default(0)->after('capacity');
        });

        foreach (self::OWNER_RULES as $slug => $rule) {
            DB::table('unit_types')->where('slug', $slug)->update($rule);
        }

        DB::table('addons')->where('name', 'Extra Bed')->update([
            'name' => 'Extra bed + sarapan 2 pax',
            'extra_guests' => 2,
        ]);

        if (! DB::table('addons')->where('name', 'Paket Curug + Track ATV (2 orang)')->exists()) {
            DB::table('addons')->insert([
                'name' => 'Paket Curug + Track ATV (2 orang)',
                'price' => 250000,
                'unit' => 'per item',
                'extra_guests' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('unit_types', function (Blueprint $table) {
            $table->dropColumn('max_extra_guests');
        });
    }
};
