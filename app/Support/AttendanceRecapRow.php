<?php

namespace App\Support;

final readonly class AttendanceRecapRow
{
    public function __construct(
        public int $employeeId,
        public string $employeeName,
        public int $workdays,
        public int $present,
        public int $late,
        public int $excused,
        public int $sick,
        public int $absent,
        public ?float $attendancePercentage,
    ) {}
}
