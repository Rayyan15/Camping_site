<?php

namespace App\Policies;

class BookingUnitPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'booking_unit';
    }
}
