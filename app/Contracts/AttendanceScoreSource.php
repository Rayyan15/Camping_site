<?php

namespace App\Contracts;

use App\Models\Employee;
use Carbon\CarbonInterface;

/**
 * Supplies the automatic attendance score (0-100) for one employee in one month (FR-64).
 * Returning null means no data, so the owner fills the score by hand.
 */
interface AttendanceScoreSource
{
    public function scoreFor(Employee $employee, CarbonInterface $month): ?int;
}
