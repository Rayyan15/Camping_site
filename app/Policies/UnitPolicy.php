<?php

namespace App\Policies;

class UnitPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'unit';
    }
}
