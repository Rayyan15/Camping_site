<?php

namespace App\Services;

use App\Models\Evaluation;

/**
 * Single place for the weighted evaluation formula:
 * total = sum(score * weight) / sum(weight), rounded to 2 decimals.
 * Dividing by the weight actually scored keeps the result on a 0-100 scale
 * even when active criteria do not add up to exactly 100.
 */
class EvaluationScoreCalculator
{
    public const DECIMALS = 2;

    /**
     * @param  iterable<array{weight: int|float|string|null, score: int|float|string|null}>  $entries
     */
    public function calculate(iterable $entries): float
    {
        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($entries as $entry) {
            $weight = (float) ($entry['weight'] ?? 0);

            if ($weight <= 0 || ! is_numeric($entry['score'] ?? null)) {
                continue;
            }

            $weightedSum += (float) $entry['score'] * $weight;
            $weightTotal += $weight;
        }

        return $weightTotal > 0 ? round($weightedSum / $weightTotal, self::DECIMALS) : 0.0;
    }

    public function refreshTotal(Evaluation $evaluation): Evaluation
    {
        $entries = $evaluation->scores()->with('criteria')->get()->map(fn ($score) => [
            'weight' => $score->criteria?->weight,
            'score' => $score->score,
        ]);

        $evaluation->update(['total_score' => $this->calculate($entries)]);

        return $evaluation;
    }
}
