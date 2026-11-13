<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Exceptions\BookingReviewException;
use App\Jobs\ReleaseExpiredHolds;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BookingReviewService;
use App\Services\BookingService;
use App\Services\Payment\PaymentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Food billed to a stay must count on every screen, and pre-orders follow their booking's life. */
class BookingBillConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private MenuItem $coffee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 4,
            'base_price_weekday' => 500000, 'base_price_weekend' => 500000,
        ]);
        $this->unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'D-01', 'status' => Unit::STATUS_ACTIVE]);
        $category = MenuCategory::create(['name' => 'Minuman', 'sort_order' => 1]);
        $this->coffee = MenuItem::create(['category_id' => $category->id, 'name' => 'Kopi', 'price' => 20000, 'is_available' => true]);
    }

    private function booking(): Booking
    {
        $customer = Customer::create(['name' => 'Budi', 'email' => 'budi@example.com', 'phone' => '081234567890']);

        return app(BookingService::class)->createBooking(
            $customer->id, now()->addDays(10)->toDateString(), now()->addDays(11)->toDateString(), 2, [$this->unit->id],
        );
    }

    private function food(Booking $booking, string $source, string $status = 'baru'): Order
    {
        $order = $booking->orders()->create([
            'code' => 'ORD-'.strtoupper(substr(uniqid(), -6)), 'source' => $source, 'status' => $status,
            'total' => 40000, 'payment_status' => 'unpaid', 'bill_to_booking' => true,
        ]);
        $order->items()->create(['menu_item_id' => $this->coffee->id, 'qty' => 2, 'price' => 20000]);

        return $order;
    }

    private function owner(): User
    {
        $user = User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'secret-pass-123', 'is_active' => true]);
        $user->assignRole('owner');

        return $user;
    }

    public function test_review_is_refused_while_food_billed_to_the_stay_is_unpaid(): void
    {
        $booking = $this->booking();
        $booking->update(['status' => BookingStatus::NeedsReview, 'paid_amount' => $booking->total, 'review_started_at' => now()]);
        $this->food($booking, Order::SOURCE_QR);

        try {
            app(BookingReviewService::class)->moveToFreeUnit($booking, $this->owner());
            $this->fail('Expected a rejection for unpaid food.');
        } catch (BookingReviewException $e) {
            $this->assertStringContainsString('Rp 40.000', $e->getMessage());
        }

        $this->assertSame(BookingStatus::NeedsReview, $booking->fresh()->status);
    }

    public function test_expired_hold_cancels_open_preorders_and_a_revival_brings_them_back(): void
    {
        $booking = $this->booking();
        $open = $this->food($booking, Order::SOURCE_PREORDER);
        $cancelledByStaff = $this->food($booking, Order::SOURCE_PREORDER, 'dibatalkan');
        $booking->update(['hold_expires_at' => now()->subMinute()]);

        (new ReleaseExpiredHolds)->handle();

        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->assertSame('dibatalkan', $open->fresh()->status);
        $this->assertNotNull($open->fresh()->auto_cancelled_at);
        $this->assertNull($cancelledByStaff->fresh()->auto_cancelled_at);

        $revived = DB::transaction(fn () => app(PaymentService::class)->reviveLapsed(
            Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail(), now()->addDay(),
        ));

        $this->assertTrue($revived);
        $this->assertSame('baru', $open->fresh()->status);
        $this->assertNull($open->fresh()->auto_cancelled_at);
        $this->assertSame('dibatalkan', $cancelledByStaff->fresh()->status);
    }

    public function test_guest_status_page_total_includes_food_billed_to_the_stay(): void
    {
        $booking = $this->booking();
        $booking->update(['status' => BookingStatus::Paid, 'paid_amount' => $booking->total]);
        $this->food($booking, Order::SOURCE_QR);
        $grand = 'Rp '.number_format($booking->total + 40000, 0, ',', '.');

        $this->get(route('booking.status', $booking->access_token))->assertOk()->assertSee($grand);
    }
}
