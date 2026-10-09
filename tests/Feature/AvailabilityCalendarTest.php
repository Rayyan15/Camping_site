<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\SpecialPrice;
use App\Models\Unit;
use App\Models\UnitBlock;
use App\Models\UnitType;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AvailabilityCalendarTest extends TestCase
{
    use RefreshDatabase;

    private UnitType $unitType;

    /** @var array<int, Unit> */
    private array $units = [];

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->travelTo('2026-12-01 10:00:00');

        $this->unitType = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 2,
            'base_price_weekday' => 100000, 'base_price_weekend' => 150000,
        ]);
        foreach (['D1', 'D2', 'D3'] as $code) {
            $this->units[] = Unit::create(['unit_type_id' => $this->unitType->id, 'code' => $code, 'status' => 'active']);
        }
        $this->customer = Customer::create(['name' => 'Tamu', 'phone' => '0811']);
    }

    private function book(Unit $unit, string $checkIn, string $checkOut): Booking
    {
        return app(BookingService::class)->createBooking($this->customer->id, $checkIn, $checkOut, 2, [$unit->id]);
    }

    private function month(string $month = '2026-12'): array
    {
        return $this->getJson(route('tenda.ketersediaan', ['slug' => 'dome', 'bulan' => $month]))->assertOk()->json();
    }

    public function test_an_empty_month_has_every_unit_free_on_every_date(): void
    {
        $payload = $this->month();

        $this->assertSame('2026-12', $payload['month']);
        $this->assertSame(3, $payload['total_units']);
        $this->assertCount(31, $payload['days']);
        $this->assertSame(['free' => 3, 'total' => 3, 'limited' => false], $payload['days']['2026-12-15']);
    }

    public function test_a_date_is_full_only_when_every_unit_of_the_type_is_taken(): void
    {
        $this->book($this->units[0], '2026-12-10', '2026-12-12');
        $this->book($this->units[1], '2026-12-10', '2026-12-11');

        $days = $this->month()['days'];

        $this->assertSame(1, $days['2026-12-10']['free']);
        $this->assertTrue($days['2026-12-10']['limited']);
        $this->assertSame(2, $days['2026-12-11']['free']);
        $this->assertSame(3, $days['2026-12-12']['free'], 'The checkout day is free for the next guest.');

        $this->book($this->units[2], '2026-12-10', '2026-12-11');

        $days = $this->month()['days'];
        $this->assertSame(0, $days['2026-12-10']['free']);
        $this->assertFalse($days['2026-12-10']['limited']);
    }

    public function test_maintenance_blocks_count_with_an_inclusive_end_date(): void
    {
        UnitBlock::create(['unit_id' => $this->units[0]->id, 'start_date' => '2026-12-20', 'end_date' => '2026-12-21', 'reason' => 'Perbaikan']);

        $days = $this->month()['days'];

        $this->assertSame(3, $days['2026-12-19']['free']);
        $this->assertSame(2, $days['2026-12-20']['free']);
        $this->assertSame(2, $days['2026-12-21']['free']);
        $this->assertSame(3, $days['2026-12-22']['free']);
    }

    public function test_a_block_that_overlaps_a_booking_of_the_same_unit_is_counted_once(): void
    {
        $this->book($this->units[0], '2026-12-20', '2026-12-22');
        UnitBlock::create(['unit_id' => $this->units[0]->id, 'start_date' => '2026-12-21', 'end_date' => '2026-12-23', 'reason' => 'Perbaikan']);

        $days = $this->month()['days'];

        $this->assertSame(2, $days['2026-12-21']['free']);
        $this->assertSame(2, $days['2026-12-22']['free']);
    }

    public function test_a_live_hold_blocks_dates_and_an_expired_one_does_not(): void
    {
        $live = $this->book($this->units[0], '2026-12-05', '2026-12-06');
        $lapsed = $this->book($this->units[1], '2026-12-07', '2026-12-08');
        $lapsed->update(['hold_expires_at' => now()->subMinute()]);

        $days = $this->month()['days'];

        $this->assertSame(BookingStatus::PendingPayment, $live->status);
        $this->assertSame(2, $days['2026-12-05']['free']);
        $this->assertSame(3, $days['2026-12-07']['free']);
    }

    public function test_cancelled_and_expired_bookings_do_not_block(): void
    {
        $this->book($this->units[0], '2026-12-05', '2026-12-06')->update(['status' => BookingStatus::Cancelled]);
        $this->book($this->units[1], '2026-12-05', '2026-12-06')->update(['status' => BookingStatus::Expired]);

        $this->assertSame(3, $this->month()['days']['2026-12-05']['free']);
    }

    public function test_paid_bookings_block_and_a_stay_spanning_months_shows_in_both(): void
    {
        $this->book($this->units[0], '2026-12-30', '2027-01-02')->update(['status' => BookingStatus::Paid]);

        $this->assertSame(2, $this->month('2026-12')['days']['2026-12-31']['free']);
        $this->assertSame(2, $this->month('2027-01')['days']['2027-01-01']['free']);
        $this->assertSame(3, $this->month('2027-01')['days']['2027-01-02']['free']);
    }

    public function test_inactive_units_are_not_counted(): void
    {
        $this->units[2]->update(['status' => 'inactive']);

        $this->assertSame(2, $this->month()['total_units']);
    }

    public function test_other_unit_types_do_not_leak_into_the_count(): void
    {
        $other = UnitType::create(['name' => 'Kabin', 'slug' => 'kabin', 'capacity' => 4, 'base_price_weekday' => 1, 'base_price_weekend' => 1]);
        $otherUnit = Unit::create(['unit_type_id' => $other->id, 'code' => 'K1', 'status' => 'active']);
        $this->book($otherUnit, '2026-12-10', '2026-12-11');

        $this->assertSame(3, $this->month()['days']['2026-12-10']['free']);
    }

    public function test_the_query_count_does_not_grow_with_units_or_bookings(): void
    {
        $this->book($this->units[0], '2026-12-03', '2026-12-05');

        DB::enableQueryLog();
        $this->month();
        $baseline = count(DB::getQueryLog());

        foreach (range(4, 12) as $n) {
            $unit = Unit::create(['unit_type_id' => $this->unitType->id, 'code' => 'X'.$n, 'status' => 'active']);
            $this->book($unit, sprintf('2026-12-%02d', $n * 2), sprintf('2026-12-%02d', $n * 2 + 1));
            UnitBlock::create(['unit_id' => $unit->id, 'start_date' => '2026-12-28', 'end_date' => '2026-12-29', 'reason' => 'Uji']);
        }
        DB::flushQueryLog();
        $this->month();
        $grown = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($baseline, $grown);
        $this->assertLessThanOrEqual(6, $grown);
    }

    public function test_month_is_validated(): void
    {
        $url = fn (array $query) => route('tenda.ketersediaan', ['slug' => 'dome'] + $query);

        $this->getJson($url([]))->assertStatus(422)->assertJsonValidationErrors('bulan');
        $this->getJson($url(['bulan' => 'desember']))->assertStatus(422)->assertJsonValidationErrors('bulan');
        $this->getJson($url(['bulan' => '2026-13']))->assertStatus(422)->assertJsonValidationErrors('bulan');
        $this->getJson($url(['bulan' => '2026-11']))->assertStatus(422)->assertJsonValidationErrors('bulan');
        $this->getJson($url(['bulan' => '2031-01']))->assertStatus(422)->assertJsonValidationErrors('bulan');
        $this->getJson($url(['bulan' => '2026-12']))->assertOk();
    }

    public function test_the_last_bookable_month_is_accepted(): void
    {
        config(['booking.max_advance_days' => 60]);

        $this->getJson(route('tenda.ketersediaan', ['slug' => 'dome', 'bulan' => '2027-01']))->assertOk();
        $this->getJson(route('tenda.ketersediaan', ['slug' => 'dome', 'bulan' => '2027-03']))->assertStatus(422);
    }

    public function test_unknown_tent_type_is_not_found(): void
    {
        $this->getJson(route('tenda.ketersediaan', ['slug' => 'tidak-ada', 'bulan' => '2026-12']))->assertNotFound();
    }

    public function test_estimate_is_priced_by_the_server_with_special_prices(): void
    {
        SpecialPrice::create(['unit_type_id' => $this->unitType->id, 'date' => '2026-12-17', 'price' => 400000]);

        // Wed 16 (weekday), Thu 17 (special), Fri 18 (weekend).
        $this->getJson(route('tenda.ketersediaan', ['slug' => 'dome', 'check_in' => '2026-12-16', 'check_out' => '2026-12-19']))
            ->assertOk()
            ->assertExactJson(['estimate' => ['nights' => 3, 'total' => 100000 + 400000 + 150000]]);
    }

    public function test_estimate_rejects_bad_ranges(): void
    {
        $url = fn (array $query) => route('tenda.ketersediaan', ['slug' => 'dome'] + $query);

        $this->getJson($url(['check_in' => '2026-12-20', 'check_out' => '2026-12-20']))->assertStatus(422)->assertJsonValidationErrors('check_out');
        $this->getJson($url(['check_in' => '2026-11-20', 'check_out' => '2026-11-22']))->assertStatus(422)->assertJsonValidationErrors('check_in');
        $this->getJson($url(['check_in' => '2026-12-20']))->assertStatus(422)->assertJsonValidationErrors('check_out');
        $this->getJson($url(['check_in' => '2026-12-20', 'check_out' => '2027-01-20']))->assertStatus(422)->assertJsonValidationErrors('check_out');
    }

    public function test_availability_endpoint_shares_the_availability_throttle(): void
    {
        config(['booking.throttle.availability_per_ip' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->getJson(route('tenda.ketersediaan', ['slug' => 'dome', 'bulan' => '2026-12']))->assertOk();
        }

        $this->getJson(route('tenda.ketersediaan', ['slug' => 'dome', 'bulan' => '2026-12']))->assertStatus(429);
    }

    public function test_response_carries_no_personal_data(): void
    {
        $this->book($this->units[0], '2026-12-10', '2026-12-11');

        $body = $this->getJson(route('tenda.ketersediaan', ['slug' => 'dome', 'bulan' => '2026-12']))->getContent();

        $this->assertStringNotContainsString('Tamu', $body);
        $this->assertStringNotContainsString('0811', $body);
        $this->assertStringNotContainsString('D1', $body);
    }
}
