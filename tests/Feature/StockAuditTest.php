<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\DiningSpot;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\DiningSpotService;
use App\Services\MenuStockService;
use App\Services\OrderService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Proofs for docs/internal/stock-audit-2026-11-15.md. Each test asserts the correct outcome and fails today. */
class StockAuditTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $satay;

    private DiningSpot $table;

    private MenuStockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $category = MenuCategory::create(['name' => 'Makanan', 'sort_order' => 1]);
        $this->satay = MenuItem::create(['category_id' => $category->id, 'name' => 'Sate', 'price' => 35000, 'is_available' => true]);
        $this->table = app(DiningSpotService::class)->create('Meja 1', DiningSpot::TYPE_TABLE);
        $this->stock = app(MenuStockService::class);
    }

    private function booking(BookingStatus $status): Booking
    {
        $customer = Customer::create(['name' => 'Tamu', 'phone' => '0812'.random_int(10000000, 99999999)]);

        return Booking::create([
            'code' => 'BK-'.Str::random(6), 'customer_id' => $customer->id, 'check_in' => '2026-12-01', 'check_out' => '2026-12-02',
            'guests' => 2, 'status' => $status, 'subtotal' => 100000, 'total' => 100000, 'paid_amount' => 0,
        ]);
    }

    private function preorder(Booking $booking, int $qty): Order
    {
        return app(OrderService::class)->createOrder(Order::SOURCE_PREORDER, [['menu_item_id' => $this->satay->id, 'qty' => $qty]], $booking->id);
    }

    private function qr(int $qty, string $guest, ?Booking $billTo = null): Order
    {
        return app(OrderService::class)->createOrder(Order::SOURCE_QR, [['menu_item_id' => $this->satay->id, 'qty' => $qty]], $billTo?->id, $this->table->id, null, $guest);
    }

    public function test_serving_a_pre_order_placed_before_the_recount_does_not_put_portions_back(): void
    {
        $order = $this->preorder($this->booking(BookingStatus::Paid), 2);
        $this->travelTo(now()->addMinute());
        $this->stock->setRemaining($this->satay, 5);
        $this->assertSame(5, $this->stock->remainingFor($this->satay));

        $order->update(['status' => OrderStatus::Diantar->value, 'served_at' => now()]);

        $this->assertSame(5, $this->stock->remainingFor($this->satay->fresh()), 'Serving reserved food must not raise the sellable count.');
    }

    public function test_reviving_a_lapsed_booking_cannot_take_more_portions_than_exist(): void
    {
        $this->markTestSkipped('A2 waits for the owner decision D1 in docs/internal/stock-merge-plan.md.');

        $booking = $this->booking(BookingStatus::Paid);
        $this->stock->setRemaining($this->satay, 2);
        $this->preorder($booking, 2);

        $booking->update(['status' => BookingStatus::Expired]);
        app(OrderService::class)->releasePreorders($booking);
        $this->qr(2, 'Budi');

        $booking->update(['status' => BookingStatus::Paid]);
        app(OrderService::class)->restorePreorders($booking);

        $held = $this->stock->reservedFor($this->satay) + 2;
        $this->assertLessThanOrEqual($this->satay->fresh()->stock, $held, 'Revival oversold the dish.');
    }

    public function test_food_already_served_on_a_stay_stays_used_when_the_booking_is_cancelled(): void
    {
        $booking = $this->booking(BookingStatus::CheckedIn);
        $this->stock->setRemaining($this->satay, 5);
        $this->qr(3, 'Sari', $booking)->update(['status' => OrderStatus::Selesai->value, 'served_at' => now()]);
        $this->assertSame(2, $this->stock->remainingFor($this->satay));

        $booking->update(['status' => BookingStatus::Cancelled]);

        $this->assertSame(2, $this->stock->remainingFor($this->satay), 'Eaten food returned to stock.');
    }

    public function test_releasing_a_lapsed_booking_does_not_cancel_food_already_delivered(): void
    {
        $booking = $this->booking(BookingStatus::Paid);
        $this->stock->setRemaining($this->satay, 5);
        $this->preorder($booking, 2)->update(['status' => OrderStatus::Diantar->value, 'served_at' => now()]);

        app(OrderService::class)->releasePreorders($booking);

        $this->assertSame(OrderStatus::Diantar->value, $booking->orders()->first()->status);
    }
}
