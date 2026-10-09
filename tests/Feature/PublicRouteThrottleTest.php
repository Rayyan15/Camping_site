<?php

namespace Tests\Feature;

use App\Models\SpecialPrice;
use App\Models\Unit;
use App\Models\UnitType;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicRouteThrottleTest extends TestCase
{
    use RefreshDatabase;

    private UnitType $unitType;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->travelTo('2026-12-01 10:00:00');

        $this->unitType = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 2,
            'base_price_weekday' => 100000, 'base_price_weekend' => 150000,
        ]);
        Unit::create(['unit_type_id' => $this->unitType->id, 'code' => 'D1', 'status' => 'active']);
    }

    public function test_code_lookup_returns_429_after_the_per_code_limit(): void
    {
        config(['booking.throttle.lookup_per_code' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->get('/booking/abc123')->assertNotFound();
        }

        $this->get('/booking/abc123')->assertStatus(429);
        $this->get('/booking/abc123/invoice')->assertStatus(429);
    }

    public function test_another_code_is_not_blocked_by_the_per_code_limit(): void
    {
        config(['booking.throttle.lookup_per_code' => 2]);

        $this->get('/booking/aaaaaa');
        $this->get('/booking/aaaaaa');
        $this->get('/booking/aaaaaa')->assertStatus(429);

        $this->get('/booking/bbbbbb')->assertNotFound();
    }

    public function test_enumerating_many_codes_hits_the_per_ip_limit(): void
    {
        config(['booking.throttle.lookup_per_ip' => 3]);

        foreach (['A', 'B', 'C'] as $letter) {
            $this->get('/checkout/RCM-261201-'.str_repeat($letter, 6))->assertNotFound();
        }

        $this->get('/checkout/RCM-261201-DDDDDD')->assertStatus(429);
    }

    public function test_qr_routes_share_the_lookup_limiter(): void
    {
        config(['booking.throttle.lookup_per_code' => 1]);

        $this->get('/order/unknown-token')->assertNotFound();
        $this->get('/order/unknown-token')->assertStatus(429);
    }

    public function test_availability_check_is_limited_and_does_not_affect_lookups(): void
    {
        config(['booking.throttle.availability_per_ip' => 2]);
        $query = $this->stayQuery('2026-12-10', '2026-12-12');

        $this->get('/booking/cek?'.$query)->assertOk();
        $this->get('/booking/cek?'.$query)->assertOk();
        $this->get('/booking/cek?'.$query)->assertStatus(429);

        $this->get('/booking/abc123')->assertNotFound();
        $this->get('/')->assertOk();
    }

    public function test_throttled_response_is_the_friendly_error_page(): void
    {
        config(['booking.throttle.lookup_per_code' => 1]);

        $this->get('/booking/abc123');

        $this->get('/booking/abc123')
            ->assertStatus(429)
            ->assertSee('Terlalu banyak permintaan');
    }

    public function test_stay_longer_than_max_nights_is_rejected_on_availability_check(): void
    {
        $maxNights = config('booking.max_nights');
        $checkOut = CarbonImmutable::parse('2026-12-10')->addDays($maxNights + 1)->toDateString();

        $this->get('/booking/cek?'.$this->stayQuery('2026-12-10', $checkOut))
            ->assertSessionHasErrors('check_out');
    }

    public function test_stay_of_exactly_max_nights_is_accepted(): void
    {
        $checkOut = CarbonImmutable::parse('2026-12-10')->addDays(config('booking.max_nights'))->toDateString();

        $this->get('/booking/cek?'.$this->stayQuery('2026-12-10', $checkOut))->assertOk();
    }

    public function test_check_in_beyond_max_advance_days_is_rejected(): void
    {
        $checkIn = CarbonImmutable::today()->addDays(config('booking.max_advance_days') + 1);

        $this->get('/booking/cek?'.$this->stayQuery($checkIn->toDateString(), $checkIn->addDay()->toDateString()))
            ->assertSessionHasErrors('check_in');
    }

    public function test_booking_store_rejects_stay_longer_than_max_nights(): void
    {
        $checkOut = CarbonImmutable::parse('2026-12-10')->addDays(config('booking.max_nights') + 1)->toDateString();

        $this->post('/booking', [
            'customer_name' => 'Tamu', 'customer_phone' => '081234567890', 'customer_email' => 'a@example.com',
            'guests' => 2, 'quantity' => 1, 'unit_type_id' => $this->unitType->id,
            'check_in' => '2026-12-10', 'check_out' => $checkOut,
        ])->assertSessionHasErrors('check_out');
    }

    public function test_stay_total_query_count_is_constant_regardless_of_nights(): void
    {
        SpecialPrice::create(['unit_type_id' => $this->unitType->id, 'date' => '2027-01-05', 'price' => 400000]);
        $pricing = app(PricingService::class);

        $countQueries = function (string $checkOut) use ($pricing): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $pricing->stayTotal($this->unitType, '2027-01-04', $checkOut);

            return count(DB::getQueryLog());
        };

        $this->assertSame($countQueries('2027-01-06'), $countQueries('2027-01-18'));
        $this->assertSame(1, $countQueries('2027-01-18'));
    }

    public function test_stay_total_still_applies_special_price_inside_range(): void
    {
        SpecialPrice::create(['unit_type_id' => $this->unitType->id, 'date' => '2027-01-05', 'price' => 400000]);

        $total = app(PricingService::class)->stayTotal($this->unitType, '2027-01-04', '2027-01-06');

        $this->assertSame(500000, $total);
    }

    public function test_special_prices_have_a_unique_unit_type_and_date(): void
    {
        SpecialPrice::create(['unit_type_id' => $this->unitType->id, 'date' => '2027-01-05', 'price' => 400000]);

        $this->expectException(QueryException::class);
        SpecialPrice::create(['unit_type_id' => $this->unitType->id, 'date' => '2027-01-05', 'price' => 500000]);
    }

    private function stayQuery(string $checkIn, string $checkOut): string
    {
        return http_build_query([
            'unit_type_id' => $this->unitType->id, 'check_in' => $checkIn,
            'check_out' => $checkOut, 'guests' => 2,
        ]);
    }
}
