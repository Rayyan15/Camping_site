<?php

namespace App\Policies;

class OrderPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'order';
    }
}
