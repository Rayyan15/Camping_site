<?php

namespace App\Services;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Support\AttendanceSyncReport;
use App\Support\FingerprintLogEntry;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Turns raw punches into one Attendance row per employee per work date.
 *
 * First punch of the day is the clock in, last punch is the clock out. Shifts that cross midnight
 * assign early-morning punches (before shift end plus a grace window) to the previous work date.
 * Days an owner entered by hand are never overwritten.
 */
class AttendanceSyncService
{
    public function importFile(string $path, CarbonInterface $from, CarbonInterface $to): AttendanceSyncReport
    {
        $source = new FileFingerprintLogSource($path);
        $report = $this->sync($source->entries($from, $to));
        $report->malformedLines = $source->malformedLines();

        return $report;
    }

    /**
     * @param  iterable<FingerprintLogEntry>  $entries
     */
    public function sync(iterable $entries): AttendanceSyncReport
    {
        $report = new AttendanceSyncReport;
        $employees = Employee::query()->whereNotNull('fingerprint_id')->get()->keyBy(fn (Employee $e) => trim($e->fingerprint_id));
        $shifts = Shift::query()->get()->keyBy('id');

        $punchesByDay = $this->groupPunches($entries, $employees, $shifts, $report);

        foreach ($punchesByDay as $date => $byEmployee) {
            DB::transaction(function () use ($date, $byEmployee, $employees, $shifts, $report) {
                foreach ($byEmployee as $employeeId => $punches) {
                    $employee = $employees->firstWhere('id', $employeeId);
                    $this->recordDay($employee, $shifts->get($employee->shift_id), $date, $punches, $report);
                }
            });
        }

        return $report;
    }

    /**
     * @param  iterable<FingerprintLogEntry>  $entries
     * @return array<string, array<int, list<CarbonImmutable>>> date => employee id => sorted punches
     */
    private function groupPunches(iterable $entries, $employees, $shifts, AttendanceSyncReport $report): array
    {
        $grouped = [];

        foreach ($entries as $entry) {
            $report->rowsRead++;
            $employee = $employees->get($entry->fingerprintId);

            if ($employee === null) {
                $report->unknownFingerprintIds[$entry->fingerprintId] = ($report->unknownFingerprintIds[$entry->fingerprintId] ?? 0) + 1;

                continue;
            }

            $date = $this->workDate($entry->punchedAt, $shifts->get($employee->shift_id))->toDateString();
            $grouped[$date][$employee->id][] = $entry->punchedAt;
        }

        ksort($grouped);

        foreach ($grouped as &$byEmployee) {
            foreach ($byEmployee as &$punches) {
                usort($punches, fn (CarbonImmutable $a, CarbonImmutable $b) => $a <=> $b);
            }
        }

        return $grouped;
    }

    /**
     * @param  list<CarbonImmutable>  $punches
     */
    private function recordDay(Employee $employee, ?Shift $shift, string $date, array $punches, AttendanceSyncReport $report): void
    {
        $existing = Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->lockForUpdate()
            ->first();

        if ($existing?->source === AttendanceSource::Manual) {
            $report->protectedDays++;

            return;
        }

        $first = $punches[0];
        $last = end($punches);
        $hasClockOut = $first->diffInMinutes($last, true) >= config('attendance.min_clock_out_gap_minutes');

        $attributes = [
            'clock_in' => $first->format('H:i:s'),
            'clock_out' => $hasClockOut ? $last->format('H:i:s') : null,
            'status' => $this->statusFor($first, $shift, $date)->value,
            'source' => AttendanceSource::Fingerprint->value,
            'note' => null,
        ];

        if ($shift === null && ! in_array($employee->name, $report->employeesWithoutShift, true)) {
            $report->employeesWithoutShift[] = $employee->name;
        }

        if ($existing === null) {
            Attendance::create(['employee_id' => $employee->id, 'date' => $date] + $attributes);
            $report->created++;

            return;
        }

        $existing->fill($attributes);
        if ($existing->isDirty()) {
            $existing->save();
            $report->updated++;
        } else {
            $report->unchanged++;
        }
    }

    private function statusFor(CarbonImmutable $firstPunch, ?Shift $shift, string $date): AttendanceStatus
    {
        if ($shift === null) {
            return AttendanceStatus::Present;
        }

        $lateAfter = CarbonImmutable::parse($date, config('app.timezone'))
            ->setTimeFromTimeString($shift->start_time)
            ->addMinutes((int) $shift->late_tolerance_minutes);

        return $firstPunch->gt($lateAfter) ? AttendanceStatus::Late : AttendanceStatus::Present;
    }

    private function workDate(CarbonImmutable $punch, ?Shift $shift): CarbonImmutable
    {
        if ($shift === null || ! $this->crossesMidnight($shift)) {
            return $punch->startOfDay();
        }

        $earlyUntil = $punch->startOfDay()
            ->setTimeFromTimeString($shift->end_time)
            ->addMinutes((int) config('attendance.overnight_checkout_grace_minutes'));

        return $punch->lt($earlyUntil) ? $punch->subDay()->startOfDay() : $punch->startOfDay();
    }

    private function crossesMidnight(Shift $shift): bool
    {
        return Carbon::parse($shift->end_time)->secondsSinceMidnight() < Carbon::parse($shift->start_time)->secondsSinceMidnight();
    }
}
