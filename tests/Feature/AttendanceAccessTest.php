<?php

namespace Tests\Feature;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Filament\Pages\AttendanceRecap;
use App\Filament\Resources\Attendances\Pages\CreateAttendance;
use App\Filament\Resources\Attendances\Pages\ListAttendances;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
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

    private function recordFor(User $user, string $name): Attendance
    {
        $employee = Employee::create(['name' => $name, 'user_id' => $user->id]);

        return Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-10-05', 'status' => AttendanceStatus::Present->value,
            'source' => AttendanceSource::Fingerprint->value,
        ]);
    }

    public function test_owner_sees_every_employee(): void
    {
        $owner = $this->userWithRole(User::ROLE_OWNER);
        $fo = $this->recordFor($this->userWithRole(User::ROLE_FRONT_OFFICE), 'FO Staff');
        $cashier = $this->recordFor($this->userWithRole(User::ROLE_CASHIER), 'Kasir Staff');

        $this->actingAs($owner);

        Livewire::test(ListAttendances::class)->assertCanSeeTableRecords([$fo, $cashier]);
    }

    public function test_front_office_and_cashier_see_only_their_own_rows(): void
    {
        $foUser = $this->userWithRole(User::ROLE_FRONT_OFFICE);
        $cashierUser = $this->userWithRole(User::ROLE_CASHIER);
        $fo = $this->recordFor($foUser, 'FO Staff');
        $cashier = $this->recordFor($cashierUser, 'Kasir Staff');

        $this->actingAs($foUser);
        Livewire::test(ListAttendances::class)
            ->assertCanSeeTableRecords([$fo])
            ->assertCanNotSeeTableRecords([$cashier]);
        $this->assertFalse($foUser->can('view', $cashier));
        $this->assertTrue($foUser->can('view', $fo));

        $this->actingAs($cashierUser);
        Livewire::test(ListAttendances::class)
            ->assertCanSeeTableRecords([$cashier])
            ->assertCanNotSeeTableRecords([$fo]);
    }

    public function test_staff_cannot_edit_or_create_attendance(): void
    {
        $user = $this->userWithRole(User::ROLE_FRONT_OFFICE);
        $row = $this->recordFor($user, 'FO Staff');

        $this->assertFalse($user->can('update', $row));
        $this->assertFalse($user->can('create', Attendance::class));
        $this->assertFalse($user->can('delete', $row));
    }

    public function test_user_without_linked_employee_sees_an_empty_list(): void
    {
        $this->recordFor($this->userWithRole(User::ROLE_CASHIER), 'Orang Lain');
        $unlinked = $this->userWithRole(User::ROLE_FRONT_OFFICE);

        $this->actingAs($unlinked);

        Livewire::test(ListAttendances::class)->assertCountTableRecords(0);
    }

    public function test_owner_can_record_manual_sick_status_with_reason_and_it_is_marked_manual(): void
    {
        $owner = $this->userWithRole(User::ROLE_OWNER);
        $employee = Employee::create(['name' => 'Rina']);
        $this->actingAs($owner);

        Livewire::test(CreateAttendance::class)
            ->fillForm(['employee_id' => $employee->id, 'date' => '2026-10-06', 'status' => 'sakit', 'note' => ''])
            ->call('create')
            ->assertHasFormErrors(['note' => 'required']);

        Livewire::test(CreateAttendance::class)
            ->fillForm(['employee_id' => $employee->id, 'date' => '2026-10-06', 'status' => 'sakit', 'note' => 'Surat dokter'])
            ->call('create')
            ->assertHasNoFormErrors();

        $row = Attendance::first();
        $this->assertSame(AttendanceStatus::Sick, $row->status);
        $this->assertSame(AttendanceSource::Manual, $row->source);
    }

    public function test_owner_import_action_creates_rows_from_an_uploaded_file(): void
    {
        Storage::fake('local');
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        Employee::create(['name' => 'Rina', 'fingerprint_id' => '1']);
        $file = UploadedFile::fake()->createWithContent('attlog.dat', "1\t2026-10-05 08:00:00\t1\n1\t2026-10-05 17:00:00\t1\n42\t2026-10-05 08:00:00\t1\nrusak\n");

        Livewire::test(ListAttendances::class)
            ->callAction('importLog', ['file' => $file, 'from' => '2026-10-01', 'until' => '2026-10-31'])
            ->assertHasNoActionErrors();

        $this->assertSame(1, Attendance::count());
        $this->assertSame('17:00:00', Attendance::first()->clock_out);
    }

    public function test_import_action_is_hidden_from_staff(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_FRONT_OFFICE));

        Livewire::test(ListAttendances::class)->assertActionHidden('importLog');
    }

    public function test_recap_page_is_scoped_like_the_list(): void
    {
        $cashierUser = $this->userWithRole(User::ROLE_CASHIER);
        $this->recordFor($cashierUser, 'Kasir Staff');
        $this->recordFor($this->userWithRole(User::ROLE_FRONT_OFFICE), 'FO Staff');

        $this->actingAs($cashierUser);
        Livewire::test(AttendanceRecap::class)->assertSee('Kasir Staff')->assertDontSee('FO Staff');

        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        Livewire::test(AttendanceRecap::class)->assertSee('Kasir Staff')->assertSee('FO Staff');
    }
}
