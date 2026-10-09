<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\OccupancyCellStatus;
use App\Models\Booking;
use App\Models\BookingUnit;
use App\Models\Unit;
use App\Models\UnitBlock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Per unit and per night status for the occupancy timeline. Booking lines and their booking status
 * are the source of truth, not the night ledger, because a booking under review has no ledger rows.
 * A night belongs to a stay from check-in up to, but not including, check-out.
 */
class OccupancyCalendarService
{
    public const DAY_OPTIONS = [7, 14, 30];

    private const MIN_DAYS = 1;

    private const MAX_DAYS = 62;

    private const MASKED_FIRST_NAME_LENGTH = 12;

    /**
     * @return array{
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     dates: list<CarbonImmutable>,
     *     groups: list<array{unit_type: string, units: list<array{id: int, code: string, active: bool, cells: array<string, array>, segments: list<array>}>}>
     * }
     */
    public function grid(CarbonInterface $from, int $days): array
    {
        $days = max(self::MIN_DAYS, min(self::MAX_DAYS, $days));
        $start = CarbonImmutable::instance($from)->startOfDay();
        $end = $start->addDays($days);

        $dates = [];
        for ($i = 0; $i < $days; $i++) {
            $dates[] = $start->addDays($i);
        }

        $stays = $this->stays($start, $end)->groupBy('unit_id');
        $blocks = $this->blocks($start, $end)->groupBy('unit_id');

        $groups = [];

        foreach ($this->units()->groupBy('unit_type_id') as $typeUnits) {
            $rows = [];

            foreach ($typeUnits as $unit) {
                $cells = $this->cellsFor($unit, $dates, $stays->get($unit->id, collect()), $blocks->get($unit->id, collect()));

                $rows[] = [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'active' => $unit->status === Unit::STATUS_ACTIVE,
                    'cells' => $cells,
                    'segments' => $this->segments($cells),
                ];
            }

            $groups[] = ['unit_type' => $typeUnits->first()->unitType->name, 'units' => $rows];
        }

        return ['from' => $start, 'to' => $end, 'dates' => $dates, 'groups' => $groups];
    }

    /**
     * @return array{date: CarbonImmutable, active: int, occupied: int, free: int, review: int, percent: int}
     */
    public function tonight(?CarbonInterface $date = null): array
    {
        $day = CarbonImmutable::instance($date ?? now())->startOfDay();
        $key = $day->toDateString();
        $summary = ['date' => $day, 'active' => 0, 'occupied' => 0, 'free' => 0, 'review' => 0, 'percent' => 0];

        foreach ($this->grid($day, 1)['groups'] as $group) {
            foreach ($group['units'] as $unit) {
                if (! $unit['active']) {
                    continue;
                }

                $status = $unit['cells'][$key]['status'];
                $summary['active']++;
                $summary['occupied'] += $status->isOccupied() ? 1 : 0;
                $summary['free'] += $status === OccupancyCellStatus::Free ? 1 : 0;
                $summary['review'] += $status === OccupancyCellStatus::NeedsReview ? 1 : 0;
            }
        }

        $summary['percent'] = $summary['active'] === 0 ? 0 : (int) round($summary['occupied'] / $summary['active'] * 100);

        return $summary;
    }

    /**
     * Everything the detail modal needs for one unit on one night. The guest name stays masked;
     * contact data is never loaded here.
     *
     * @return array{unit_code: string, unit_type: string, date: CarbonImmutable, cell: array<string, mixed>, booking: Booking|null, block: UnitBlock|null}|null
     */
    public function detail(int $unitId, CarbonInterface $date): ?array
    {
        $day = CarbonImmutable::instance($date)->startOfDay();

        foreach ($this->grid($day, 1)['groups'] as $group) {
            foreach ($group['units'] as $unit) {
                if ($unit['id'] !== $unitId) {
                    continue;
                }

                $cell = $unit['cells'][$day->toDateString()];

                return [
                    'unit_code' => $unit['code'],
                    'unit_type' => $group['unit_type'],
                    'date' => $day,
                    'cell' => $cell,
                    'booking' => $cell['booking_id'] === null ? null : Booking::with('bookingUnits.unit')->find($cell['booking_id']),
                    'block' => $cell['status'] === OccupancyCellStatus::Blocked ? $this->blockOn($unitId, $day) : null,
                ];
            }
        }

        return null;
    }

    private function blockOn(int $unitId, CarbonImmutable $day): ?UnitBlock
    {
        return UnitBlock::query()
            ->where('unit_id', $unitId)
            ->whereDate('start_date', '<=', $day->toDateString())
            ->whereDate('end_date', '>=', $day->toDateString())
            ->first();
    }

    /** "Budi Santoso" becomes "Budi S." so staff can recognise a guest without seeing the full name. */
    public function maskName(?string $name): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return null;
        }

        $first = Str::limit($parts[0], self::MASKED_FIRST_NAME_LENGTH, '');

        return count($parts) === 1 ? $first : $first.' '.Str::upper(Str::substr($parts[1], 0, 1)).'.';
    }

    /** @return Collection<int, Unit> */
    private function units(): Collection
    {
        return Unit::query()
            ->select('units.*')
            ->join('unit_types', 'unit_types.id', '=', 'units.unit_type_id')
            ->with('unitType:id,name')
            ->orderBy('unit_types.name')
            ->orderBy('unit_types.id')
            ->orderBy('units.code')
            ->get();
    }

    /** @return Collection<int, BookingUnit> */
    private function stays(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return BookingUnit::query()
            ->whereDate('check_in', '<', $end->toDateString())
            ->whereDate('check_out', '>', $start->toDateString())
            ->whereHas('booking', fn ($booking) => $booking->where(function ($status) {
                $status->occupying()->orWhere('status', BookingStatus::CheckedOut);
            }))
            ->with('booking:id,code,status,customer_id', 'booking.customer:id,name')
            ->get();
    }

    /** @return Collection<int, UnitBlock> */
    private function blocks(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return UnitBlock::query()
            ->whereDate('start_date', '<', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get();
    }

    /**
     * @param  list<CarbonImmutable>  $dates
     * @param  Collection<int, BookingUnit>  $stays
     * @param  Collection<int, UnitBlock>  $blocks
     * @return array<string, array>
     */
    private function cellsFor(Unit $unit, array $dates, Collection $stays, Collection $blocks): array
    {
        $base = $unit->status === Unit::STATUS_ACTIVE ? OccupancyCellStatus::Free : OccupancyCellStatus::Unavailable;
        $cells = [];

        foreach ($dates as $date) {
            $key = $date->toDateString();
            $cell = $this->cell($base);

            foreach ($blocks as $block) {
                if ($block->start_date->toDateString() <= $key && $block->end_date->toDateString() >= $key) {
                    $cell = $this->stronger($cell, $this->cell(OccupancyCellStatus::Blocked, reason: $block->reason));
                }
            }

            foreach ($stays as $stay) {
                $status = OccupancyCellStatus::fromBooking($stay->booking);

                if ($status !== null && $stay->check_in->toDateString() <= $key && $stay->check_out->toDateString() > $key) {
                    $cell = $this->stronger($cell, $this->cell(
                        $status,
                        $stay->booking->id,
                        $stay->booking->code,
                        $this->maskName($stay->booking->customer?->name),
                    ));
                }
            }

            $cells[$key] = $cell;
        }

        return $cells;
    }

    /** @return array{status: OccupancyCellStatus, booking_id: int|null, booking_code: string|null, guest: string|null, reason: string|null} */
    private function cell(OccupancyCellStatus $status, ?int $bookingId = null, ?string $code = null, ?string $guest = null, ?string $reason = null): array
    {
        return ['status' => $status, 'booking_id' => $bookingId, 'booking_code' => $code, 'guest' => $guest, 'reason' => $reason];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function stronger(array $current, array $candidate): array
    {
        return $candidate['status']->priority() > $current['status']->priority() ? $candidate : $current;
    }

    /**
     * Consecutive nights of the same booking, block reason or plain status become one segment so the
     * timeline can draw a single bar.
     *
     * @param  array<string, array>  $cells
     * @return list<array<string, mixed>>
     */
    private function segments(array $cells): array
    {
        $segments = [];
        $index = 0;

        foreach ($cells as $date => $cell) {
            $last = array_key_last($segments);

            if ($last !== null && $this->continues($segments[$last], $cell)) {
                $segments[$last]['span']++;
            } else {
                $segments[] = $cell + ['start' => $index, 'span' => 1, 'date' => $date];
            }

            $index++;
        }

        return $segments;
    }

    /**
     * @param  array<string, mixed>  $segment
     * @param  array<string, mixed>  $cell
     */
    private function continues(array $segment, array $cell): bool
    {
        return $segment['status'] === $cell['status']
            && $segment['booking_id'] === $cell['booking_id']
            && $segment['reason'] === $cell['reason'];
    }
}
