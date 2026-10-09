<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Support\AttendanceRecapRow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Single source for attendance counts.
 *
 * Working days: every calendar day of the month from the day the employee record was created
 * up to today, minus config('attendance.off_weekdays'). The system holds no day-off roster, so this
 * is the most honest definition available. A working day with no attendance row counts as alpa.
 * Attendance percentage = (hadir + terlambat) / working days, capped at 100.
 */
class AttendanceRecapService
{
    /**
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, AttendanceRecapRow>
     */
    public function forMonth(CarbonInterface $month, Collection $employees): Collection
    {
        $start = CarbonImmutable::instance($month)->startOfMonth();
        $end = $start->endOfMonth();

        $rowsByEmployee = Attendance::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('employee_id');

        return $employees->map(fn (Employee $employee) => $this->buildRow(
            $employee,
            $rowsByEmployee->get($employee->id, collect()),
            $start,
            $end,
        ))->values();
    }

    public function forEmployee(Employee $employee, CarbonInterface $month): AttendanceRecapRow
    {
        return $this->forMonth($month, collect([$employee]))->first();
    }

    /**
     * @param  Collection<int, Attendance>  $records
     */
    private function buildRow(Employee $employee, Collection $records, CarbonImmutable $start, CarbonImmutable $end): AttendanceRecapRow
    {
        $workdays = $this->workdays($employee, $start, $end);
        $recordedWorkdays = $records->whereIn('date', $workdays)->count();
        $count = fn (AttendanceStatus $status) => $records->where('status', $status)->count();

        $present = $count(AttendanceStatus::Present);
        $late = $count(AttendanceStatus::Late);
        $absent = $count(AttendanceStatus::Absent) + max(0, count($workdays) - $recordedWorkdays);

        $percentage = $workdays === []
            ? null
            : round(min(100, ($present + $late) / count($workdays) * 100), 1);

        return new AttendanceRecapRow(
            $employee->id,
            $employee->name,
            count($workdays),
            $present,
            $late,
            $count(AttendanceStatus::Excused),
            $count(AttendanceStatus::Sick),
            $absent,
            $percentage,
        );
    }

    /** @return list<string> Y-m-d dates */
    private function workdays(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $from = $employee->created_at ? $start->max($employee->created_at->startOfDay()) : $start;
        $until = $end->min(CarbonImmutable::now(config('app.timezone'))->startOfDay());
        $off = config('attendance.off_weekdays');

        $days = [];
        for ($day = CarbonImmutable::instance($from); $day->lte($until); $day = $day->addDay()) {
            if (! in_array($day->dayOfWeekIso, $off, true)) {
                $days[] = $day->toDateString();
            }
        }

        return $days;
    }
}
