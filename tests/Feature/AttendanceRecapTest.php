<?php

namespace Tests\Feature;

use App\Contracts\AttendanceScoreSource;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceRecapScoreSource;
use App\Services\AttendanceRecapService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRecapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 12:00:00', 'Asia/Jakarta'));
    }

    private function employee(): Employee
    {
        $employee = Employee::create(['name' => 'Rina']);
        $employee->forceFill(['created_at' => '2026-09-01 00:00:00'])->save();

        return $employee;
    }

    private function mark(Employee $employee, string $date, AttendanceStatus $status): void
    {
        Attendance::create(['employee_id' => $employee->id, 'date' => $date, 'status' => $status->value]);
    }

    public function test_counts_each_status_and_treats_missing_working_days_as_absent(): void
    {
        $employee = $this->employee();
        $this->mark($employee, '2026-10-01', AttendanceStatus::Present);
        $this->mark($employee, '2026-10-02', AttendanceStatus::Present);
        $this->mark($employee, '2026-10-03', AttendanceStatus::Late);
        $this->mark($employee, '2026-10-04', AttendanceStatus::Excused);
        $this->mark($employee, '2026-10-05', AttendanceStatus::Sick);

        $row = app(AttendanceRecapService::class)->forEmployee($employee, CarbonImmutable::parse('2026-10-01'));

        $this->assertSame(11, $row->workdays);
        $this->assertSame(2, $row->present);
        $this->assertSame(1, $row->late);
        $this->assertSame(1, $row->excused);
        $this->assertSame(1, $row->sick);
        $this->assertSame(6, $row->absent);
        $this->assertSame(27.3, $row->attendancePercentage);
    }

    public function test_off_weekdays_config_excludes_days(): void
    {
        config(['attendance.off_weekdays' => [7]]);

        $row = app(AttendanceRecapService::class)->forEmployee($this->employee(), CarbonImmutable::parse('2026-10-01'));

        $this->assertSame(9, $row->workdays);
    }

    public function test_days_before_the_employee_existed_do_not_count(): void
    {
        $employee = Employee::create(['name' => 'Baru']);
        $employee->forceFill(['created_at' => '2026-10-09 08:00:00'])->save();

        $row = app(AttendanceRecapService::class)->forEmployee($employee, CarbonImmutable::parse('2026-10-01'));

        $this->assertSame(3, $row->workdays);
    }

    public function test_past_month_counts_all_days_and_future_month_has_no_percentage(): void
    {
        $employee = $this->employee();

        $september = app(AttendanceRecapService::class)->forEmployee($employee, CarbonImmutable::parse('2026-09-01'));
        $november = app(AttendanceRecapService::class)->forEmployee($employee, CarbonImmutable::parse('2026-11-01'));

        $this->assertSame(30, $september->workdays);
        $this->assertSame(0.0, $september->attendancePercentage);
        $this->assertSame(0, $november->workdays);
        $this->assertNull($november->attendancePercentage);
    }

    public function test_score_source_is_bound_and_returns_rounded_percentage(): void
    {
        $employee = $this->employee();
        foreach (range(1, 11) as $day) {
            $this->mark($employee, sprintf('2026-10-%02d', $day), AttendanceStatus::Present);
        }

        $source = app(AttendanceScoreSource::class);

        $this->assertInstanceOf(AttendanceRecapScoreSource::class, $source);
        $this->assertSame(100, $source->scoreFor($employee, CarbonImmutable::parse('2026-10-01')));
        $this->assertNull($source->scoreFor($employee, CarbonImmutable::parse('2026-11-01')));
    }
}
