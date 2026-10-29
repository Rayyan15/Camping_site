<?php

namespace Tests\Feature;

use App\Filament\Resources\UnitTypes\Pages\EditUnitType;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnitTypeFacilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_facilities_edited_in_the_panel_stay_a_list_and_the_landing_renders(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'secret-pass-123', 'is_active' => true]);
        $owner->assignRole(User::ROLE_OWNER);
        $this->actingAs($owner);

        $unitType = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'description' => 'Tenda', 'capacity' => 4,
            'facilities' => ['Kasur'], 'base_price_weekday' => 650000, 'base_price_weekend' => 800000,
        ]);

        Livewire::test(EditUnitType::class, ['record' => $unitType->getRouteKey()])
            ->fillForm(['facilities' => ['Kasur', 'Listrik', 'Grill']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Kasur', 'Listrik', 'Grill'], $unitType->fresh()->facilities);
        $this->get(route('home'))->assertOk()->assertSee('Grill');
    }

    public function test_facilities_are_capitalized_and_deduplicated_case_insensitively(): void
    {
        $this->assertSame(
            ['Grill', 'Air minum'],
            UnitType::normalizeFacilities(['Grill', ' grill', 'air minum', '', 'Air Minum']),
        );
    }
}
