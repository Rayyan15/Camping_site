<?php

namespace Tests\Feature;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\BookingChart;
use App\Filament\Widgets\DashboardStats;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Refund;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\AccessSynchronizer;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(string $role, bool $active = true): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.Str::random(6).'@example.test',
            'password' => Str::random(24),
            'is_active' => $active,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_matrix_grants_each_role_only_its_prd_abilities(): void
    {
        $owner = $this->userWithRole(User::ROLE_OWNER);
        $frontOffice = $this->userWithRole(User::ROLE_FRONT_OFFICE);
        $cashier = $this->userWithRole(User::ROLE_CASHIER);

        $this->assertTrue($owner->can('update', new UnitType));
        $this->assertTrue($owner->can('viewAny', User::class));

        $this->assertTrue($frontOffice->can('update', new Booking));
        $this->assertTrue($frontOffice->can('create', Refund::class));
        $this->assertFalse($frontOffice->can('update', new UnitType));
        $this->assertFalse($frontOffice->can('create', Unit::class));
        $this->assertFalse($frontOffice->can('viewAny', User::class));

        $this->assertTrue($cashier->can('viewAny', Booking::class));
        $this->assertFalse($cashier->can('update', new Booking));
        $this->assertFalse($cashier->can('viewAny', Refund::class));
        $this->assertTrue($cashier->can('process_orders'));
        $this->assertTrue($cashier->can('toggle_menu_availability'));
        $this->assertFalse($cashier->can('manage_menu'));
        $this->assertFalse($cashier->can('view_financials'));
    }

    public function test_owner_only_functions_are_denied_to_operators(): void
    {
        $owner = $this->userWithRole(User::ROLE_OWNER);

        foreach ([User::ROLE_FRONT_OFFICE, User::ROLE_CASHIER] as $role) {
            $operator = $this->userWithRole($role);

            foreach (['approve_refund', 'view_reports', 'view_financials', 'manage_settings', 'manage_users', 'view_activity_log', 'evaluate_employees', 'manage_employees'] as $permission) {
                $this->assertTrue($owner->can($permission));
                $this->assertFalse($operator->can($permission), "$role must not hold $permission");
            }
        }
    }

    public function test_only_front_office_and_owner_request_refunds(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_FRONT_OFFICE));
        $this->assertTrue(RefundResource::canCreate());

        $this->actingAs($this->userWithRole(User::ROLE_CASHIER));
        $this->assertFalse(RefundResource::canCreate());
    }

    public function test_user_and_activity_log_resources_are_owner_only(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(ActivityLogResource::canViewAny());
        $this->assertFalse(ActivityLogResource::canCreate());

        $this->actingAs($this->userWithRole(User::ROLE_FRONT_OFFICE));
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(ActivityLogResource::canViewAny());
    }

    public function test_audit_log_entries_cannot_be_edited_by_anyone(): void
    {
        $owner = $this->userWithRole(User::ROLE_OWNER);

        $this->assertFalse($owner->can('update', new ActivityLog));
        $this->assertFalse($owner->can('delete', new ActivityLog));
    }

    public function test_owner_cannot_delete_own_account(): void
    {
        $owner = $this->userWithRole(User::ROLE_OWNER);
        $other = $this->userWithRole(User::ROLE_CASHIER);

        $this->assertFalse($owner->can('delete', $owner));
        $this->assertTrue($owner->can('delete', $other));
    }

    public function test_panel_access_requires_an_active_account_with_a_panel_role(): void
    {
        $panel = Filament::getPanel('admin');

        foreach (User::PANEL_ROLES as $role) {
            $this->assertTrue($this->userWithRole($role)->canAccessPanel($panel));
            $this->assertFalse($this->userWithRole($role, active: false)->canAccessPanel($panel));
        }

        $roleless = User::create(['name' => 'None', 'email' => 'none@example.test', 'password' => Str::random(24)]);
        $this->assertFalse($roleless->canAccessPanel($panel));
    }

    public function test_deactivated_user_is_logged_out_on_the_next_request(): void
    {
        $user = $this->userWithRole(User::ROLE_FRONT_OFFICE);
        $this->actingAs($user)->get('/admin')->assertSuccessful();

        $user->update(['is_active' => false]);

        $this->get('/admin')->assertRedirect();
        $this->assertGuest();
    }

    public function test_revenue_widgets_follow_the_financial_permission(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_CASHIER));
        $this->assertFalse(DashboardStats::canView());
        $this->assertFalse(BookingChart::canView());

        $this->actingAs($this->userWithRole(User::ROLE_FRONT_OFFICE));
        $this->assertTrue(DashboardStats::canView());
        Livewire::test(DashboardStats::class)
            ->assertSee('Okupansi')
            ->assertDontSee('Pendapatan Camping');

        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        Livewire::test(DashboardStats::class)->assertSee('Pendapatan Camping');
    }

    public function test_cashier_does_not_see_customer_contact_columns(): void
    {
        Customer::create(['name' => 'Tamu', 'phone' => '081234567890', 'email' => 'tamu@example.test']);

        $this->actingAs($this->userWithRole(User::ROLE_CASHIER));
        Livewire::test(ListCustomers::class)
            ->assertTableColumnHidden('phone')
            ->assertTableColumnHidden('email');

        $this->actingAs($this->userWithRole(User::ROLE_FRONT_OFFICE));
        Livewire::test(ListCustomers::class)
            ->assertTableColumnVisible('phone')
            ->assertTableColumnVisible('email');
    }

    public function test_owner_without_two_factor_is_sent_to_set_it_up_when_required(): void
    {
        config(['access.owner_two_factor_required' => true]);

        $this->actingAs($this->userWithRole(User::ROLE_OWNER))
            ->get('/admin')
            ->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }

    public function test_two_factor_requirement_does_not_apply_to_operators(): void
    {
        config(['access.owner_two_factor_required' => true]);

        $this->actingAs($this->userWithRole(User::ROLE_FRONT_OFFICE))
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_sync_is_idempotent_and_moves_legacy_roles_without_promoting_anyone(): void
    {
        $legacyRoles = ['operator', 'finance'];
        foreach ($legacyRoles as $name) {
            Role::create(['name' => $name]);
        }
        $finance = User::create(['name' => 'Fin', 'email' => 'fin@example.test', 'password' => Str::random(24)]);
        $finance->assignRole('finance');

        app(AccessSynchronizer::class)->sync();
        app(AccessSynchronizer::class)->sync();

        $this->assertTrue($finance->fresh()->hasRole(User::ROLE_FRONT_OFFICE));
        $this->assertFalse($finance->fresh()->hasRole(User::ROLE_OWNER));
        $this->assertSame(0, Role::whereIn('name', $legacyRoles)->count());
        $this->assertSame(count(User::PANEL_ROLES), Role::count());
    }

    public function test_seeder_creates_no_accounts_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $this->assertSame(0, User::count());
    }

    public function test_seeder_creates_demo_accounts_in_local_without_hardcoded_password(): void
    {
        $this->app['env'] = 'local';
        config(['access.demo_password' => null]);

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $owner = User::where('email', 'owner@raynad.test')->firstOrFail();
        $this->assertTrue($owner->hasRole(User::ROLE_OWNER));
        $this->assertFalse(\Hash::check('password123', $owner->password));
    }

    public function test_create_owner_command_makes_an_owner_from_a_long_enough_password(): void
    {
        config(['access.owner_bootstrap_password' => Str::random(20)]);

        $this->artisan('app:create-owner', ['email' => 'boss@example.test', 'name' => 'Boss'])->assertSuccessful();

        $this->assertTrue(User::where('email', 'boss@example.test')->firstOrFail()->hasRole(User::ROLE_OWNER));
    }

    public function test_create_owner_command_rejects_short_passwords_and_duplicates(): void
    {
        config(['access.owner_bootstrap_password' => 'short']);
        $this->artisan('app:create-owner', ['email' => 'boss@example.test', 'name' => 'Boss'])->assertFailed();
        $this->assertSame(0, User::count());

        config(['access.owner_bootstrap_password' => Str::random(20)]);
        $this->artisan('app:create-owner', ['email' => 'boss@example.test', 'name' => 'Boss'])->assertSuccessful();
        $this->artisan('app:create-owner', ['email' => 'boss@example.test', 'name' => 'Boss'])->assertFailed();
    }
}
