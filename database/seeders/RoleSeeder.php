<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Buat Roles
        $owner = Role::firstOrCreate(['name' => 'owner']);
        $operator = Role::firstOrCreate(['name' => 'operator']);
        $finance = Role::firstOrCreate(['name' => 'finance']);

        // Buat User Owner
        $ownerUser = User::firstOrCreate(
            ['email' => 'owner@raynad.com'],
            [
                'name' => 'Bapak Raynad',
                'password' => Hash::make('password123'),
            ]
        );
        $ownerUser->assignRole($owner);

        // Buat User Operator
        $operatorUser = User::firstOrCreate(
            ['email' => 'operator@raynad.com'],
            [
                'name' => 'Resepsionis',
                'password' => Hash::make('password123'),
            ]
        );
        $operatorUser->assignRole($operator);
        
        // Buat User Finance
        $financeUser = User::firstOrCreate(
            ['email' => 'finance@raynad.com'],
            [
                'name' => 'Staff Keuangan',
                'password' => Hash::make('password123'),
            ]
        );
        $financeUser->assignRole($finance);
    }
}
