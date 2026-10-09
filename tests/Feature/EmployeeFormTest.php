<?php

namespace Tests\Feature;

use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeFormTest extends TestCase
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

    private function makeShift(string $name = 'Shift Pagi'): Shift
    {
        return Shift::create(['name' => $name, 'start_time' => '07:00', 'end_time' => '15:00', 'late_tolerance_minutes' => 5]);
    }

    public function test_employee_relations_resolve(): void
    {
        $shift = $this->makeShift();
        $user = $this->userWithRole(User::ROLE_CASHIER);
        $employee = Employee::create(['name' => 'Ayu', 'shift_id' => $shift->id, 'user_id' => $user->id]);

        $this->assertTrue($employee->shift->is($shift));
        $this->assertTrue($employee->user->is($user));
    }

    public function test_owner_creates_employee_with_shift_and_login_account(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        $shift = $this->makeShift();
        $cashier = $this->userWithRole(User::ROLE_CASHIER);

        Livewire::test(CreateEmployee::class)
            ->fillForm([
                'name' => 'Ayu Lestari',
                'position' => 'Kasir',
                'shift_id' => $shift->id,
                'user_id' => $cashier->id,
                'fingerprint_id' => 'FP-001',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $employee = Employee::firstWhere('name', 'Ayu Lestari');
        $this->assertSame($shift->id, $employee->shift_id);
        $this->assertSame($cashier->id, $employee->user_id);
    }

    public function test_duplicate_fingerprint_is_rejected_but_own_value_can_be_resaved(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        Employee::create(['name' => 'Ayu', 'fingerprint_id' => 'FP-001']);
        $other = Employee::create(['name' => 'Bima', 'fingerprint_id' => 'FP-002']);

        Livewire::test(CreateEmployee::class)
            ->fillForm(['name' => 'Cici', 'fingerprint_id' => 'FP-001'])
            ->call('create')
            ->assertHasFormErrors(['fingerprint_id' => 'unique']);

        Livewire::test(EditEmployee::class, ['record' => $other->getKey()])
            ->fillForm(['fingerprint_id' => 'FP-002'])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_blank_fingerprint_is_stored_as_null_so_many_employees_can_omit_it(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));

        foreach (['Ayu', 'Bima'] as $name) {
            Livewire::test(CreateEmployee::class)
                ->fillForm(['name' => $name, 'fingerprint_id' => ''])
                ->call('create')
                ->assertHasNoFormErrors();
        }

        $this->assertSame(2, Employee::whereNull('fingerprint_id')->count());
    }

    public function test_database_enforces_unique_fingerprint(): void
    {
        Employee::create(['name' => 'Ayu', 'fingerprint_id' => 'FP-001']);

        $this->expectException(QueryException::class);
        Employee::create(['name' => 'Bima', 'fingerprint_id' => 'FP-001']);
    }

    public function test_login_account_cannot_be_linked_to_two_employees(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        $cashier = $this->userWithRole(User::ROLE_CASHIER);
        Employee::create(['name' => 'Ayu', 'user_id' => $cashier->id]);

        Livewire::test(CreateEmployee::class)
            ->fillForm(['name' => 'Bima', 'user_id' => $cashier->id])
            ->call('create')
            ->assertHasFormErrors(['user_id' => 'unique']);
    }

    public function test_table_shows_shift_name_and_role_label(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        $shift = $this->makeShift('Shift Siang');
        $cashier = $this->userWithRole(User::ROLE_CASHIER);
        $employee = Employee::create(['name' => 'Ayu', 'shift_id' => $shift->id, 'user_id' => $cashier->id]);

        Livewire::test(ListEmployees::class)
            ->assertCanSeeTableRecords([$employee])
            ->assertSee('Shift Siang')
            ->assertSee('Operator Kasir/Dapur');
    }

    public function test_only_owner_can_access_employees(): void
    {
        foreach ([User::ROLE_FRONT_OFFICE, User::ROLE_CASHIER] as $role) {
            $this->actingAs($this->userWithRole($role));
            Livewire::test(ListEmployees::class)->assertForbidden();
        }
    }
}
