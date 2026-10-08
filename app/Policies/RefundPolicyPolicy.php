<?php

namespace App\Policies;

class RefundPolicyPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'refund_policy';
    }
}
