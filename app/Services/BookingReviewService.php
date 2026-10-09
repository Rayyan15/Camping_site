<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingReviewException;
use App\Models\Booking;
use App\Models\BookingUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves bookings that were paid after their units were taken by someone else (needs_review).
 * The other outcome, a full refund, lives in RefundService::cancelForPropertyFault.
 */
class BookingReviewService
{
    public function __construct(
        private readonly UnitNightLedger $ledger,
        private readonly BookingAuditTrail $audit,
    ) {}

    /**
     * Moves every conflicted unit line to a free unit of the same type and confirms the booking as paid.
     * It becomes Paid and never CheckedIn: a late payment lands before the stay, and check-in stays a
     * separate front desk step. All or nothing: one line without a free unit rejects the whole move.
     *
     * @throws BookingReviewException
     */
    public function moveToFreeUnit(Booking $booking, User $actor): Booking
    {
        $this->assertOwner($actor);

        return DB::transaction(function () use ($booking, $actor) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BookingStatus::NeedsReview) {
                throw BookingReviewException::notInReview();
            }

            $lines = $locked->bookingUnits()->with('unit')->lockForUpdate()->get();
            $moves = $this->reassignConflictedLines($locked, $lines);

            $this->ledger->claim($lines);

            $locked->update(['status' => BookingStatus::Paid, 'review_started_at' => null]);
            $this->audit->record($locked, BookingAuditTrail::ACTION_REVIEW_MOVED_UNIT, $actor->id, ['moves' => $moves]);

            return $locked;
        });
    }

    /**
     * @param  Collection<int, BookingUnit>  $lines
     * @return array<int, array{from_unit_id: int, to_unit_id: int}>
     */
    private function reassignConflictedLines(Booking $booking, Collection $lines): array
    {
        $takenIds = $lines->pluck('unit_id')->all();
        $moves = [];

        foreach ($lines as $line) {
            if ($line->unit->isFreeBetween($line->check_in->toDateString(), $line->check_out->toDateString(), $booking->id)) {
                continue;
            }

            $replacement = $this->findFreeUnit($line, $takenIds) ?? throw BookingReviewException::noFreeUnit();

            $moves[] = ['from_unit_id' => $line->unit_id, 'to_unit_id' => $replacement->id];
            $takenIds[] = $replacement->id;
            $line->update(['unit_id' => $replacement->id]);
        }

        return $moves;
    }

    /**
     * Lowest free unit of the same type, locked so a concurrent checkout waits and then sees it taken.
     *
     * @param  array<int, int>  $excludedUnitIds
     */
    private function findFreeUnit(BookingUnit $line, array $excludedUnitIds): ?Unit
    {
        return Unit::where('unit_type_id', $line->unit->unit_type_id)
            ->where('status', Unit::STATUS_ACTIVE)
            ->whereNotIn('id', $excludedUnitIds)
            ->freeBetween($line->check_in->toDateString(), $line->check_out->toDateString())
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    private function assertOwner(User $actor): void
    {
        if (! $actor->is_active || ! $actor->can('approve_refund')) {
            throw BookingReviewException::notAuthorized();
        }
    }
}
