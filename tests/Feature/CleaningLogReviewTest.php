<?php

namespace Tests\Feature;

use App\Enums\CleaningLogStatus;
use App\Filament\Resources\CleaningLogs\Pages\ListCleaningLogs;
use App\Models\CleaningLog;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CleaningLogReviewTest extends TestCase
{
    use RefreshDatabase;

    private CleaningLog $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());

        $type = UnitType::create(['name' => 'Dome', 'slug' => 'dome', 'capacity' => 4, 'base_price_weekday' => 500000, 'base_price_weekend' => 700000]);
        $unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'D-01', 'status' => 'active']);
        $employee = Employee::create(['name' => 'Rina', 'position' => 'Housekeeping']);

        $this->log = CleaningLog::create([
            'unit_id' => $unit->id,
            'employee_id' => $employee->id,
            'cleaned_at' => now(),
            'photo_path' => 'cleaning_logs/d-01.jpg',
            'notes' => 'Sprei diganti',
        ]);
    }

    private function actingAsRole(string $role): void
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.Str::random(6).'@example.test',
            'password' => Str::random(24),
            'is_active' => true,
        ]);
        $user->assignRole($role);
        $this->actingAs($user);
    }

    public function test_owner_approves_a_pending_report(): void
    {
        $this->actingAsRole(User::ROLE_OWNER);

        Livewire::test(ListCleaningLogs::class)->callTableAction('approve', $this->log);

        $this->assertSame(CleaningLogStatus::Approved, $this->log->fresh()->status);
    }

    public function test_reject_keeps_the_reason_on_the_report(): void
    {
        $this->actingAsRole(User::ROLE_OWNER);

        Livewire::test(ListCleaningLogs::class)
            ->callTableAction('reject', $this->log, ['reason' => 'Lantai masih berpasir'])
            ->assertHasNoTableActionErrors();

        $log = $this->log->fresh();
        $this->assertSame(CleaningLogStatus::Rejected, $log->status);
        $this->assertStringContainsString('Lantai masih berpasir', $log->notes);
        $this->assertStringContainsString('Sprei diganti', $log->notes);
    }

    public function test_front_office_can_report_but_not_review(): void
    {
        $this->actingAsRole('operator_fo');

        Livewire::test(ListCleaningLogs::class)
            ->assertTableActionHidden('approve', $this->log)
            ->assertTableActionHidden('reject', $this->log);
    }

    public function test_a_reviewed_report_cannot_be_reviewed_again(): void
    {
        $this->actingAsRole(User::ROLE_OWNER);
        $this->log->update(['status' => CleaningLogStatus::Approved]);

        Livewire::test(ListCleaningLogs::class)->assertTableActionHidden('reject', $this->log);
    }
}
