<?php

namespace App\Services;

use App\Exceptions\LastOwnerException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staff account lifecycle for the admin panel and the app:create-owner command.
 */
class UserAccountService
{
    /**
     * @param  array{name: string, email: string, phone?: ?string, password: string, is_active?: bool}  $attributes
     */
    public function create(array $attributes, string $role): User
    {
        return DB::transaction(function () use ($attributes, $role): User {
            $user = User::create($attributes);
            $user->assignRole($role);

            return $user;
        });
    }

    /**
     * @param  array{name?: string, email?: string, phone?: ?string, is_active?: bool}  $attributes
     */
    public function update(User $user, array $attributes, string $role): User
    {
        return DB::transaction(function () use ($user, $attributes, $role): User {
            $stillActive = $attributes['is_active'] ?? $user->is_active;

            if ($user->isOwner() && (! $stillActive || $role !== User::ROLE_OWNER)) {
                $this->assertAnotherActiveOwnerExists($user);
            }

            $user->update($attributes);
            $user->syncRoles($role);

            return $user;
        });
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);
    }

    private function assertAnotherActiveOwnerExists(User $user): void
    {
        $others = User::role(User::ROLE_OWNER)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();

        if (! $others) {
            throw LastOwnerException::forChange();
        }
    }
}
