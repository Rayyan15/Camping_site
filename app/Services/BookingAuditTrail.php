<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;

/**
 * Explicit audit entries for booking decisions that a plain field update does not describe.
 */
class BookingAuditTrail
{
    public const ACTION_CANCELLED_WITHOUT_REFUND = 'cancelled_without_refund';

    public const ACTION_REVIEW_MOVED_UNIT = 'review_moved_unit';

    public const ACTION_REVIEW_CANCELLED_FULL_REFUND = 'review_cancelled_full_refund';

    /**
     * @param  array<string, mixed>  $details
     */
    public function record(Booking $booking, string $action, ?int $userId, array $details): void
    {
        ActivityLog::create([
            'user_id' => $userId,
            'subject_type' => $booking->getMorphClass(),
            'subject_id' => $booking->getKey(),
            'action' => $action,
            'changes' => ['old' => [], 'new' => $details],
        ]);
    }
}
