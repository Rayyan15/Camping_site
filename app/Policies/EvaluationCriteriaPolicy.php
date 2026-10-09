<?php

namespace App\Policies;

use App\Models\EvaluationCriteria;
use App\Models\User;

class EvaluationCriteriaPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'evaluation_criteria';
    }

    public function delete(User $user, mixed $record = null): bool
    {
        return parent::delete($user, $record)
            && ! ($record instanceof EvaluationCriteria && $record->scores()->exists());
    }
}
