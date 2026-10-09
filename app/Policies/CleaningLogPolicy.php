<?php

namespace App\Policies;

use App\Models\User;

class CleaningLogPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'cleaning_log';
    }

    public function review(User $user, mixed $record = null): bool
    {
        return $user->is_active && $user->can('review_cleaning_logs');
    }
}
