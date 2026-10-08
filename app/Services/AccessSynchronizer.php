<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Applies config/access.php to the database. Safe to run on every deploy.
 */
class AccessSynchronizer
{
    private const GUARD = 'web';

    public function sync(): void
    {
        DB::transaction(function (): void {
            $all = $this->allPermissionNames();

            foreach ($all as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
            }

            foreach (config('access.roles') as $roleName => $definition) {
                $permissions = $definition === '*' ? $all : $this->permissionsFor($definition);

                Role::firstOrCreate(['name' => $roleName, 'guard_name' => self::GUARD])
                    ->syncPermissions($permissions);
            }

            $this->migrateLegacyRoles();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    public function allPermissionNames(): array
    {
        $resourcePermissions = [];

        foreach (config('access.resources') as $resource) {
            foreach (config('access.abilities') as $ability) {
                $resourcePermissions[] = $ability.'_'.$resource;
            }
        }

        return [...$resourcePermissions, ...config('access.functional_permissions')];
    }

    /**
     * @param  array{resources?: array<string, list<string>>, functional?: list<string>}  $definition
     * @return list<string>
     */
    private function permissionsFor(array $definition): array
    {
        $permissions = $definition['functional'] ?? [];

        foreach ($definition['resources'] ?? [] as $resource => $abilities) {
            foreach ($abilities as $ability) {
                $permissions[] = $ability.'_'.$resource;
            }
        }

        return $permissions;
    }

    /** Moves holders of the pre-PRD roles to their replacement, then removes the old roles. */
    private function migrateLegacyRoles(): void
    {
        foreach (config('access.legacy_role_map') as $legacyName => $replacement) {
            $legacy = Role::where('name', $legacyName)->where('guard_name', self::GUARD)->first();

            if (! $legacy) {
                continue;
            }

            User::role($legacyName)->get()->each(function (User $user) use ($legacyName, $replacement): void {
                $user->assignRole($replacement);
                $user->removeRole($legacyName);
            });

            $legacy->delete();
        }
    }
}
