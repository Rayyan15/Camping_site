<?php

namespace App\Policies;

class ShiftPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'shift';
    }
}
