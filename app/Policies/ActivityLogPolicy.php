<?php

namespace App\Policies;

use App\Models\User;

/**
 * The audit trail is append-only: nobody can create, edit or delete entries from the panel.
 */
class ActivityLogPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'activity_log';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, mixed $record = null): bool
    {
        return false;
    }

    public function delete(User $user, mixed $record = null): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
