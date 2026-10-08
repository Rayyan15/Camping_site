<?php

namespace App\Policies;

class AttendancePolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'attendance';
    }
}
