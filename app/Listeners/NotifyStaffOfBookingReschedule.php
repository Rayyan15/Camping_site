<?php

namespace App\Listeners;

use App\Events\BookingRescheduled;
use App\Models\User;
use App\Notifications\BookingRescheduledNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Synchronous like the review alert: staff must see a changed arrival date even without a queue worker.
 */
class NotifyStaffOfBookingReschedule
{
    public function handle(BookingRescheduled $event): void
    {
        $staff = User::whereHas('roles', fn ($roles) => $roles->whereIn('name', ['owner', 'operator_fo']))
            ->where('is_active', true)
            ->get();

        Notification::send($staff, new BookingRescheduledNotification($event->booking, $event->priceDifference));
    }
}
