<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Events\BookingRescheduled;
use App\Exceptions\BookingRescheduleException;
use App\Exceptions\UnitUnavailableException;
use App\Models\Booking;
use App\Models\BookingUnit;
use App\Models\Order;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moves a paid booking to new dates, once. Availability is claimed in the unit-night ledger and the price
 * is recomputed here, never taken from the browser.
 */
class BookingRescheduleService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly UnitNightLedger $ledger,
        private readonly BookingAuditTrail $audit,
        private readonly RefundService $refunds,
    ) {}

    /**
     * Why this booking cannot be rescheduled right now, or null when it can.
     */
    public function blockedReason(Booking $booking): ?string
    {
        try {
            $this->assertEligible($booking);
        } catch (BookingRescheduleException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * @param  bool  $allowPriceIncrease  staff may accept a higher price and collect the difference afterwards
     *
     * @throws BookingRescheduleException
     */
    public function reschedule(Booking $booking, string $checkIn, string $checkOut, bool $allowPriceIncrease = false, ?int $actorId = null): RescheduleResult
    {
        $checkIn = CarbonImmutable::parse($checkIn)->toDateString();
        $checkOut = CarbonImmutable::parse($checkOut)->toDateString();

        return DB::transaction(function () use ($booking, $checkIn, $checkOut, $allowPriceIncrease, $actorId) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $this->assertEligible($locked);

            if ($locked->check_in->toDateString() === $checkIn && $locked->check_out->toDateString() === $checkOut) {
                throw BookingRescheduleException::sameDates();
            }

            $lines = $locked->bookingUnits()->with('unit.unitType')->get();
            $unitIds = $this->lockAndChooseUnits($locked, $lines, $checkIn, $checkOut);

            $nights = $this->pricing->nightsBetween($checkIn, $checkOut);
            $newSubtotal = $this->repriceLines($locked, $lines, $unitIds, $checkIn, $checkOut, $nights);
            $newTotal = $newSubtotal;
            $difference = $newTotal - $locked->total;

            if ($difference > 0 && ! $allowPriceIncrease) {
                throw BookingRescheduleException::priceIncreased($difference);
            }

            $this->claimNights($locked->bookingUnits()->get());
            $this->shiftPreorders($locked, $checkIn, $checkOut);

            $old = [
                'check_in' => $locked->check_in->toDateString(),
                'check_out' => $locked->check_out->toDateString(),
                'total' => $locked->total,
            ];

            $locked->update([
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'subtotal' => $newSubtotal,
                'total' => $newTotal,
                'reschedule_count' => $locked->reschedule_count + 1,
                'reschedule_credit' => max(0, $locked->paid_amount - $newTotal),
                'rescheduled_at' => now(),
            ]);

            $this->audit->record($locked, BookingAuditTrail::ACTION_RESCHEDULED, $actorId, [
                'from' => $old,
                'to' => ['check_in' => $checkIn, 'check_out' => $checkOut, 'total' => $newTotal],
                'price_difference' => $difference,
            ]);

            BookingRescheduled::dispatch($locked, $difference);

            return new RescheduleResult($locked, $difference);
        });
    }

    /**
     * @throws BookingRescheduleException
     */
    private function assertEligible(Booking $booking): void
    {
        if ($booking->status !== BookingStatus::Paid) {
            throw BookingRescheduleException::notEligibleStatus();
        }

        if ($booking->reschedule_count >= (int) config('booking.reschedule.max_times')) {
            throw BookingRescheduleException::alreadyUsed();
        }

        $minDays = (int) config('booking.reschedule.min_days_before_check_in');
        $daysLeft = (int) now()->startOfDay()->diffInDays($booking->check_in->copy()->startOfDay(), false);

        if ($daysLeft < $minDays) {
            throw BookingRescheduleException::tooLate($minDays);
        }

        if ($this->refunds->hasOpenRequest($booking)) {
            throw BookingRescheduleException::refundPending();
        }
    }

    /**
     * Keeps each line on its own unit when that unit is free on the new dates, otherwise takes the lowest
     * free unit of the same type. Unit rows are locked so a concurrent checkout waits and then sees them taken.
     *
     * @param  Collection<int, BookingUnit>  $lines
     * @return array<int, int> line id => unit id
     *
     * @throws BookingRescheduleException
     */
    private function lockAndChooseUnits(Booking $booking, Collection $lines, string $checkIn, string $checkOut): array
    {
        Unit::whereIn('id', $lines->pluck('unit_id'))->lockForUpdate()->get();

        $chosen = [];

        foreach ($lines as $line) {
            $own = $line->unit;
            $ownIsFree = $own->status === Unit::STATUS_ACTIVE && $own->isFreeBetween($checkIn, $checkOut, $booking->id);

            $chosen[$line->id] = $ownIsFree
                ? $own->id
                : $this->findReplacement($booking, $line, $lines, $chosen, $checkIn, $checkOut)->id;
        }

        return $chosen;
    }

    /**
     * @param  Collection<int, BookingUnit>  $lines
     * @param  array<int, int>  $chosen
     *
     * @throws BookingRescheduleException
     */
    private function findReplacement(Booking $booking, BookingUnit $line, Collection $lines, array $chosen, string $checkIn, string $checkOut): Unit
    {
        $otherLinesUnits = $lines->where('id', '!=', $line->id)->pluck('unit_id')->all();

        return Unit::where('unit_type_id', $line->unit->unit_type_id)
            ->where('status', Unit::STATUS_ACTIVE)
            ->whereNotIn('id', array_merge($chosen, $otherLinesUnits))
            ->freeBetween($checkIn, $checkOut, $booking->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->first() ?? throw BookingRescheduleException::datesUnavailable();
    }

    /**
     * Rewrites unit and add-on lines for the new stay and returns the new subtotal. Food ordered in advance
     * keeps its price (its serve times move in shiftPreorders): it is whatever the old subtotal held beyond units and add-ons.
     *
     * @param  Collection<int, BookingUnit>  $lines
     * @param  array<int, int>  $unitIds  line id => unit id
     */
    private function repriceLines(Booking $booking, Collection $lines, array $unitIds, string $checkIn, string $checkOut, int $nights): int
    {
        $addons = $booking->addons()->with('addon')->get();
        $foodTotal = max(0, $booking->subtotal - (int) $lines->sum('subtotal') - (int) $addons->sum('subtotal'));

        $unitTotal = 0;

        foreach ($lines as $line) {
            $unit = Unit::with('unitType')->findOrFail($unitIds[$line->id]);
            $subtotal = $this->pricing->stayTotal($unit->unitType, $checkIn, $checkOut);
            $unitTotal += $subtotal;

            $line->update([
                'unit_id' => $unit->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'nights' => $nights,
                'price_per_night' => intdiv($subtotal, max($nights, 1)),
                'subtotal' => $subtotal,
            ]);
        }

        $addonTotal = 0;

        foreach ($addons as $bookingAddon) {
            $multiplier = $bookingAddon->addon->isPerNight() ? max($nights, 1) : 1;
            $subtotal = $bookingAddon->price * $bookingAddon->qty * $multiplier;
            $bookingAddon->update(['subtotal' => $subtotal]);
            $addonTotal += $subtotal;
        }

        return $unitTotal + $addonTotal + $foodTotal;
    }

    /**
     * Pre-ordered meals move with the stay by the same number of days, so the kitchen serves them on the
     * new dates. A meal that would land outside a shortened stay blocks the move instead of being billed
     * for a day the guest is not there.
     *
     * @throws BookingRescheduleException
     */
    private function shiftPreorders(Booking $booking, string $checkIn, string $checkOut): void
    {
        $offsetDays = (int) $booking->check_in->copy()->startOfDay()->diffInDays(CarbonImmutable::parse($checkIn), false);

        $preorders = $booking->orders()->where('source', Order::SOURCE_PREORDER)->whereNotNull('scheduled_at')->get();

        foreach ($preorders as $order) {
            $moved = $order->scheduled_at->copy()->addDays($offsetDays);

            if ($moved->toDateString() < $checkIn || $moved->toDateString() > $checkOut) {
                throw BookingRescheduleException::preordersOutsideStay();
            }

            $order->update(['scheduled_at' => $moved]);
        }
    }

    /**
     * Savepoint, so a conflict on a later line cannot leave the earlier lines' nights claimed.
     *
     * @param  Collection<int, BookingUnit>  $lines
     *
     * @throws BookingRescheduleException
     */
    private function claimNights(Collection $lines): void
    {
        try {
            DB::transaction(fn () => $this->ledger->claim($lines));
        } catch (UnitUnavailableException) {
            throw BookingRescheduleException::datesUnavailable();
        }
    }
}
