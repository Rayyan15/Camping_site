<?php

namespace Tests\Feature;

use App\Filament\Resources\SpecialPrices\Pages\CreateSpecialPrice;
use App\Filament\Resources\SpecialPrices\Pages\ListSpecialPrices;
use App\Filament\Resources\SpecialPrices\RelationManagers\SpecialPricesRelationManager;
use App\Filament\Resources\SpecialPrices\SpecialPriceResource;
use App\Filament\Resources\UnitTypes\Pages\EditUnitType;
use App\Models\SpecialPrice;
use App\Models\UnitType;
use App\Models\User;
use App\Services\PricingService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SpecialPriceResourceTest extends TestCase
{
    use RefreshDatabase;

    private UnitType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01 10:00:00');
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());

        $this->type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 2,
            'base_price_weekday' => 100000, 'base_price_weekend' => 150000,
        ]);
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

    private function actAsOwner(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
    }

    public function test_checkout_price_changes_after_special_price_is_created_through_the_resource(): void
    {
        $this->actAsOwner();
        // Monday 2027-01-04 and Tuesday night: two weekday nights.
        $pricing = app(PricingService::class);
        $this->assertSame(200000, $pricing->stayTotal($this->type, '2027-01-04', '2027-01-06'));

        Livewire::test(CreateSpecialPrice::class)
            ->fillForm(['unit_type_id' => $this->type->id, 'date' => '2027-01-04', 'price' => 350000, 'note' => 'Libur'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(450000, $pricing->stayTotal($this->type, '2027-01-04', '2027-01-06'));
    }

    public function test_duplicate_type_and_date_is_rejected_in_indonesian(): void
    {
        $this->actAsOwner();
        SpecialPrice::create(['unit_type_id' => $this->type->id, 'date' => '2027-01-04', 'price' => 300000]);

        Livewire::test(CreateSpecialPrice::class)
            ->fillForm(['unit_type_id' => $this->type->id, 'date' => '2027-01-04', 'price' => 400000])
            ->call('create')
            ->assertHasFormErrors(['date']);

        $this->assertSame(1, SpecialPrice::count());
    }

    public function test_past_date_and_non_positive_price_are_rejected_for_new_data(): void
    {
        $this->actAsOwner();

        Livewire::test(CreateSpecialPrice::class)
            ->fillForm(['unit_type_id' => $this->type->id, 'date' => '2026-11-01', 'price' => 0])
            ->call('create')
            ->assertHasFormErrors(['date', 'price']);

        $this->assertSame(0, SpecialPrice::count());
    }

    public function test_list_can_be_filtered_by_type_and_date_range(): void
    {
        $this->actAsOwner();
        $other = UnitType::create([
            'name' => 'Tenda Klasik', 'slug' => 'klasik', 'capacity' => 4,
            'base_price_weekday' => 80000, 'base_price_weekend' => 90000,
        ]);
        $inRange = SpecialPrice::create(['unit_type_id' => $this->type->id, 'date' => '2027-01-04', 'price' => 300000]);
        $outOfRange = SpecialPrice::create(['unit_type_id' => $this->type->id, 'date' => '2027-03-04', 'price' => 300000]);
        $otherType = SpecialPrice::create(['unit_type_id' => $other->id, 'date' => '2027-01-05', 'price' => 300000]);

        Livewire::test(ListSpecialPrices::class)
            ->filterTable('unit_type_id', $this->type->id)
            ->filterTable('date_range', ['from' => '2027-01-01', 'until' => '2027-01-31'])
            ->assertCanSeeTableRecords([$inRange])
            ->assertCanNotSeeTableRecords([$outOfRange, $otherType]);
    }

    public function test_relation_manager_lists_prices_on_the_unit_type_page(): void
    {
        $this->actAsOwner();
        $price = SpecialPrice::create(['unit_type_id' => $this->type->id, 'date' => '2027-01-04', 'price' => 300000]);

        Livewire::test(SpecialPricesRelationManager::class, ['ownerRecord' => $this->type, 'pageClass' => EditUnitType::class])
            ->assertCanSeeTableRecords([$price]);
    }

    public function test_front_office_and_cashier_cannot_access_special_prices(): void
    {
        foreach ([User::ROLE_FRONT_OFFICE, User::ROLE_CASHIER] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->assertFalse(SpecialPriceResource::canViewAny());
            Livewire::test(ListSpecialPrices::class)->assertForbidden();
            Livewire::test(CreateSpecialPrice::class)->assertForbidden();
        }
    }
}
