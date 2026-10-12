<?php

namespace Tests\Feature;

use App\Enums\AddonUnit;
use App\Filament\Resources\Addons\Pages\CreateAddon;
use App\Filament\Resources\Addons\Pages\EditAddon;
use App\Models\Addon;
use App\Models\User;
use App\Services\PricingService;
use Database\Seeders\AddonSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ExtraGuestAddonTest extends TestCase
{
    use RefreshDatabase;

    private function signInAsOwner(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::create(['name' => 'Owner', 'email' => Str::random(6).'@example.test', 'password' => Str::random(24), 'is_active' => true]);
        $owner->assignRole(User::ROLE_OWNER);
        $this->actingAs($owner);
        Filament::setCurrentPanel('admin');
    }

    public function test_seeded_extra_guest_addon_is_free_and_inactive_until_the_owner_sets_a_fee(): void
    {
        $this->seed(AddonSeeder::class);

        $addon = Addon::where('extra_guests', '>', 0)->firstOrFail();

        $this->assertSame(0, $addon->price);
        $this->assertFalse($addon->is_active);
    }

    public function test_owner_sets_the_fee_in_the_panel_and_pricing_charges_it_per_night(): void
    {
        $this->seed(AddonSeeder::class);
        $this->signInAsOwner();
        $addon = Addon::where('extra_guests', '>', 0)->firstOrFail();

        Livewire::test(EditAddon::class, ['record' => $addon->getRouteKey()])
            ->fillForm(['price' => 50000, 'is_active' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $lines = app(PricingService::class)->addonLines([$addon->id => 2], 3);

        $this->assertSame(300000, $lines[0]['subtotal']);
    }

    public function test_owner_can_create_an_extra_guest_addon_with_the_fee_left_empty_as_zero(): void
    {
        $this->signInAsOwner();

        Livewire::test(CreateAddon::class)
            ->fillForm(['name' => 'Tamu Tambahan', 'unit' => AddonUnit::PerNight->value, 'extra_guests' => 1, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(0, Addon::where('name', 'Tamu Tambahan')->value('price'));
    }
}
