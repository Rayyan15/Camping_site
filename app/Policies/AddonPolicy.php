<?php

namespace App\Policies;

class AddonPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'addon';
    }
}
