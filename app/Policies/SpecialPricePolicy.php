<?php

namespace App\Policies;

class SpecialPricePolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'special_price';
    }
}
