<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Demo data exists only for local and testing. Every other environment, production above all,
     * receives the minimal ProductionSeeder and never any demo accounts, tents or menu.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->call(ProductionSeeder::class);

            return;
        }

        $this->call([
            RoleSeeder::class,
            UnitTypeSeeder::class,
            AddonSeeder::class,
            MenuSeeder::class,
            DiningSpotSeeder::class,
            DemoUserSeeder::class,
            RefundPolicySeeder::class,
        ]);
    }
}
