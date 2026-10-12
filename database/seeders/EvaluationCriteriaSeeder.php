<?php

namespace Database\Seeders;

use App\Models\EvaluationCriteria;
use Illuminate\Database\Seeder;

/**
 * Starting criteria from the client answer of 10 Oct 2026: Kehadiran, Kerapihan, Kesopanan with equal
 * weight (the remainder of 100 goes to the first). The owner can edit names and weights afterwards;
 * existing rows are never overwritten.
 */
class EvaluationCriteriaSeeder extends Seeder
{
    private const CRITERIA = [
        ['name' => 'Kehadiran', 'weight' => 34, 'is_attendance' => true],
        ['name' => 'Kerapihan', 'weight' => 33, 'is_attendance' => false],
        ['name' => 'Kesopanan', 'weight' => 33, 'is_attendance' => false],
    ];

    public function run(): void
    {
        foreach (self::CRITERIA as $criteria) {
            EvaluationCriteria::firstOrCreate(
                ['name' => $criteria['name']],
                ['weight' => $criteria['weight'], 'is_attendance' => $criteria['is_attendance'], 'is_active' => true],
            );
        }
    }
}
