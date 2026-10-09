<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Exceptions\CannotDeleteReferencedRecord;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Booking;
use App\Models\BookingUnit;
use App\Models\Customer;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner'.Str::random(5).'@example.test', 'password' => Str::random(24), 'is_active' => true,
        ]);
        $this->owner->assignRole(User::ROLE_OWNER);
        $this->actingAs($this->owner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function makeBooking(Customer $customer, BookingStatus $status, ?Unit $unit = null): Booking
    {
        $booking = Booking::create([
            'code' => 'BK-'.Str::upper(Str::random(6)),
            'customer_id' => $customer->id,
            'check_in' => '2026-12-01',
            'check_out' => '2026-12-02',
            'guests' => 2,
            'status' => $status,
            'subtotal' => 100000,
            'total' => 100000,
        ]);

        if ($unit) {
            BookingUnit::create([
                'booking_id' => $booking->id, 'unit_id' => $unit->id,
                'check_in' => '2026-12-01', 'check_out' => '2026-12-02', 'price_per_night' => 100000, 'nights' => 1, 'subtotal' => 100000,
            ]);
        }

        return $booking;
    }

    private function makeUnit(string $code): Unit
    {
        $type = UnitType::first() ?? UnitType::create(['name' => 'Dome', 'slug' => 'dome', 'capacity' => 2, 'base_price_weekday' => 100000, 'base_price_weekend' => 120000]);

        return Unit::create(['unit_type_id' => $type->id, 'code' => $code, 'status' => 'active']);
    }

    public function test_customer_with_booking_cannot_be_deleted(): void
    {
        $customer = Customer::create(['name' => 'Tamu', 'phone' => '081234567890']);
        $this->makeBooking($customer, BookingStatus::Paid);

        $this->assertFalse($this->owner->can('delete', $customer));
        $this->expectException(CannotDeleteReferencedRecord::class);

        $customer->delete();
    }

    public function test_unit_with_booking_history_cannot_be_deleted(): void
    {
        $unit = $this->makeUnit('D-1');
        $this->makeBooking(Customer::create(['name' => 'Tamu', 'phone' => '081200000000']), BookingStatus::CheckedOut, $unit);

        $this->assertFalse($this->owner->can('delete', $unit));
        $this->expectException(CannotDeleteReferencedRecord::class);

        $unit->delete();
    }

    public function test_paid_booking_cannot_be_deleted_even_by_owner(): void
    {
        $booking = $this->makeBooking(Customer::create(['name' => 'Tamu', 'phone' => '081200000000']), BookingStatus::Paid);

        $this->assertFalse($this->owner->can('delete', $booking));
        $this->assertFalse($this->owner->can('deleteAny', Booking::class));

        try {
            $booking->delete();
            $this->fail('Booking lunas seharusnya tidak bisa dihapus.');
        } catch (CannotDeleteReferencedRecord) {
            $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
        }
    }

    public function test_records_without_references_can_be_deleted(): void
    {
        $customer = Customer::create(['name' => 'Baru', 'phone' => '081200000000']);
        $unit = $this->makeUnit('D-2');

        $this->assertTrue($this->owner->can('delete', $customer));
        $this->assertTrue($this->owner->can('delete', $unit));

        $customer->delete();
        $unit->delete();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }

    public function test_bulk_delete_skips_referenced_customers(): void
    {
        $used = Customer::create(['name' => 'Punya Booking', 'phone' => '081200000000']);
        $this->makeBooking($used, BookingStatus::Paid);
        $free = Customer::create(['name' => 'Tanpa Booking', 'phone' => '081200000000']);

        Livewire::test(ListCustomers::class)
            ->selectTableRecords([$used->id, $free->id])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertDatabaseMissing('customers', ['id' => $free->id]);
        $this->assertDatabaseHas('customers', ['id' => $used->id]);
    }
}
