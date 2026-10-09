<?php

namespace Tests\Feature;

use App\Filament\Resources\Units\Pages\CreateUnit;
use App\Filament\Resources\Units\Pages\EditUnit;
use App\Filament\Resources\UnitTypes\Pages\CreateUnitType;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class UnitFormValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner'.Str::random(6).'@example.test',
            'password' => Str::random(24),
            'is_active' => true,
        ]);
        $owner->assignRole(User::ROLE_OWNER);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getDefaultPanel());
    }

    private function makeType(string $slug = 'dome'): UnitType
    {
        return UnitType::create([
            'name' => 'Dome',
            'slug' => $slug,
            'capacity' => 2,
            'base_price_weekday' => 100000,
            'base_price_weekend' => 150000,
        ]);
    }

    public function test_slug_is_generated_from_name_and_manual_edit_is_kept(): void
    {
        Livewire::test(CreateUnitType::class)
            ->fillForm(['name' => 'Tenda Dome Premium'])
            ->assertFormSet(['slug' => 'tenda-dome-premium'])
            ->fillForm(['slug' => 'dome-custom'])
            ->fillForm(['name' => 'Tenda Dome Baru'])
            ->assertFormSet(['slug' => 'dome-custom']);
    }

    public function test_duplicate_unit_type_slug_is_rejected_with_validation_error(): void
    {
        $this->makeType('dome');

        Livewire::test(CreateUnitType::class)
            ->fillForm([
                'name' => 'Dome Lain',
                'slug' => 'dome',
                'capacity' => 4,
                'base_price_weekday' => 200000,
                'base_price_weekend' => 250000,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);

        $this->assertSame(1, UnitType::count());
    }

    public function test_duplicate_unit_code_is_rejected_but_own_code_can_be_resaved(): void
    {
        $type = $this->makeType();
        Unit::create(['unit_type_id' => $type->id, 'code' => 'D-01', 'status' => 'active']);
        $other = Unit::create(['unit_type_id' => $type->id, 'code' => 'D-02', 'status' => 'active']);

        Livewire::test(CreateUnit::class)
            ->fillForm(['unit_type_id' => $type->id, 'code' => 'D-01', 'status' => 'active'])
            ->call('create')
            ->assertHasFormErrors(['code' => 'unique']);

        Livewire::test(EditUnit::class, ['record' => $other->getKey()])
            ->fillForm(['code' => 'D-02'])
            ->call('save')
            ->assertHasNoFormErrors();
    }
}
