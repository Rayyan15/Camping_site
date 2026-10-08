<?php

namespace App\Policies;

class EvaluationScorePolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'evaluation_score';
    }
}
