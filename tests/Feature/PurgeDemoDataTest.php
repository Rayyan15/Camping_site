<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CleaningLog;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeDemoDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoActivitySeeder::class);
    }

    public function test_purge_empties_activity_and_keeps_owner_configuration(): void
    {
        $unitTypes = UnitType::count();
        $units = Unit::count();
        $menuItems = MenuItem::count();
        $cleaningPhoto = CleaningLog::first()->photo_path;
        $this->assertGreaterThan(0, Booking::count());

        $this->artisan('app:purge-demo-data', ['--force' => true, '--skip-backup' => true])->assertSuccessful();

        $this->assertSame(0, Booking::count());
        $this->assertSame(0, Order::count());
        $this->assertSame(0, Customer::count());
        $this->assertSame(0, Employee::count());
        $this->assertSame(0, CleaningLog::count());
        Storage::disk('local')->assertMissing($cleaningPhoto);

        $this->assertSame($unitTypes, UnitType::count());
        $this->assertSame($units, Unit::count());
        $this->assertSame($menuItems, MenuItem::count());
    }

    public function test_demo_accounts_stay_until_a_real_owner_exists(): void
    {
        $this->artisan('app:purge-demo-data', ['--force' => true, '--skip-backup' => true])->assertSuccessful();
        $this->assertTrue(User::where('email', 'owner@raynad.test')->exists());

        User::factory()->create(['email' => 'pemilik@halimun.id'])->assignRole(User::ROLE_OWNER);

        $this->artisan('app:purge-demo-data', ['--force' => true, '--skip-backup' => true])->assertSuccessful();
        $this->assertFalse(User::where('email', 'like', '%@raynad.test')->exists());
        $this->assertTrue(User::where('email', 'pemilik@halimun.id')->exists());
    }

    public function test_declining_the_prompt_deletes_nothing(): void
    {
        $bookings = Booking::count();

        $this->artisan('app:purge-demo-data', ['--skip-backup' => true])
            ->expectsConfirmation('Lanjut hapus data demo?', 'no')
            ->assertSuccessful();

        $this->assertSame($bookings, Booking::count());
    }
}
