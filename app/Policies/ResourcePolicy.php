<?php

namespace App\Policies;

use App\Models\User;

/**
 * Maps Laravel/Filament policy methods to permission names such as view_any_booking.
 * Subclasses only declare which resource they guard.
 */
abstract class ResourcePolicy
{
    abstract protected function resource(): string;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view_any');
    }

    public function view(User $user, mixed $record = null): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, mixed $record = null): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, mixed $record = null): bool
    {
        return $this->allows($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'delete');
    }

    public function restore(User $user, mixed $record = null): bool
    {
        return false;
    }

    public function forceDelete(User $user, mixed $record = null): bool
    {
        return false;
    }

    protected function allows(User $user, string $ability): bool
    {
        return $user->is_active && $user->can($ability.'_'.$this->resource());
    }
}
