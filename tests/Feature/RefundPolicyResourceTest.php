<?php

namespace Tests\Feature;

use App\Filament\Resources\RefundPolicies\Pages\CreateRefundPolicy;
use App\Filament\Resources\RefundPolicies\RefundPolicyResource;
use App\Models\RefundPolicy;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RefundPolicyResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());
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

    public function test_owner_adds_a_tier(): void
    {
        $this->actingAsRole(User::ROLE_OWNER);

        Livewire::test(CreateRefundPolicy::class)
            ->fillForm(['min_days_before' => 14, 'percent' => 100])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(100, RefundPolicy::firstWhere('min_days_before', 14)->percent);
    }

    public function test_duplicate_day_and_out_of_range_percent_are_rejected(): void
    {
        $this->actingAsRole(User::ROLE_OWNER);
        RefundPolicy::create(['min_days_before' => 7, 'percent' => 100]);

        Livewire::test(CreateRefundPolicy::class)
            ->fillForm(['min_days_before' => 7, 'percent' => 120])
            ->call('create')
            ->assertHasFormErrors(['min_days_before' => 'unique', 'percent']);
    }

    public function test_operators_cannot_open_the_tier_list(): void
    {
        $this->actingAsRole('operator_fo');

        $this->assertFalse(RefundPolicyResource::canViewAny());
    }
}
