<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OrderEditShowsItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_page_lists_what_was_ordered(): void
    {
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());
        $menu = MenuItem::create(['category_id' => MenuCategory::create(['name' => 'Minuman', 'sort_order' => 1])->id, 'name' => 'Cappucino', 'price' => 25000, 'is_available' => true]);
        $order = app(OrderService::class)->createOrder(Order::SOURCE_QR, [['menu_item_id' => $menu->id, 'qty' => 2]]);
        $owner = User::create(['name' => 'o', 'email' => Str::random(6).'@example.test', 'password' => Str::random(24), 'is_active' => true]);
        $owner->assignRole('owner');

        $this->actingAs($owner);
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee('2x Cappucino, Rp 50.000');
    }
}
