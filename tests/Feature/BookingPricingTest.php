<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Jobs\ReleaseExpiredHolds;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\SpecialPrice;
use App\Models\Unit;
use App\Models\UnitBlock;
use App\Models\UnitType;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPricingTest extends TestCase
{
    use RefreshDatabase;

    private const WEEKDAY_PRICE = 100000;

    private const WEEKEND_PRICE = 150000;

    private UnitType $unitType;

    private Unit $unit;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01 10:00:00');

        $this->unitType = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 2,
            'base_price_weekday' => self::WEEKDAY_PRICE,
            'base_price_weekend' => self::WEEKEND_PRICE,
        ]);
        $this->unit = Unit::create(['unit_type_id' => $this->unitType->id, 'code' => 'D1', 'status' => 'active']);
        $this->customer = Customer::create(['name' => 'Tamu', 'phone' => '0811']);
    }

    private function book(string $checkIn, string $checkOut, array $addons = []): Booking
    {
        return app(BookingService::class)->createBooking(
            $this->customer->id, $checkIn, $checkOut, 2, [$this->unit->id], $addons,
        );
    }

    public function test_weekday_nights_use_weekday_price_with_default_tax(): void
    {
        // Mon 2027-01-04 to Wed 2027-01-06: two weekday nights.
        $booking = $this->book('2027-01-04', '2027-01-06');

        $this->assertSame(200000, $booking->subtotal);
        $this->assertSame(22000, $booking->tax);
        $this->assertSame(222000, $booking->total);
    }

    public function test_friday_and_saturday_nights_use_weekend_price(): void
    {
        // Fri 2027-01-01 to Sun 2027-01-03: Friday and Saturday nights.
        $booking = $this->book('2027-01-01', '2027-01-03');

        $this->assertSame(2 * self::WEEKEND_PRICE, $booking->subtotal);
    }

    public function test_special_price_overrides_base_price_for_its_date(): void
    {
        SpecialPrice::create(['unit_type_id' => $this->unitType->id, 'date' => '2027-01-04', 'price' => 400000]);

        $booking = $this->book('2027-01-04', '2027-01-06');

        $this->assertSame(400000 + self::WEEKDAY_PRICE, $booking->subtotal);
    }

    public function test_addons_are_added_to_subtotal_before_tax(): void
    {
        $addon = Addon::create(['name' => 'Kayu bakar', 'price' => 50000, 'unit' => 'per item', 'is_active' => true]);

        $booking = $this->book('2027-01-04', '2027-01-05', [$addon->id => 2]);

        $this->assertSame(self::WEEKDAY_PRICE + 100000, $booking->subtotal);
        $this->assertSame(22000, $booking->tax);
        $this->assertSame(1, $booking->addons()->count());
    }

    public function test_expired_hold_frees_the_unit(): void
    {
        $service = app(BookingService::class);
        $this->book('2027-01-04', '2027-01-06');

        $this->assertTrue($service->checkAvailability($this->unitType->id, '2027-01-04', '2027-01-06')->isEmpty());

        $this->travelTo(now()->addMinutes(16));

        $this->assertCount(1, $service->checkAvailability($this->unitType->id, '2027-01-04', '2027-01-06'));
    }

    public function test_release_job_marks_expired_pending_bookings(): void
    {
        $booking = $this->book('2027-01-04', '2027-01-06');
        $this->travelTo(now()->addMinutes(16));

        (new ReleaseExpiredHolds)->handle();

        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
    }

    public function test_blocked_unit_is_unavailable(): void
    {
        UnitBlock::create(['unit_id' => $this->unit->id, 'start_date' => '2027-01-05', 'end_date' => '2027-01-05']);

        $available = app(BookingService::class)->checkAvailability($this->unitType->id, '2027-01-04', '2027-01-06');

        $this->assertTrue($available->isEmpty());
    }
}
