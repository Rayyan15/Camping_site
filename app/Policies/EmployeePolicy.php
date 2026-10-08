<?php

namespace App\Policies;

class EmployeePolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'employee';
    }
}
