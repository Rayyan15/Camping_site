<?php

namespace App\Policies;

class RefundPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'refund';
    }
}
