<?php

namespace Tests\Feature;

use App\Enums\SettingKey;
use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Filament\Resources\Settings\SettingResource;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingRepository;
use App\Support\BankAccount;
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
        $this->assertSame('15:00', $repository->checkInTime());
        $this->assertSame('11:00', $repository->checkOutTime());
        $this->assertSame(30, $repository->downPaymentPercent());
    }

    public function test_form_is_prefilled_with_defaults_when_nothing_is_stored(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));

        Livewire::test(ManageSettings::class)->assertFormSet([
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'down_payment_percent' => 0,
        ]);
    }

    public function test_out_of_range_and_missing_values_are_rejected(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));

        Livewire::test(ManageSettings::class)
            ->fillForm($this->values(['down_payment_percent' => -1, 'check_in_time' => null]))
            ->call('save')
            ->assertHasFormErrors(['down_payment_percent', 'check_in_time']);

        $this->assertSame(0, Setting::count());
    }

    public function test_cache_is_refreshed_after_a_direct_change(): void
    {
        $repository = app(SettingRepository::class);
        $this->assertSame('14:00', $repository->checkInTime());

        Setting::create(['key' => SettingKey::CheckInTime->value, 'value' => '15:00']);
        $this->assertSame('15:00', $repository->checkInTime());

        Setting::where('key', SettingKey::CheckInTime->value)->first()->update(['value' => '16:00']);
        $this->assertSame('16:00', $repository->checkInTime());
    }

    public function test_changes_are_written_to_the_activity_log(): void
    {
        $owner = $this->userWithRole(User::ROLE_OWNER);
        $this->actingAs($owner);

        Livewire::test(ManageSettings::class)->fillForm($this->values())->call('save');

        $this->assertTrue(ActivityLog::where('subject_type', 'setting')->where('user_id', $owner->id)->exists());
    }

    public function test_owner_sets_the_bank_account_that_guests_see(): void
    {
        config(['site.bank' => ['name' => null, 'account_number' => null, 'account_holder' => null]]);
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        $this->assertFalse(BankAccount::current()->isComplete());

        Livewire::test(ManageSettings::class)
            ->fillForm($this->values(['bank_name' => 'BCA', 'bank_account_number' => '123 456 7890', 'bank_account_holder' => 'PT Raynad Cipta Makmur']))
            ->call('save')
            ->assertHasNoFormErrors();

        $account = BankAccount::current();
        $this->assertTrue($account->isComplete());
        $this->assertSame('123 456 7890', $account->accountNumber);
    }

    public function test_bank_account_number_rejects_letters(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));

        Livewire::test(ManageSettings::class)
            ->fillForm($this->values(['bank_account_number' => 'abc123']))
            ->call('save')
            ->assertHasFormErrors(['bank_account_number' => 'regex']);
    }

    public function test_empty_setting_falls_back_to_the_env_account(): void
    {
        config(['site.bank' => ['name' => 'Mandiri', 'account_number' => '999', 'account_holder' => 'Halimun']]);

        $this->assertSame('Mandiri', BankAccount::current()->bankName);
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
