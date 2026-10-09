<?php

namespace Tests\Feature;

use App\Enums\AddonUnit;
use App\Filament\Resources\Addons\Pages\CreateAddon;
use App\Models\Addon;
use App\Models\User;
use App\Services\PricingService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AddonUnitTest extends TestCase
{
    use RefreshDatabase;

    private function addon(string $name, AddonUnit $unit): Addon
    {
        return Addon::create(['name' => $name, 'price' => 75000, 'unit' => $unit, 'is_active' => true]);
    }

    public function test_per_night_addon_is_multiplied_by_nights(): void
    {
        $bed = $this->addon('Extra Bed', AddonUnit::PerNight);

        $lines = app(PricingService::class)->addonLines([$bed->id => 2], 3);

        $this->assertSame(450000, $lines[0]['subtotal']);
    }

    public function test_per_item_addon_is_not_multiplied_by_nights(): void
    {
        $wood = $this->addon('Kayu Bakar', AddonUnit::PerItem);

        $lines = app(PricingService::class)->addonLines([$wood->id => 2], 3);

        $this->assertSame(150000, $lines[0]['subtotal']);
    }

    public function test_legacy_free_text_units_are_normalized_by_migration(): void
    {
        $now = now();
        foreach (['malam', 'Per Malam', 'per malam', 'pax', 'porsi'] as $i => $unit) {
            DB::table('addons')->insert(['name' => "Lama {$i}", 'price' => 1000, 'unit' => $unit, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $migration = require database_path('migrations/2026_10_12_000001_normalize_addon_units.php');
        $migration->up();
        $migration->up();

        $this->assertSame(
            ['per malam', 'per malam', 'per malam', 'per item', 'per item'],
            DB::table('addons')->orderBy('id')->pluck('unit')->all(),
        );
        $this->assertTrue(Addon::where('name', 'Lama 0')->first()->isPerNight());
    }

    public function test_form_only_accepts_enum_values(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::create(['name' => 'Owner', 'email' => Str::random(6).'@example.test', 'password' => Str::random(24), 'is_active' => true]);
        $owner->assignRole(User::ROLE_OWNER);
        $this->actingAs($owner);
        Filament::setCurrentPanel('admin');

        Livewire::test(CreateAddon::class)
            ->fillForm(['name' => 'Bed', 'price' => 1000, 'unit' => 'malam', 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['unit']);

        Livewire::test(CreateAddon::class)
            ->fillForm(['name' => 'Bed', 'price' => 1000, 'unit' => AddonUnit::PerNight->value, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Addon::where('name', 'Bed')->first()->isPerNight());
    }
}
