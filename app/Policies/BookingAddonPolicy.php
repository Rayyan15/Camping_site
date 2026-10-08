<?php

namespace App\Policies;

class BookingAddonPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'booking_addon';
    }
}
