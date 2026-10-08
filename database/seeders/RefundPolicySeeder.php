<?php

namespace Database\Seeders;

use App\Models\RefundPolicy;
use Illuminate\Database\Seeder;

class RefundPolicySeeder extends Seeder
{
    /**
     * Refund tiers by days before check-in: 7 or more days is 100%, 3 or more is 50%, otherwise 0%.
     */
    private const TIERS = [
        ['min_days_before' => 7, 'percent' => 100],
        ['min_days_before' => 3, 'percent' => 50],
        ['min_days_before' => 0, 'percent' => 0],
    ];

    public function run(): void
    {
        foreach (self::TIERS as $tier) {
            RefundPolicy::firstOrCreate($tier);
        }
    }
}
