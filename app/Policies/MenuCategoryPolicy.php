<?php

namespace App\Policies;

class MenuCategoryPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'menu_category';
    }
}
