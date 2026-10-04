<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(\Database\Seeders\RoleSeeder::class);

        // Kebijakan refund (H+1 minggu / >7 hari = 100%)
        \App\Models\RefundPolicy::firstOrCreate([
            'min_days_before' => 7,
            'percent' => 100
        ]);
        \App\Models\RefundPolicy::firstOrCreate([
            'min_days_before' => 3,
            'percent' => 50
        ]);
        \App\Models\RefundPolicy::firstOrCreate([
            'min_days_before' => 0,
            'percent' => 0
        ]);
    }
}
