<?php

namespace App\Policies;

class SettingPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'setting';
    }
}
