<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\QrPaymentChoice;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\DiningSpot;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\DiningSpotService;
use App\Services\OrderService;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FoodReportingTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $satay;

    private DiningSpot $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $category = MenuCategory::create(['name' => 'Makanan', 'sort_order' => 1]);
        $this->satay = MenuItem::create(['category_id' => $category->id, 'name' => 'Sate Maranggi', 'price' => 35000, 'is_available' => true]);
        $this->table = app(DiningSpotService::class)->create('Meja 1', DiningSpot::TYPE_TABLE);
    }

    private function order(int $qty, ?DiningSpot $spot = null): Order
    {
        return app(OrderService::class)->createQrOrder($spot ?? $this->table, [['menu_item_id' => $this->satay->id, 'qty' => $qty]], 'Sari', null, QrPaymentChoice::Cashier);
    }

    private function staff(string $role): User
    {
        $user = User::create(['name' => $role, 'email' => $role.Str::random(5).'@example.test', 'password' => Str::random(24), 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function bookingWithFood(BookingStatus $status, int $qty): Booking
    {
        $customer = Customer::create(['name' => 'Tamu', 'phone' => '0812'.random_int(10000000, 99999999)]);
        $booking = Booking::create([
            'code' => 'BK-'.Str::random(6), 'customer_id' => $customer->id, 'check_in' => '2026-12-01', 'check_out' => '2026-12-02',
            'guests' => 2, 'status' => $status, 'subtotal' => 100000, 'total' => 100000, 'paid_amount' => 0,
        ]);
        app(OrderService::class)->createOrder(Order::SOURCE_PREORDER, [['menu_item_id' => $this->satay->id, 'qty' => $qty]], $booking->id);

        return $booking;
    }

    public function test_top_menu_splits_booking_food_from_qr_scans_in_tents_and_the_cafe(): void
    {
        $this->bookingWithFood(BookingStatus::Paid, 2);
        $tent = app(DiningSpotService::class)->create('DOM-01', DiningSpot::TYPE_TENT);
        foreach ([[$this->table, 3], [$tent, 1]] as [$spot, $qty]) {
            app(OrderService::class)->markPaidAtCashier($this->order($qty, $spot), PaymentMethod::Cash, $this->staff(User::ROLE_CASHIER)->id);
        }
        $this->bookingWithFood(BookingStatus::Expired, 5);

        $report = app(ReportService::class)->topMenu(ReportPeriod::thisMonth());

        $this->assertSame([['Sate Maranggi', 2, 1, 3, 0, 6, 210000]], $report->rows);
    }

    public function test_booking_report_keeps_the_tent_apart_from_food_ordered_with_it(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00'));
        $booking = $this->bookingWithFood(BookingStatus::Paid, 2);
        $booking->update(['paid_amount' => 100000]);
        // QR food billed to the stay sits outside booking.total and must not shrink the tent figure.
        app(OrderService::class)->createOrder(Order::SOURCE_QR, [['menu_item_id' => $this->satay->id, 'qty' => 1]], $booking->id, $this->table->id, null, 'Tamu', null, true);

        $report = app(ReportService::class)->bookings(ReportPeriod::thisMonth());

        $this->assertSame([$booking->code, 'Tamu', '01/12/2026', '02/12/2026', 'Lunas', 30000, 0, 105000, 135000, 100000], $report->rows[0]);
        $summary = array_column($report->summary, 'value', 'label');
        $this->assertSame(30000, $summary['Sewa tenda, lunas (Rp)']);
        $this->assertSame(105000, $summary['Makanan, lunas (Rp)']);
    }

    public function test_served_orders_nobody_paid_for_show_in_the_unpaid_tab(): void
    {
        Filament::setCurrentPanel(Filament::getDefaultPanel());
        $served = $this->order(1);
        foreach ([OrderStatus::Diproses, OrderStatus::Siap, OrderStatus::Diantar, OrderStatus::Selesai] as $step) {
            app(OrderService::class)->updateStatus($served, $step);
        }
        $paid = $this->order(1);
        app(OrderService::class)->markPaidAtCashier($paid, PaymentMethod::Cash, $this->staff(User::ROLE_CASHIER)->id);

        $this->actingAs($this->staff('owner'));
        Livewire::test(ListOrders::class)
            ->set('activeTab', 'unpaid')
            ->assertCanSeeTableRecords([$served])
            ->assertCanNotSeeTableRecords([$paid]);
    }
}
