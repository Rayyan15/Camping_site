<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Runs synchronously on purpose: the kitchen alert must not depend on a queue worker being alive.
 */
class NotifyKitchenOfNewOrder
{
    private const PERMISSION = 'view_kitchen_queue';

    public function handle(OrderPlaced $event): void
    {
        // can() answers false for a permission row that is missing, where a role scope would throw.
        $staff = User::where('is_active', true)->get()->filter(fn (User $user) => $user->can(self::PERMISSION));

        Notification::send($staff, new NewOrderNotification($event->order));
    }
}
