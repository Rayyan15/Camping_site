<?php

namespace App\Listeners;

use App\Events\TransferProofSubmitted;
use App\Models\User;
use App\Notifications\TransferProofSubmittedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Runs synchronously on purpose: the alert must not depend on a queue worker being alive.
 */
class NotifyStaffOfTransferProof
{
    private const PERMISSION = 'record_manual_payment';

    public function handle(TransferProofSubmitted $event): void
    {
        // can() answers false for a permission row that is missing, where a role scope would throw.
        $staff = User::where('is_active', true)->get()->filter(fn (User $user) => $user->can(self::PERMISSION));

        Notification::send($staff, new TransferProofSubmittedNotification($event->payment));
    }
}
