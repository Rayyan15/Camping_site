<?php

namespace App\Listeners;

use App\Events\BookingNeedsReview;
use App\Models\User;
use App\Notifications\BookingNeedsReviewNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Runs synchronously on purpose: the alert must not depend on a queue worker being alive.
 */
class NotifyOwnersOfBookingReview
{
    public function handle(BookingNeedsReview $event): void
    {
        // whereHas instead of User::role(): role() throws when the role row is missing,
        // and an alert must never break the payment that triggered it.
        $owners = User::whereHas('roles', fn ($roles) => $roles->where('name', 'owner'))
            ->where('is_active', true)
            ->get();

        Notification::send($owners, new BookingNeedsReviewNotification($event->booking));
    }
}
