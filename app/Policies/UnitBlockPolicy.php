<?php

namespace App\Policies;

class UnitBlockPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'unit_block';
    }
}
