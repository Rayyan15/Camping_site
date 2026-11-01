<?php

namespace Tests\Feature;

use App\Filament\Pages\KitchenQueue;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\OrderService;
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
        $this->get('/admin/orders')->assertOk();
        $this->get('/admin/menu-items')->assertOk();

        foreach (['/admin/bookings', '/admin/order-walk-in', '/admin/laporan', '/admin/settings', '/admin/users', '/admin/payments', '/admin/customers', '/admin/cleaning-logs'] as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_kitchen_lands_on_the_queue_instead_of_the_dashboard(): void
    {
        $this->actingAs($this->kitchen());

        $this->get('/admin')->assertRedirect(KitchenQueue::getUrl());
    }

    public function test_kitchen_is_told_about_a_new_walkin_order(): void
    {
        $kitchen = $this->kitchen();
        $category = MenuCategory::create(['name' => 'Minuman', 'sort_order' => 1]);
        $coffee = MenuItem::create(['category_id' => $category->id, 'name' => 'Kopi', 'price' => 12000, 'is_available' => true]);

        $order = app(OrderService::class)->createWalkinOrder([['menu_item_id' => $coffee->id, 'qty' => 2]], 'Dewi', null, null, $kitchen->id);

        $notification = $kitchen->notifications()->sole();
        $this->assertStringContainsString($order->code, $notification->data['title']);
        $this->assertStringContainsString('2x Kopi', $notification->data['body']);
    }
}
