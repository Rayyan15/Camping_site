<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\QrPaymentChoice;
use App\Filament\Pages\DailyOrders;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\DiningSpot;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\DailyOrdersService;
use App\Services\DiningSpotService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DailyOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_day_lists_stays_by_what_they_bought_and_food_sold_without_a_stay(): void
    {
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-12-05 12:00'));
        $menu = MenuItem::create(['category_id' => MenuCategory::create(['name' => 'Makanan', 'sort_order' => 1])->id, 'name' => 'Paket BBQ', 'price' => 190000, 'is_available' => true]);
        $cafe = app(DiningSpotService::class)->create('Meja 1', DiningSpot::TYPE_TABLE);
        $stay = fn (string $code, string $in, string $out) => Booking::create([
            'code' => $code, 'customer_id' => Customer::create(['name' => $code, 'phone' => '0812'.random_int(10000000, 99999999)])->id,
            'check_in' => $in, 'check_out' => $out, 'guests' => 2, 'status' => BookingStatus::CheckedIn,
            'subtotal' => 720000, 'total' => 720000, 'paid_amount' => 720000,
        ]);

        $tentOnly = $stay('BK-TENT', '2026-12-05', '2026-12-06');
        $withFood = $stay('BK-FOOD', '2026-12-04', '2026-12-06');
        $stay('BK-GONE', '2026-12-03', '2026-12-05');
        app(OrderService::class)->createOrder(Order::SOURCE_QR, [['menu_item_id' => $menu->id, 'qty' => 1]], $withFood->id, $cafe->id, null, 'Tamu', null, true);
        app(OrderService::class)->createQrOrder($cafe, [['menu_item_id' => $menu->id, 'qty' => 2]], 'Sari', null, QrPaymentChoice::Cashier);

        $rows = collect(app(DailyOrdersService::class)->forDate(CarbonImmutable::parse('2026-12-05')))->keyBy('code');

        $this->assertSame(DailyOrdersService::TENT, $rows['BK-TENT']['category']);
        $this->assertSame(DailyOrdersService::TENT_FOOD, $rows['BK-FOOD']['category']);
        $this->assertSame(190000, $rows['BK-FOOD']['owed']);
        $this->assertFalse($rows->has('BK-GONE'), 'A guest who left that morning is not in house that night.');
        $this->assertSame([DailyOrdersService::FOOD], $rows->except(['BK-TENT', 'BK-FOOD'])->pluck('category')->values()->all());

        Filament::setCurrentPanel(Filament::getDefaultPanel());
        $owner = User::create(['name' => 'o', 'email' => Str::random(6).'@example.test', 'password' => Str::random(24), 'is_active' => true]);
        $owner->assignRole('owner');
        $this->actingAs($owner);
        Livewire::test(DailyOrders::class)
            ->assertSee('BK-FOOD')->assertSee('2 order belum lunas')
            ->set('category', DailyOrdersService::TENT)->assertSee('BK-TENT')->assertDontSee('BK-FOOD')
            ->set('category', '')->call('shiftDay', 2)->assertSet('date', '2026-12-07')->assertSee('Tidak ada tamu menginap');
    }
}
