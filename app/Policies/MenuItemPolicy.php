<?php

namespace App\Policies;

class MenuItemPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'menu_item';
    }
}
