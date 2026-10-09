<?php

namespace App\Policies;

use App\Models\User;

class BookingPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'booking';
    }

    /** Bookings are an audit record; cancel or refund instead of deleting. */
    public function delete(User $user, mixed $record = null): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function checkIn(User $user, mixed $record = null): bool
    {
        return $user->is_active && $user->can('check_in_booking');
    }

    public function checkOut(User $user, mixed $record = null): bool
    {
        return $user->is_active && $user->can('check_out_booking');
    }

    public function recordPayment(User $user, mixed $record = null): bool
    {
        return $user->is_active && $user->can('record_manual_payment');
    }

    /** A paid booking that lost its unit is an owner decision: moving it or refunding it in full. */
    public function resolveReview(User $user, mixed $record = null): bool
    {
        return $user->is_active && $user->can('approve_refund');
    }
}
