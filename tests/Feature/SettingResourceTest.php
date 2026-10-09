<?php

namespace Tests\Feature;

use App\Enums\SettingKey;
use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Filament\Resources\Settings\SettingResource;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BookingService;
use App\Services\SettingRepository;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SettingResourceTest extends TestCase
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

    private function values(array $overrides = []): array
    {
        return array_merge([
            'tax_rate_percent' => 10,
            'check_in_time' => '15:00',
            'check_out_time' => '11:00',
            'down_payment_percent' => 30,
        ], $overrides);
    }

    public function test_owner_saves_settings_and_repository_reads_them(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));

        Livewire::test(ManageSettings::class)
            ->fillForm($this->values())
            ->call('save')
            ->assertHasNoFormErrors();

        $repository = app(SettingRepository::class);
        $this->assertSame(0.1, $repository->taxRate());
        $this->assertSame('15:00', $repository->checkInTime());
        $this->assertSame('11:00', $repository->checkOutTime());
        $this->assertSame(30, $repository->downPaymentPercent());
        $this->assertSame('0.1', Setting::where('key', SettingKey::TaxRate->value)->value('value'));
    }

    public function test_form_is_prefilled_with_defaults_when_nothing_is_stored(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));

        Livewire::test(ManageSettings::class)->assertFormSet([
            'tax_rate_percent' => 11,
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'down_payment_percent' => 0,
        ]);
    }

    public function test_out_of_range_and_missing_values_are_rejected(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));

        Livewire::test(ManageSettings::class)
            ->fillForm($this->values(['tax_rate_percent' => 101, 'down_payment_percent' => -1, 'check_in_time' => null]))
            ->call('save')
            ->assertHasFormErrors(['tax_rate_percent', 'down_payment_percent', 'check_in_time']);

        $this->assertSame(0, Setting::count());
    }

    public function test_saved_tax_changes_the_booking_price(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        $type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 2,
            'base_price_weekday' => 100000, 'base_price_weekend' => 150000,
        ]);
        $unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'D1', 'status' => 'active']);
        $customer = Customer::create(['name' => 'Tamu', 'phone' => '0811']);

        Livewire::test(ManageSettings::class)
            ->fillForm($this->values(['tax_rate_percent' => 0]))
            ->call('save');

        $booking = app(BookingService::class)->createBooking(
            $customer->id, now()->addDays(30)->toDateString(), now()->addDays(31)->toDateString(), 2, [$unit->id], [],
        );

        $this->assertSame(0, $booking->tax);
    }

    public function test_cache_is_refreshed_after_a_direct_change(): void
    {
        $repository = app(SettingRepository::class);
        $this->assertSame(0.11, $repository->taxRate());

        Setting::create(['key' => SettingKey::TaxRate->value, 'value' => '0.05']);
        $this->assertSame(0.05, $repository->taxRate());

        Setting::where('key', SettingKey::TaxRate->value)->first()->update(['value' => '0.2']);
        $this->assertSame(0.2, $repository->taxRate());
    }

    public function test_changes_are_written_to_the_activity_log(): void
    {
        $owner = $this->userWithRole(User::ROLE_OWNER);
        $this->actingAs($owner);

        Livewire::test(ManageSettings::class)->fillForm($this->values())->call('save');

        $this->assertTrue(ActivityLog::where('subject_type', 'setting')->where('user_id', $owner->id)->exists());
    }

    public function test_front_office_and_cashier_cannot_access_settings(): void
    {
        foreach ([User::ROLE_FRONT_OFFICE, User::ROLE_CASHIER] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->assertFalse(SettingResource::canViewAny());
            Livewire::test(ManageSettings::class)->assertForbidden();
        }
    }
}
