<?php

namespace App\Policies;

class DiningSpotPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'dining_spot';
    }
}
