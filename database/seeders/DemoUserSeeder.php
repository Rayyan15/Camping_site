<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Local and testing accounts. The shared password comes from DEMO_PASSWORD; without it a random
 * one is generated and not shown, so set DEMO_PASSWORD before seeding to be able to sign in.
 */
class DemoUserSeeder extends Seeder
{
    private const ACCOUNTS = [
        ['email' => 'owner@raynad.test', 'name' => 'Owner Demo', 'role' => User::ROLE_OWNER],
        ['email' => 'fo@raynad.test', 'name' => 'Front Office Demo', 'role' => User::ROLE_FRONT_OFFICE],
        ['email' => 'kasir@raynad.test', 'name' => 'Kasir Demo', 'role' => User::ROLE_CASHIER],
    ];

    private const GENERATED_PASSWORD_LENGTH = 32;

    public function run(): void
    {
        $password = config('access.demo_password') ?: Str::random(self::GENERATED_PASSWORD_LENGTH);

        foreach (self::ACCOUNTS as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                ['name' => $account['name'], 'password' => $password],
            );

            $user->syncRoles($account['role']);
        }
    }
}
