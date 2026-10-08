<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'user';
    }

    /** An account cannot delete itself, which would lock the last owner out. */
    public function delete(User $user, mixed $record = null): bool
    {
        return parent::delete($user) && $record?->getKey() !== $user->getKey();
    }
}
