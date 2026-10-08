<?php

namespace Tests\Feature;

use App\Enums\CleaningLogStatus;
use App\Models\CleaningLog;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\UnitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleaningLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleaning_log_is_stored_for_an_employee_with_status(): void
    {
        $unitType = UnitType::create([
            'name' => 'Dome',
            'slug' => 'dome',
            'capacity' => 4,
            'base_price_weekday' => 500000,
            'base_price_weekend' => 700000,
        ]);
        $unit = Unit::create(['unit_type_id' => $unitType->id, 'code' => 'D-01', 'status' => 'available']);
        $employee = Employee::create(['name' => 'Rina', 'position' => 'Housekeeping']);

        $log = CleaningLog::create([
            'unit_id' => $unit->id,
            'employee_id' => $employee->id,
            'cleaned_at' => now(),
            'photo_path' => 'cleaning_logs/d-01.jpg',
        ]);

        $this->assertSame(CleaningLogStatus::Pending, $log->fresh()->status);

        $log->update(['status' => CleaningLogStatus::Approved]);

        $this->assertSame(CleaningLogStatus::Approved, $log->fresh()->status);
        $this->assertTrue($employee->cleaningLogs->contains($log));
    }
}
