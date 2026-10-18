<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Demo data exists only for local, testing and the stakeholder demo server (APP_ENV=demo). Every other environment, production above all,
     * receives the minimal ProductionSeeder and never any demo accounts, tents or menu.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'demo'])) {
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
            SettingsSeeder::class,
            EvaluationCriteriaSeeder::class,
        ]);
    }
}
