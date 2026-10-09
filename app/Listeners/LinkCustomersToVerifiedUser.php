<?php

namespace App\Listeners;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

/**
 * Bookings made as a guest are tied to an account only after its email is verified,
 * so typing someone else's email at sign-up never exposes that person's history.
 */
class LinkCustomersToVerifiedUser
{
    public function handle(Verified $event): void
    {
        if ($event->user instanceof User) {
            Customer::claimByVerifiedEmail($event->user);
        }
    }
}
