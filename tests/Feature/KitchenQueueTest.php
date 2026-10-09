<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidOrderTransitionException;
use App\Filament\Pages\KitchenQueue;
use App\Filament\Pages\WalkInOrder;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\KitchenQueueService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KitchenQueueTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $coffee;

    protected function setUp(): void
    {
        parent::setUp();

        $category = MenuCategory::create(['name' => 'Minuman', 'sort_order' => 1]);
        $this->coffee = MenuItem::create(['category_id' => $category->id, 'name' => 'Kopi', 'price' => 12000, 'is_available' => true]);
    }

    private function booking(BookingStatus $status): Booking
    {
        $customer = Customer::create(['name' => 'Budi', 'phone' => '0812']);

        return Booking::create([
            'code' => 'RCM-'.fake()->unique()->bothify('??????'), 'customer_id' => $customer->id,
            'check_in' => now()->toDateString(), 'check_out' => now()->addDay()->toDateString(), 'guests' => 2,
            'status' => $status, 'subtotal' => 1000, 'tax' => 110, 'total' => 1110, 'paid_amount' => 1110,
        ]);
    }

    private function preorder(Booking $booking, \DateTimeInterface $serveAt): Order
    {
        return app(OrderService::class)->createOrder(
            Order::SOURCE_PREORDER,
            [['menu_item_id' => $this->coffee->id, 'qty' => 1]],
            $booking->id,
            null,
            CarbonImmutable::instance($serveAt),
        );
    }

    private function staff(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        return $user;
    }

    /**
     * @return array<int, int>
     */
    private function boardIds(): array
    {
        return collect(app(KitchenQueueService::class)->board())->flatten()->pluck('id')->all();
    }

    public function test_preorder_shows_only_for_paid_booking_inside_the_window(): void
    {
        $paid = $this->booking(BookingStatus::Paid);
        $pending = $this->booking(BookingStatus::PendingPayment);

        $today = $this->preorder($paid, now()->setTime(12, 0));
        $tomorrow = $this->preorder($paid, now()->addDay()->setTime(8, 0));
        $dayAfter = $this->preorder($paid, now()->addDays(2)->setTime(8, 0));
        $unpaidBooking = $this->preorder($pending, now()->setTime(12, 0));

        $ids = $this->boardIds();

        $this->assertContains($today->id, $ids);
        $this->assertContains($tomorrow->id, $ids);
        $this->assertNotContains($dayAfter->id, $ids);
        $this->assertNotContains($unpaidBooking->id, $ids);
    }

    public function test_preorder_of_expired_or_cancelled_booking_is_hidden(): void
    {
        $expired = $this->preorder($this->booking(BookingStatus::Expired), now()->setTime(12, 0));
        $cancelled = $this->preorder($this->booking(BookingStatus::Cancelled), now()->setTime(12, 0));
        $checkedIn = $this->preorder($this->booking(BookingStatus::CheckedIn), now()->setTime(12, 0));

        $ids = $this->boardIds();

        $this->assertNotContains($expired->id, $ids);
        $this->assertNotContains($cancelled->id, $ids);
        $this->assertContains($checkedIn->id, $ids);
        $this->assertSame(1, app(KitchenQueueService::class)->activeCount());
    }

    public function test_qr_and_walkin_orders_show_for_today(): void
    {
        $service = app(OrderService::class);
        $items = [['menu_item_id' => $this->coffee->id, 'qty' => 1]];
        $qr = $service->createOrder(Order::SOURCE_QR, $items, null, null, null, 'Sari', null, false);
        $walkin = $service->createWalkinOrder($items, 'Dewi', null, null, $this->staff()->id);
        $old = $service->createOrder(Order::SOURCE_QR, $items);
        $old->forceFill(['created_at' => now()->subDays(2)])->save();

        $board = app(KitchenQueueService::class)->board();

        $this->assertSame([$qr->id, $walkin->id], $board['baru']->pluck('id')->all());
        $this->assertNotContains($old->id, $this->boardIds());
        $this->assertCount(5, $board);
    }

    public function test_status_only_moves_one_step_forward(): void
    {
        $service = app(OrderService::class);
        $order = $service->createOrder(Order::SOURCE_QR, [['menu_item_id' => $this->coffee->id, 'qty' => 1]]);

        $service->updateStatus($order, OrderStatus::Diproses);
        $this->assertSame('diproses', $order->fresh()->status);

        try {
            $service->updateStatus($order, OrderStatus::Diantar);
            $this->fail('Skipping a step must be refused.');
        } catch (InvalidOrderTransitionException) {
            $this->assertSame('diproses', $order->fresh()->status);
        }

        $this->expectException(InvalidOrderTransitionException::class);
        $service->updateStatus($order, OrderStatus::Baru);
    }

    public function test_walkin_order_is_created_and_can_be_paid_immediately(): void
    {
        $user = $this->staff('create_walkin_orders');

        $order = app(OrderService::class)->createWalkinOrder(
            [['menu_item_id' => $this->coffee->id, 'qty' => 3, 'notes' => 'panas']],
            'Dewi',
            null,
            PaymentMethod::Cash,
            $user->id,
        );

        $this->assertSame(Order::SOURCE_WALKIN, $order->source);
        $this->assertSame(36000, $order->total);
        $this->assertTrue($order->isPaid());
        $this->assertSame($user->id, $order->payments()->firstOrFail()->recorded_by);
    }

    public function test_kitchen_page_advances_orders_only_for_permitted_staff(): void
    {
        $order = app(OrderService::class)->createOrder(Order::SOURCE_QR, [['menu_item_id' => $this->coffee->id, 'qty' => 1]]);

        $this->actingAs($this->staff('view_kitchen_queue'));
        Livewire::test(KitchenQueue::class)->call('advance', $order->id)->assertForbidden();
        $this->assertSame('baru', $order->fresh()->status);

        $this->actingAs($this->staff('view_kitchen_queue', 'process_orders'));
        Livewire::test(KitchenQueue::class)->assertSee($order->code)->call('advance', $order->id);
        $this->assertSame('diproses', $order->fresh()->status);
    }

    public function test_pages_are_gated_by_permission(): void
    {
        $this->actingAs($this->staff());
        $this->assertFalse(KitchenQueue::canAccess());
        $this->assertFalse(WalkInOrder::canAccess());

        $this->actingAs($this->staff('view_kitchen_queue', 'create_walkin_orders'));
        $this->assertTrue(KitchenQueue::canAccess());
        $this->assertTrue(WalkInOrder::canAccess());
    }
}
