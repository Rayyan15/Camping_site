<?php

namespace App\Policies;

class BookingPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'booking';
    }
}
