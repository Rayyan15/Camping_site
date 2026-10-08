<?php

namespace App\Policies;

class CustomerPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'customer';
    }
}
