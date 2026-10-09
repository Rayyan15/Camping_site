<?php

namespace Tests\Feature;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\AttendanceSyncService;
use App\Support\FingerprintLogEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSyncTest extends TestCase
{
    use RefreshDatabase;

    private function punch(string $id, string $at): FingerprintLogEntry
    {
        return new FingerprintLogEntry($id, CarbonImmutable::parse($at, 'Asia/Jakarta'));
    }

    private function dayShift(): Shift
    {
        return Shift::create(['name' => 'Pagi', 'start_time' => '08:00:00', 'end_time' => '16:00:00', 'late_tolerance_minutes' => 10]);
    }

    private function employee(string $fingerprintId, ?Shift $shift, string $name = 'Rina'): Employee
    {
        return Employee::create(['name' => $name, 'fingerprint_id' => $fingerprintId, 'shift_id' => $shift?->id]);
    }

    public function test_on_time_and_late_status_follow_shift_and_tolerance(): void
    {
        $shift = $this->dayShift();
        $this->employee('1', $shift, 'Tepat');
        $this->employee('2', $shift, 'Telat');

        app(AttendanceSyncService::class)->sync([
            $this->punch('1', '2026-10-05 08:10:00'),
            $this->punch('1', '2026-10-05 16:05:00'),
            $this->punch('2', '2026-10-05 08:11:00'),
        ]);

        $onTime = Attendance::whereHas('employee', fn ($q) => $q->where('name', 'Tepat'))->first();
        $late = Attendance::whereHas('employee', fn ($q) => $q->where('name', 'Telat'))->first();

        $this->assertSame(AttendanceStatus::Present, $onTime->status);
        $this->assertSame('08:10:00', $onTime->clock_in);
        $this->assertSame('16:05:00', $onTime->clock_out);
        $this->assertSame(AttendanceSource::Fingerprint, $onTime->source);
        $this->assertSame(AttendanceStatus::Late, $late->status);
        $this->assertNull($late->clock_out);
    }

    public function test_sync_is_idempotent(): void
    {
        $this->employee('1', $this->dayShift());
        $entries = [$this->punch('1', '2026-10-05 07:55:00'), $this->punch('1', '2026-10-05 17:00:00')];

        $first = app(AttendanceSyncService::class)->sync($entries);
        $second = app(AttendanceSyncService::class)->sync($entries);

        $this->assertSame(1, $first->created);
        $this->assertSame(0, $second->created);
        $this->assertSame(0, $second->updated);
        $this->assertSame(1, $second->unchanged);
        $this->assertSame(1, Attendance::count());
    }

    public function test_later_punch_updates_clock_out_on_resync(): void
    {
        $this->employee('1', $this->dayShift());
        app(AttendanceSyncService::class)->sync([$this->punch('1', '2026-10-05 07:55:00')]);

        $report = app(AttendanceSyncService::class)->sync([$this->punch('1', '2026-10-05 07:55:00'), $this->punch('1', '2026-10-05 16:30:00')]);

        $this->assertSame(1, $report->updated);
        $this->assertSame('16:30:00', Attendance::first()->clock_out);
    }

    public function test_unknown_fingerprint_ids_are_reported_not_fatal(): void
    {
        $this->employee('1', $this->dayShift());

        $report = app(AttendanceSyncService::class)->sync([
            $this->punch('1', '2026-10-05 07:55:00'),
            $this->punch('99', '2026-10-05 08:00:00'),
            $this->punch('99', '2026-10-05 16:00:00'),
            $this->punch('77', '2026-10-05 08:00:00'),
        ]);

        $this->assertSame(2, $report->unknownIdCount());
        $this->assertSame(['99' => 2, '77' => 1], $report->unknownFingerprintIds);
        $this->assertSame(4, $report->rowsRead);
        $this->assertSame(1, Attendance::count());
    }

    public function test_employee_without_shift_is_present_and_listed_in_report(): void
    {
        $this->employee('5', null, 'Tanpa Shift');

        $report = app(AttendanceSyncService::class)->sync([$this->punch('5', '2026-10-05 13:45:00')]);

        $this->assertSame(AttendanceStatus::Present, Attendance::first()->status);
        $this->assertSame(['Tanpa Shift'], $report->employeesWithoutShift);
    }

    public function test_manual_rows_are_never_overwritten(): void
    {
        $employee = $this->employee('1', $this->dayShift());
        Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-10-05', 'status' => AttendanceStatus::Sick->value,
            'source' => AttendanceSource::Manual->value, 'note' => 'Surat dokter',
        ]);

        $report = app(AttendanceSyncService::class)->sync([$this->punch('1', '2026-10-05 08:00:00')]);

        $this->assertSame(1, $report->protectedDays);
        $this->assertSame(1, $report->skipped());
        $this->assertSame(AttendanceStatus::Sick, Attendance::first()->status);
    }

    public function test_overnight_shift_assigns_morning_punch_to_previous_work_date(): void
    {
        $night = Shift::create(['name' => 'Malam', 'start_time' => '22:00:00', 'end_time' => '06:00:00', 'late_tolerance_minutes' => 5]);
        $this->employee('9', $night);

        app(AttendanceSyncService::class)->sync([
            $this->punch('9', '2026-10-05 21:58:00'),
            $this->punch('9', '2026-10-06 06:02:00'),
        ]);

        $row = Attendance::first();
        $this->assertSame('2026-10-05', $row->date);
        $this->assertSame('21:58:00', $row->clock_in);
        $this->assertSame('06:02:00', $row->clock_out);
        $this->assertSame(AttendanceStatus::Present, $row->status);
        $this->assertSame(1, Attendance::count());
    }

    public function test_unique_index_blocks_duplicate_employee_date(): void
    {
        $employee = $this->employee('1', null);
        $row = ['employee_id' => $employee->id, 'date' => '2026-10-05', 'status' => 'hadir'];
        Attendance::create($row);

        $this->expectException(QueryException::class);
        Attendance::create($row);
    }
}
