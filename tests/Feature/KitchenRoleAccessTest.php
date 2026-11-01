<?php

namespace Tests\Feature;

use App\Filament\Pages\KitchenQueue;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KitchenRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function kitchen(): User
    {
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());

        $user = User::create(['name' => 'Dapur', 'email' => 'dapur'.Str::random(5).'@example.test', 'password' => Str::random(24), 'is_active' => true]);
        $user->assignRole(User::ROLE_KITCHEN);

        return $user;
    }

    public function test_kitchen_sees_the_order_queue_and_nothing_else(): void
    {
        $this->actingAs($this->kitchen());

        $this->get(KitchenQueue::getUrl())->assertOk();

        foreach (['/admin/bookings', '/admin/orders', '/admin/order-walk-in', '/admin/laporan', '/admin/settings', '/admin/users', '/admin/menu-items', '/admin/payments', '/admin/customers', '/admin/cleaning-logs'] as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_kitchen_lands_on_the_queue_instead_of_the_dashboard(): void
    {
        $this->actingAs($this->kitchen());

        $this->get('/admin')->assertRedirect(KitchenQueue::getUrl());
    }
}
