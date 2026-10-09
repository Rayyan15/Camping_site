<?php

namespace App\Services;

use App\Models\RefundPolicy;

/**
 * Plain-language version of the refund tiers for public pages. It reads the policy table
 * so the page cannot drift from what the system actually pays out.
 */
class RefundPolicySummary
{
    public function sentence(): ?string
    {
        $tiers = RefundPolicy::orderByDesc('min_days_before')->get();

        if ($tiers->isEmpty()) {
            return null;
        }

        $previousMin = null;

        return $tiers->map(function (RefundPolicy $tier) use (&$previousMin): string {
            $rule = $tier->min_days_before > 0
                ? "{$tier->min_days_before} hari atau lebih sebelum check-in"
                : "Kurang dari {$previousMin} hari sebelum check-in";
            $previousMin = $tier->min_days_before;
            $outcome = $tier->percent > 0 ? "dikembalikan {$tier->percent}%" : 'tidak ada pengembalian';

            return "{$rule}: {$outcome}.";
        })->implode(' ');
    }
}
