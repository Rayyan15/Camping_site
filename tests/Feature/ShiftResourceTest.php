<?php

namespace Tests\Feature;

use App\Exceptions\ReferencedSetupRecordException;
use App\Filament\Resources\Shifts\Pages\CreateShift;
use App\Filament\Resources\Shifts\Pages\EditShift;
use App\Filament\Resources\Shifts\Pages\ListShifts;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ShiftResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());
    }

    private function userWithRole(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.Str::random(6).'@example.test',
            'password' => Str::random(24),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function actingAsOwner(): User
    {
        $owner = $this->userWithRole(User::ROLE_OWNER);
        $this->actingAs($owner);

        return $owner;
    }

    public function test_owner_creates_a_shift(): void
    {
        $this->actingAsOwner();

        Livewire::test(CreateShift::class)
            ->fillForm([
                'name' => 'Shift Pagi',
                'start_time' => '07:00:00',
                'end_time' => '15:00:00',
                'late_tolerance_minutes' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $shift = Shift::firstWhere('name', 'Shift Pagi');
        $this->assertSame(10, (int) $shift->late_tolerance_minutes);
        $this->assertFalse($shift->crossesMidnight());
    }

    public function test_shift_may_end_after_midnight(): void
    {
        $this->actingAsOwner();

        Livewire::test(CreateShift::class)
            ->fillForm([
                'name' => 'Shift Malam',
                'start_time' => '22:00:00',
                'end_time' => '06:00:00',
                'late_tolerance_minutes' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Shift::firstWhere('name', 'Shift Malam')->crossesMidnight());
    }

    public function test_invalid_tolerance_and_equal_times_are_rejected(): void
    {
        $this->actingAsOwner();

        foreach ([-1, 121] as $tolerance) {
            Livewire::test(CreateShift::class)
                ->fillForm([
                    'name' => 'Shift',
                    'start_time' => '08:00:00',
                    'end_time' => '16:00:00',
                    'late_tolerance_minutes' => $tolerance,
                ])
                ->call('create')
                ->assertHasFormErrors(['late_tolerance_minutes']);
        }

        Livewire::test(CreateShift::class)
            ->fillForm([
                'name' => 'Shift',
                'start_time' => '08:00:00',
                'end_time' => '08:00:00',
                'late_tolerance_minutes' => 5,
            ])
            ->call('create')
            ->assertHasFormErrors(['end_time' => 'different']);

        $this->assertSame(0, Shift::count());
    }

    public function test_table_lists_shifts_with_employee_count(): void
    {
        $this->actingAsOwner();
        $shift = Shift::create(['name' => 'Shift Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'late_tolerance_minutes' => 5]);
        Employee::create(['name' => 'Ayu', 'shift_id' => $shift->id]);
        Employee::create(['name' => 'Bima', 'shift_id' => $shift->id]);

        Livewire::test(ListShifts::class)
            ->assertCanSeeTableRecords([$shift])
            ->assertTableColumnStateSet('employees_count', 2, $shift)
            ->assertTableColumnStateSet('late_tolerance_minutes', 5, $shift);
    }

    public function test_shift_used_by_employees_cannot_be_deleted(): void
    {
        $owner = $this->actingAsOwner();
        $used = Shift::create(['name' => 'Dipakai', 'start_time' => '07:00', 'end_time' => '15:00', 'late_tolerance_minutes' => 0]);
        $free = Shift::create(['name' => 'Kosong', 'start_time' => '15:00', 'end_time' => '23:00', 'late_tolerance_minutes' => 0]);
        $employee = Employee::create(['name' => 'Ayu', 'shift_id' => $used->id]);

        $this->assertFalse($owner->can('delete', $used));
        $this->assertTrue($owner->can('delete', $free));

        try {
            $used->delete();
            $this->fail('Deleting a shift in use must throw.');
        } catch (ReferencedSetupRecordException $e) {
            $this->assertStringContainsString('masih dipakai karyawan', $e->getMessage());
        }

        $this->assertSame($used->id, $employee->fresh()->shift_id);

        Livewire::test(EditShift::class, ['record' => $used->getKey()])
            ->assertActionHidden('delete');

        $free->delete();
        $this->assertDatabaseMissing('shifts', ['id' => $free->id]);
    }

    public function test_only_owner_can_access_shifts(): void
    {
        foreach ([User::ROLE_FRONT_OFFICE, User::ROLE_CASHIER] as $role) {
            $user = $this->userWithRole($role);

            $this->assertFalse($user->can('viewAny', Shift::class));
            $this->assertFalse($user->can('create', Shift::class));

            $this->actingAs($user);
            Livewire::test(ListShifts::class)->assertForbidden();
        }

        $this->actingAsOwner();
        Livewire::test(ListShifts::class)->assertSuccessful();
    }
}
