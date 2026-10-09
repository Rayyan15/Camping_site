<?php

namespace App\Services;

use App\Contracts\AttendanceScoreSource;
use App\Models\Employee;
use Carbon\CarbonInterface;

/** The attendance score is the monthly attendance percentage, rounded to a whole number. */
class AttendanceRecapScoreSource implements AttendanceScoreSource
{
    public function __construct(private readonly AttendanceRecapService $recap) {}

    public function scoreFor(Employee $employee, CarbonInterface $month): ?int
    {
        $percentage = $this->recap->forEmployee($employee, $month)->attendancePercentage;

        return $percentage === null ? null : (int) round($percentage);
    }
}
