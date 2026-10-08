<?php

namespace App\Policies;

class EvaluationPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'evaluation';
    }
}
