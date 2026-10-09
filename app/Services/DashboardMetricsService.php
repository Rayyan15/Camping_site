<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    private const ORDER_DONE = 'selesai';

    private const UNIT_ACTIVE = 'active';

    /** Bookings that hold a unit overnight. */
    private const OCCUPYING_STATUSES = ['paid', 'checked_in'];

    private const ARRIVAL_STATUSES = ['paid', 'checked_in'];

    private const DEPARTURE_STATUSES = ['checked_in', 'checked_out'];

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::instance(now())->startOfDay();
    }

    public function checkInsToday(): int
    {
        return Booking::query()
            ->whereDate('check_in', $this->today()->toDateString())
            ->whereIn('status', self::ARRIVAL_STATUSES)
            ->count();
    }

    public function checkOutsToday(): int
    {
        return Booking::query()
            ->whereDate('check_out', $this->today()->toDateString())
            ->whereIn('status', self::DEPARTURE_STATUSES)
            ->count();
    }

    public function activeUnitCount(): int
    {
        return DB::table('units')->where('status', self::UNIT_ACTIVE)->count();
    }

    public function occupiedUnitCount(): int
    {
        return $this->occupiedUnitsQuery()->distinct()->count('booking_units.unit_id');
    }

    /** Whole percent of active units booked today; 0 when no unit exists. */
    public function occupancyPercent(): int
    {
        $active = $this->activeUnitCount();

        if ($active === 0) {
            return 0;
        }

        return (int) round($this->occupiedUnitCount() / $active * 100);
    }

    public function activeFoodOrderCount(): int
    {
        return Order::query()->kitchenRelevant()->where('status', '!=', self::ORDER_DONE)->count();
    }

    /** Pre-orders of unpaid bookings with a live hold: hidden from the kitchen until the booking is paid. */
    public function preorderAwaitingPaymentCount(): int
    {
        return Order::query()->awaitingBookingPayment()->count();
    }

    /** @return array{camping: int, food: int} net integer rupiah (payments in minus refunds) for the current month */
    public function monthlyRevenue(): array
    {
        $start = $this->today()->startOfMonth();
        $end = $start->addMonth();

        $totals = Payment::query()
            ->settled()
            ->paidBetween($start->toDateTimeString(), $end->toDateTimeString())
            ->netByPayableType()
            ->pluck('total', 'payable_type');

        return [
            'camping' => (int) ($totals[(new Booking)->getMorphClass()] ?? 0),
            'food' => (int) ($totals[(new Order)->getMorphClass()] ?? 0),
        ];
    }

    /**
     * Bookings created per local day, oldest first.
     *
     * @return array{labels: list<string>, data: list<int>}
     */
    public function bookingsPerDay(int $days = 14): array
    {
        $first = $this->today()->subDays($days - 1);

        $counts = Booking::query()
            ->where('created_at', '>=', $first->toDateTimeString())
            ->get(['created_at'])
            ->countBy(fn (Booking $booking) => $booking->created_at->toDateString());

        $labels = [];
        $data = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $first->addDays($i);
            $labels[] = $day->format('d/m');
            $data[] = (int) ($counts[$day->toDateString()] ?? 0);
        }

        return compact('labels', 'data');
    }

    /**
     * Occupied versus free active units per unit type.
     *
     * @return array{labels: list<string>, occupied: list<int>, available: list<int>}
     */
    public function occupancyByUnitType(): array
    {
        $occupiedIds = $this->occupiedUnitsQuery()->pluck('booking_units.unit_id')->unique()->all();

        $units = DB::table('units')
            ->join('unit_types', 'unit_types.id', '=', 'units.unit_type_id')
            ->where('units.status', self::UNIT_ACTIVE)
            ->orderBy('unit_types.name')
            ->get(['units.id', 'unit_types.name']);

        $labels = [];
        $occupied = [];
        $available = [];

        foreach ($units->groupBy('name') as $name => $group) {
            $busy = $group->whereIn('id', $occupiedIds)->count();
            $labels[] = $name;
            $occupied[] = $busy;
            $available[] = $group->count() - $busy;
        }

        return compact('labels', 'occupied', 'available');
    }

    private function occupiedUnitsQuery(): Builder
    {
        $today = $this->today()->toDateString();

        return DB::table('booking_units')
            ->join('bookings', 'bookings.id', '=', 'booking_units.booking_id')
            ->join('units', 'units.id', '=', 'booking_units.unit_id')
            ->where('units.status', self::UNIT_ACTIVE)
            ->whereIn('bookings.status', self::OCCUPYING_STATUSES)
            ->where('booking_units.check_in', '<=', $today)
            ->where('booking_units.check_out', '>', $today);
    }
}
