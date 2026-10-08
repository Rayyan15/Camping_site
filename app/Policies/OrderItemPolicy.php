<?php

namespace App\Policies;

class OrderItemPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'order_item';
    }
}
