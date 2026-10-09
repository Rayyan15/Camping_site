<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Exceptions\UnitUnavailableException;
use App\Filament\Resources\UnitBlocks\Pages\CreateUnitBlock;
use App\Filament\Resources\UnitBlocks\Pages\EditUnitBlock;
use App\Filament\Resources\UnitBlocks\Pages\ListUnitBlocks;
use App\Filament\Resources\UnitBlocks\UnitBlockResource;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Unit;
use App\Models\UnitBlock;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BookingService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class UnitBlockResourceTest extends TestCase
{
    use RefreshDatabase;

    private UnitType $type;

    private Unit $unit;

    private Customer $customer;

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
        $this->unit = Unit::create(['unit_type_id' => $this->type->id, 'code' => 'D1', 'status' => 'active']);
        $this->customer = Customer::create(['name' => 'Tamu', 'phone' => '0811']);
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

    private function book(string $checkIn, string $checkOut): Booking
    {
        return app(BookingService::class)->createBooking(
            $this->customer->id, $checkIn, $checkOut, 2, [$this->unit->id], [],
        );
    }

    public function test_owner_creates_a_block_and_the_unit_leaves_availability_and_cannot_be_booked(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));

        Livewire::test(CreateUnitBlock::class)
            ->fillForm(['unit_id' => $this->unit->id, 'start_date' => '2027-01-04', 'end_date' => '2027-01-06', 'reason' => 'Ganti terpal'])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = app(BookingService::class);
        $this->assertCount(0, $service->checkAvailability($this->type->id, '2027-01-05', '2027-01-07'));
        $this->assertCount(1, $service->checkAvailability($this->type->id, '2027-01-07', '2027-01-08'));

        $this->expectException(UnitUnavailableException::class);
        $this->book('2027-01-05', '2027-01-07');
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));

        Livewire::test(CreateUnitBlock::class)
            ->fillForm(['unit_id' => $this->unit->id, 'start_date' => '2027-01-06', 'end_date' => '2027-01-04'])
            ->call('create')
            ->assertHasFormErrors(['end_date']);

        $this->assertSame(0, UnitBlock::count());
    }

    public function test_block_overlapping_an_active_booking_is_rejected_with_its_code(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        $booking = $this->book('2027-01-04', '2027-01-06');

        $component = Livewire::test(CreateUnitBlock::class)
            ->fillForm(['unit_id' => $this->unit->id, 'start_date' => '2027-01-05', 'end_date' => '2027-01-07'])
            ->call('create')
            ->assertHasFormErrors(['unit_id']);

        $this->assertStringContainsString($booking->code, collect($component->errors()->all())->implode(' '));
        $this->assertSame(0, UnitBlock::count());
    }

    public function test_block_starting_on_checkout_day_or_over_a_cancelled_booking_is_allowed(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        $this->book('2027-01-04', '2027-01-06');
        $cancelled = $this->book('2027-02-01', '2027-02-03');
        $cancelled->update(['status' => BookingStatus::Cancelled]);

        // The booking leaves on 2027-01-06; the block starts that same day.
        Livewire::test(CreateUnitBlock::class)
            ->fillForm(['unit_id' => $this->unit->id, 'start_date' => '2027-01-06', 'end_date' => '2027-01-08'])
            ->call('create')
            ->assertHasNoFormErrors();
        Livewire::test(CreateUnitBlock::class)
            ->fillForm(['unit_id' => $this->unit->id, 'start_date' => '2027-02-01', 'end_date' => '2027-02-02'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, UnitBlock::count());
    }

    public function test_editing_a_block_to_overlap_a_booking_is_rejected(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        $this->book('2027-01-04', '2027-01-06');
        $block = UnitBlock::create(['unit_id' => $this->unit->id, 'start_date' => '2027-03-01', 'end_date' => '2027-03-02']);

        Livewire::test(EditUnitBlock::class, ['record' => $block->getKey()])
            ->fillForm(['start_date' => '2027-01-05', 'end_date' => '2027-01-05'])
            ->call('save')
            ->assertHasFormErrors(['unit_id']);

        $this->assertSame('2027-03-01', $block->fresh()->start_date->toDateString());
    }

    public function test_list_is_filterable_by_date_range(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        $early = UnitBlock::create(['unit_id' => $this->unit->id, 'start_date' => '2027-01-04', 'end_date' => '2027-01-05']);
        $late = UnitBlock::create(['unit_id' => $this->unit->id, 'start_date' => '2027-06-04', 'end_date' => '2027-06-05']);

        Livewire::test(ListUnitBlocks::class)
            ->filterTable('date_range', ['from' => '2027-01-01', 'until' => '2027-01-31'])
            ->assertCanSeeTableRecords([$early])
            ->assertCanNotSeeTableRecords([$late]);
    }

    public function test_front_office_and_cashier_cannot_access_unit_blocks(): void
    {
        foreach ([User::ROLE_FRONT_OFFICE, User::ROLE_CASHIER] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->assertFalse(UnitBlockResource::canViewAny());
            Livewire::test(ListUnitBlocks::class)->assertForbidden();
            Livewire::test(CreateUnitBlock::class)->assertForbidden();
        }
    }
}
