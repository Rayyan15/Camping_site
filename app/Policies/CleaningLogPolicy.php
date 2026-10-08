<?php

namespace App\Policies;

class CleaningLogPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'cleaning_log';
    }
}
