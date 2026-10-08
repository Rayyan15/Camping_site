<?php

namespace Database\Seeders;

use App\Services\AccessSynchronizer;
use Illuminate\Database\Seeder;

/**
 * Creates roles and permissions only. Accounts are never created here: demo users live in
 * DemoUserSeeder (local and testing) and the production owner comes from app:create-owner.
 */
class RoleSeeder extends Seeder
{
    public function run(AccessSynchronizer $access): void
    {
        $access->sync();
    }
}
