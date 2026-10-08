<?php

namespace App\Policies;

class EvaluationCriteriaPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'evaluation_criteria';
    }
}
