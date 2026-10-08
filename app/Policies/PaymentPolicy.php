<?php

namespace App\Policies;

class PaymentPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'payment';
    }
}
