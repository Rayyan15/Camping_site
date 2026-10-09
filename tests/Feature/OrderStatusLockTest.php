<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Filament\Pages\KitchenQueue;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Services\KitchenQueueService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrderStatusLockTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $coffee;

    protected function setUp(): void
    {
        parent::setUp();

        $category = MenuCategory::create(['name' => 'Minuman', 'sort_order' => 1]);
        $this->coffee = MenuItem::create(['category_id' => $category->id, 'name' => 'Kopi', 'price' => 12000, 'is_available' => true]);
    }

    private function qrOrder(): Order
    {
        return app(OrderService::class)->createOrder(Order::SOURCE_QR, [['menu_item_id' => $this->coffee->id, 'qty' => 1]]);
    }

    private function staff(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        return $user;
    }

    public function test_skipping_or_going_back_is_refused_using_the_locked_status(): void
    {
        $order = $this->qrOrder();
        $stale = Order::findOrFail($order->id);

        app(OrderService::class)->updateStatus($order, OrderStatus::Diproses);

        // The stale copy still says baru, but the locked row says diproses, so baru -> diproses must fail.
        try {
            app(OrderService::class)->updateStatus($stale, OrderStatus::Diproses);
            $this->fail('A repeated transition must be refused.');
        } catch (InvalidOrderTransitionException) {
            $this->assertSame('diproses', $order->fresh()->status);
        }

        $this->expectException(InvalidOrderTransitionException::class);
        app(OrderService::class)->updateStatus($stale, OrderStatus::Diantar);
    }

    public function test_double_click_does_not_advance_two_steps(): void
    {
        $order = $this->qrOrder();
        $this->actingAs($this->staff('view_kitchen_queue', 'process_orders'));

        Livewire::test(KitchenQueue::class)
            ->call('advance', $order->id, 'diproses')
            ->call('advance', $order->id, 'diproses');

        $this->assertSame('diproses', $order->fresh()->status);
    }

    public function test_unknown_payment_method_is_rejected_without_error(): void
    {
        $order = $this->qrOrder();
        $this->actingAs($this->staff('view_kitchen_queue', 'record_order_payment'));

        Livewire::test(KitchenQueue::class)->call('markPaid', $order->id, 'bitcoin');
        Livewire::test(KitchenQueue::class)->call('markPaid', $order->id, 'midtrans');

        $this->assertFalse($order->fresh()->isPaid());
        $this->assertSame(0, $order->payments()->count());
    }

    public function test_marking_paid_requires_record_order_payment(): void
    {
        $order = $this->qrOrder();

        $this->actingAs($this->staff('view_kitchen_queue', 'process_orders'));
        Livewire::test(KitchenQueue::class)->call('markPaid', $order->id, 'cash')->assertForbidden();

        $this->actingAs($this->staff('view_kitchen_queue', 'record_order_payment'));
        Livewire::test(KitchenQueue::class)->call('markPaid', $order->id, 'cash');

        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_preorders_of_dead_bookings_are_hidden_and_not_counted(): void
    {
        $customer = Customer::create(['name' => 'Budi', 'phone' => '0812']);
        $makeBooking = fn (BookingStatus $status) => Booking::create([
            'code' => 'RCM-'.fake()->unique()->bothify('??????'), 'customer_id' => $customer->id,
            'check_in' => now()->toDateString(), 'check_out' => now()->addDay()->toDateString(), 'guests' => 2,
            'status' => $status, 'subtotal' => 1000, 'tax' => 110, 'total' => 1110, 'paid_amount' => 0,
        ]);
        $preorder = fn (Booking $booking) => app(OrderService::class)->createOrder(
            Order::SOURCE_PREORDER,
            [['menu_item_id' => $this->coffee->id, 'qty' => 1]],
            $booking->id,
            null,
            CarbonImmutable::instance(now()->setTime(12, 0)),
        );

        $live = $preorder($makeBooking(BookingStatus::CheckedIn));
        $hidden = collect([BookingStatus::Expired, BookingStatus::Cancelled, BookingStatus::Refunded, BookingStatus::NeedsReview, BookingStatus::PendingPayment])
            ->map(fn (BookingStatus $status) => $preorder($makeBooking($status)));
        $qr = $this->qrOrder();

        $ids = collect(app(KitchenQueueService::class)->board())->flatten()->pluck('id');

        $this->assertEqualsCanonicalizing([$live->id, $qr->id], $ids->all());
        $this->assertTrue($hidden->every(fn (Order $order) => ! $ids->contains($order->id)));
        $this->assertSame(2, app(KitchenQueueService::class)->activeCount());
        $this->assertSame(2, (new DashboardMetricsService)->activeFoodOrderCount());
    }
}
