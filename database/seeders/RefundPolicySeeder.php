<?php

namespace Database\Seeders;

use App\Models\RefundPolicy;
use Illuminate\Database\Seeder;

class RefundPolicySeeder extends Seeder
{
    /**
     * Refund tiers by days before check-in (client answer, 10 Oct 2026): 7 or more days is 100%,
     * fewer than 7 days is 0%. The earlier 3-day, 50% tier is removed on re-seed.
     */
    private const TIERS = [
        ['min_days_before' => 7, 'percent' => 100],
        ['min_days_before' => 0, 'percent' => 0],
    ];

    private const RETIRED_MIN_DAYS = [3];

    public function run(): void
    {
        RefundPolicy::whereIn('min_days_before', self::RETIRED_MIN_DAYS)->where('percent', 50)->delete();

        foreach (self::TIERS as $tier) {
            RefundPolicy::firstOrCreate($tier);
        }
    }
}
