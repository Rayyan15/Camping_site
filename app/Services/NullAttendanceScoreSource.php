<?php

namespace App\Services;

use App\Contracts\AttendanceScoreSource;
use App\Models\Employee;
use Carbon\CarbonInterface;

class NullAttendanceScoreSource implements AttendanceScoreSource
{
    public function scoreFor(Employee $employee, CarbonInterface $month): ?int
    {
        return null;
    }
}
