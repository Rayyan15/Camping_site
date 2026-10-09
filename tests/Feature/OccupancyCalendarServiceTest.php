<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OccupancyCellStatus;
use App\Models\Booking;
use App\Models\BookingUnit;
use App\Models\Customer;
use App\Models\Unit;
use App\Models\UnitBlock;
use App\Models\UnitType;
use App\Services\OccupancyCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OccupancyCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    private OccupancyCalendarService $service;

    private CarbonImmutable $today;

    private UnitType $type;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-09 10:00:00');
        $this->service = new OccupancyCalendarService;
        $this->today = CarbonImmutable::now()->startOfDay();
        $this->type = $this->makeType('Dome');
    }

    private function makeType(string $name): UnitType
    {
        return UnitType::create([
            'name' => $name, 'slug' => strtolower($name), 'capacity' => 2,
            'base_price_weekday' => 100000, 'base_price_weekend' => 150000,
        ]);
    }

    private function makeUnit(string $code, string $status = 'active', ?UnitType $type = null): Unit
    {
        return Unit::create(['unit_type_id' => ($type ?? $this->type)->id, 'code' => $code, 'status' => $status]);
    }

    private function makeStay(Unit $unit, BookingStatus $status, int $fromOffset, int $toOffset, string $guest = 'Budi Santoso', ?CarbonImmutable $holdUntil = null): Booking
    {
        $customer = Customer::create(['name' => $guest, 'phone' => '081234567890', 'email' => 'tamu@example.test']);
        $in = $this->today->addDays($fromOffset);
        $out = $this->today->addDays($toOffset);

        $booking = Booking::create([
            'code' => 'RCM-'.++$this->sequence, 'customer_id' => $customer->id,
            'check_in' => $in->toDateString(), 'check_out' => $out->toDateString(), 'guests' => 2,
            'status' => $status, 'hold_expires_at' => $holdUntil,
            'subtotal' => 100000, 'tax' => 11000, 'total' => 111000, 'paid_amount' => 0,
        ]);
        BookingUnit::create([
            'booking_id' => $booking->id, 'unit_id' => $unit->id, 'check_in' => $in->toDateString(),
            'check_out' => $out->toDateString(), 'price_per_night' => 100000,
            'nights' => $toOffset - $fromOffset, 'subtotal' => 100000,
        ]);

        return $booking;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function cellsOf(Unit $unit, int $days = 7): array
    {
        foreach ($this->service->grid($this->today, $days)['groups'] as $group) {
            foreach ($group['units'] as $row) {
                if ($row['id'] === $unit->id) {
                    return $row['cells'];
                }
            }
        }

        $this->fail('Unit missing from grid.');
    }

    private function statusOn(Unit $unit, int $offset): OccupancyCellStatus
    {
        return $this->cellsOf($unit, 30)[$this->today->addDays($offset)->toDateString()]['status'];
    }

    public function test_free_unit_is_free_every_day(): void
    {
        $unit = $this->makeUnit('D-1');

        foreach (range(0, 6) as $offset) {
            $this->assertSame(OccupancyCellStatus::Free, $this->statusOn($unit, $offset));
        }
    }

    public function test_each_booking_status_maps_to_its_cell_status(): void
    {
        $held = $this->makeUnit('D-1');
        $paid = $this->makeUnit('D-2');
        $in = $this->makeUnit('D-3');
        $out = $this->makeUnit('D-4');
        $review = $this->makeUnit('D-5');

        $this->makeStay($held, BookingStatus::PendingPayment, 0, 2, holdUntil: CarbonImmutable::now()->addMinutes(30));
        $this->makeStay($paid, BookingStatus::Paid, 0, 2);
        $this->makeStay($in, BookingStatus::CheckedIn, -1, 2);
        $this->makeStay($out, BookingStatus::CheckedOut, -2, 1);
        $this->makeStay($review, BookingStatus::NeedsReview, 1, 3);

        $this->assertSame(OccupancyCellStatus::Held, $this->statusOn($held, 1));
        $this->assertSame(OccupancyCellStatus::Booked, $this->statusOn($paid, 1));
        $this->assertSame(OccupancyCellStatus::Staying, $this->statusOn($in, 0));
        $this->assertSame(OccupancyCellStatus::Completed, $this->statusOn($out, 0));
        $this->assertSame(OccupancyCellStatus::Free, $this->statusOn($review, 0));
        $this->assertSame(OccupancyCellStatus::NeedsReview, $this->statusOn($review, 1));
    }

    public function test_lapsed_cancelled_expired_and_refunded_bookings_do_not_show(): void
    {
        $unit = $this->makeUnit('D-1');

        $this->makeStay($unit, BookingStatus::PendingPayment, 0, 1, holdUntil: CarbonImmutable::now()->subMinute());
        $this->makeStay($unit, BookingStatus::Cancelled, 1, 2);
        $this->makeStay($unit, BookingStatus::Expired, 2, 3);
        $this->makeStay($unit, BookingStatus::Refunded, 3, 4);

        foreach (range(0, 4) as $offset) {
            $this->assertSame(OccupancyCellStatus::Free, $this->statusOn($unit, $offset));
        }
    }

    public function test_checkout_night_is_free_for_the_next_guest(): void
    {
        $unit = $this->makeUnit('D-1');
        $this->makeStay($unit, BookingStatus::Paid, 1, 3);
        $this->makeStay($unit, BookingStatus::Paid, 3, 4, 'Sari Dewi');

        $this->assertSame(OccupancyCellStatus::Free, $this->statusOn($unit, 0));
        $this->assertSame(OccupancyCellStatus::Booked, $this->statusOn($unit, 2));
        $this->assertSame('Sari D.', $this->cellsOf($unit)[$this->today->addDays(3)->toDateString()]['guest']);
        $this->assertSame(OccupancyCellStatus::Free, $this->statusOn($unit, 4));
    }

    public function test_a_stay_ending_today_does_not_fill_tonight(): void
    {
        $unit = $this->makeUnit('D-1');
        $this->makeStay($unit, BookingStatus::CheckedIn, -2, 0);

        $this->assertSame(OccupancyCellStatus::Free, $this->statusOn($unit, 0));
        $this->assertSame(1, $this->service->tonight()['free']);
        $this->assertSame(0, $this->service->tonight()['occupied']);
    }

    public function test_block_range_is_inclusive_and_carries_its_reason(): void
    {
        $unit = $this->makeUnit('D-1');
        UnitBlock::create([
            'unit_id' => $unit->id, 'reason' => 'Ganti kasur',
            'start_date' => $this->today->addDay()->toDateString(), 'end_date' => $this->today->addDays(2)->toDateString(),
        ]);

        $cells = $this->cellsOf($unit);

        $this->assertSame(OccupancyCellStatus::Free, $cells[$this->today->toDateString()]['status']);
        $this->assertSame(OccupancyCellStatus::Blocked, $cells[$this->today->addDay()->toDateString()]['status']);
        $this->assertSame('Ganti kasur', $cells[$this->today->addDays(2)->toDateString()]['reason']);
        $this->assertSame(OccupancyCellStatus::Free, $cells[$this->today->addDays(3)->toDateString()]['status']);
    }

    public function test_needs_review_is_shown_even_without_ledger_rows(): void
    {
        $unit = $this->makeUnit('D-1');
        $booking = $this->makeStay($unit, BookingStatus::NeedsReview, 0, 2);

        $this->assertSame(0, DB::table('booking_unit_nights')->count());
        $this->assertSame(OccupancyCellStatus::NeedsReview, $this->statusOn($unit, 0));
        $this->assertSame($booking->code, $this->cellsOf($unit)[$this->today->toDateString()]['booking_code']);
        $this->assertSame(1, $this->service->tonight()['review']);
    }

    public function test_inactive_unit_is_unavailable_and_not_counted(): void
    {
        $this->makeUnit('D-1');
        $maintenance = $this->makeUnit('D-2', 'maintenance');

        $this->assertSame(OccupancyCellStatus::Unavailable, $this->statusOn($maintenance, 0));

        $tonight = $this->service->tonight();
        $this->assertSame(1, $tonight['active']);
        $this->assertSame(1, $tonight['free']);
    }

    public function test_units_are_ordered_by_type_then_code_and_guest_is_masked(): void
    {
        $tent = $this->makeType('Tenda');
        $this->makeUnit('T-1', type: $tent);
        $this->makeUnit('D-2');
        $b = $this->makeUnit('D-10');
        $this->makeStay($b, BookingStatus::Paid, 0, 1, 'Budi Santoso Wijaya');

        $groups = $this->service->grid($this->today, 7)['groups'];

        $this->assertSame(['Dome', 'Tenda'], array_column($groups, 'unit_type'));
        $this->assertSame(['D-10', 'D-2'], array_column($groups[0]['units'], 'code'));
        $this->assertSame('Budi S.', $this->cellsOf($b)[$this->today->toDateString()]['guest']);
        $this->assertStringNotContainsString('Santoso', json_encode($this->service->grid($this->today, 7)['groups'][0]['units'][0]['cells']));
    }

    public function test_consecutive_nights_of_one_booking_form_one_segment(): void
    {
        $unit = $this->makeUnit('D-1');
        $this->makeStay($unit, BookingStatus::Paid, 1, 4);

        $segments = $this->service->grid($this->today, 7)['groups'][0]['units'][0]['segments'];

        $this->assertSame(
            [[OccupancyCellStatus::Free, 1], [OccupancyCellStatus::Booked, 3], [OccupancyCellStatus::Free, 3]],
            array_map(fn (array $segment) => [$segment['status'], $segment['span']], $segments),
        );
    }

    public function test_query_count_is_constant_regardless_of_units_and_bookings(): void
    {
        $this->makeStay($this->makeUnit('D-1'), BookingStatus::Paid, 0, 2);
        $few = $this->countQueries(fn () => $this->service->grid($this->today, 14));

        foreach (range(2, 15) as $n) {
            $unit = $this->makeUnit("D-{$n}", type: $n % 2 === 0 ? $this->type : $this->makeType("Tipe {$n}"));
            $this->makeStay($unit, BookingStatus::CheckedIn, -1, 3);
            UnitBlock::create(['unit_id' => $unit->id, 'start_date' => $this->today->addDays(5)->toDateString(), 'end_date' => $this->today->addDays(6)->toDateString(), 'reason' => 'Servis']);
        }
        $many = $this->countQueries(fn () => $this->service->grid($this->today, 14));

        $this->assertSame($few, $many);
        $this->assertLessThanOrEqual(8, $many);
    }

    public function test_tonight_summary_counts_percent_free_and_occupied(): void
    {
        $this->makeStay($this->makeUnit('D-1'), BookingStatus::Paid, 0, 1);
        $this->makeStay($this->makeUnit('D-2'), BookingStatus::CheckedIn, -1, 1);
        $this->makeStay($this->makeUnit('D-3'), BookingStatus::PendingPayment, 0, 1, holdUntil: CarbonImmutable::now()->addHour());
        $this->makeUnit('D-4');

        $tonight = $this->service->tonight();

        $this->assertSame(4, $tonight['active']);
        $this->assertSame(2, $tonight['occupied']);
        $this->assertSame(1, $tonight['free']);
        $this->assertSame(50, $tonight['percent']);
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
