<?php

namespace App\Policies;

class UnitTypePolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'unit_type';
    }
}
