<?php

namespace Tests\Feature;

use App\Enums\CleaningLogStatus;
use App\Filament\Resources\CleaningLogs\CleaningLogResource;
use App\Filament\Resources\CleaningLogs\Pages\CreateCleaningLog;
use App\Models\CleaningLog;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CleaningRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function cleaner(): User
    {
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());

        $user = User::create(['name' => 'OB', 'email' => 'ob'.Str::random(5).'@example.test', 'password' => Str::random(24), 'is_active' => true]);
        $user->assignRole(User::ROLE_CLEANING);

        return $user;
    }

    public function test_cleaner_can_file_cleaning_reports_and_nothing_else(): void
    {
        $this->actingAs($this->cleaner());

        $this->get(CleaningLogResource::getUrl('index'))->assertOk();
        $this->get(CleaningLogResource::getUrl('create'))->assertOk();

        foreach (['/admin/bookings', '/admin/orders', '/admin/antrian-dapur', '/admin/order-walk-in', '/admin/laporan', '/admin/settings', '/admin/users', '/admin/menu-items', '/admin/payments'] as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_cleaner_lands_on_the_cleaning_list_instead_of_the_dashboard(): void
    {
        $this->actingAs($this->cleaner());

        $this->get('/admin')->assertRedirect(CleaningLogResource::getUrl('index'));
    }

    public function test_cleaner_submits_a_report_with_a_photo_that_waits_for_review(): void
    {
        Storage::fake('local');
        $this->actingAs($this->cleaner());
        $type = UnitType::create(['name' => 'Dome', 'slug' => 'dome', 'capacity' => 4, 'base_price_weekday' => 500000, 'base_price_weekend' => 700000]);
        $unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'DOM-01', 'status' => 'available']);
        $employee = Employee::create(['name' => 'Reno', 'position' => 'OB']);

        Livewire::test(CreateCleaningLog::class)
            ->fillForm([
                'unit_id' => $unit->id,
                'employee_id' => $employee->id,
                'cleaned_at' => now()->toDateTimeString(),
                'photo_path' => UploadedFile::fake()->image('tenda.jpg'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $log = CleaningLog::sole();
        $this->assertSame(CleaningLogStatus::Pending, $log->status);
        Storage::disk('local')->assertExists($log->photo_path);
    }
}
