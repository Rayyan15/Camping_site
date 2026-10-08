<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Unit;
use App\Models\UnitType;
use App\Services\OrderService;
use App\Services\Payment\PaymentIntent;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private UnitType $unitType;

    private Addon $extraBed;

    private MenuItem $coffee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01 10:00:00');

        $this->unitType = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 2,
            'base_price_weekday' => 100000, 'base_price_weekend' => 150000,
        ]);
        Unit::create(['unit_type_id' => $this->unitType->id, 'code' => 'D1', 'status' => Unit::STATUS_ACTIVE]);
        Unit::create(['unit_type_id' => $this->unitType->id, 'code' => 'D2', 'status' => Unit::STATUS_ACTIVE]);

        $this->extraBed = Addon::create(['name' => 'Extra Bed', 'price' => 75000, 'unit' => Addon::UNIT_PER_NIGHT, 'is_active' => true]);
        $category = MenuCategory::create(['name' => 'Minuman', 'sort_order' => 1]);
        $this->coffee = MenuItem::create(['category_id' => $category->id, 'name' => 'Kopi', 'price' => 12000, 'is_available' => true]);
    }

    /**
     * Mon 2026-12-07 to Wed 2026-12-09: two weekday nights.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'unit_type_id' => $this->unitType->id,
            'check_in' => '2026-12-07',
            'check_out' => '2026-12-09',
            'guests' => 2,
            'quantity' => 1,
            'customer_name' => 'Budi Santoso',
            'customer_email' => 'budi@example.com',
            'customer_phone' => '0812-3456-7890',
            'notes' => 'Tiba sore',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function fullPayload(): array
    {
        return $this->payload([
            'guests' => 3,
            'addons' => [$this->extraBed->id => 1],
            'preorder' => [$this->coffee->id => ['qty' => 2, 'serve_date' => '2026-12-07', 'serve_time' => '08:00']],
        ]);
    }

    public function test_booking_with_addon_and_preorder_has_correct_totals(): void
    {
        $response = $this->post(route('booking.store'), $this->fullPayload());

        $booking = Booking::firstOrFail();
        $response->assertRedirect(route('checkout.show', $booking->code));

        // Unit 2 nights x 100000, extra bed 75000 x 1 x 2 nights, coffee 2 x 12000.
        $this->assertSame(200000 + 150000 + 24000, $booking->subtotal);
        $this->assertSame(41140, $booking->tax);
        $this->assertSame(374000 + 41140, $booking->total);
        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertEquals(now()->addMinutes(15), $booking->hold_expires_at);
        $this->assertSame('6281234567890', $booking->customer->phone);

        $this->assertSame(150000, $booking->addons->sole()->subtotal);

        $order = $booking->orders->sole();
        $this->assertSame(Order::SOURCE_PREORDER, $order->source);
        $this->assertSame(24000, $order->total);
        $this->assertSame('2026-12-07 08:00:00', $order->scheduled_at->toDateTimeString());
    }

    public function test_each_serve_slot_gets_its_own_order(): void
    {
        $this->post(route('booking.store'), $this->payload([
            'preorder' => [
                ['menu_item_id' => $this->coffee->id, 'qty' => 1, 'serve_date' => '2026-12-07', 'serve_time' => '08:00'],
                ['menu_item_id' => $this->coffee->id, 'qty' => 1, 'serve_date' => '2026-12-08', 'serve_time' => '08:00'],
            ],
        ]));

        $this->assertSame(2, Booking::firstOrFail()->orders()->count());
    }

    public function test_booking_code_follows_unambiguous_format(): void
    {
        $this->post(route('booking.store'), $this->payload());

        $this->assertMatchesRegularExpression('/^RCM-261201-[A-HJ-NP-Z2-9]{6}$/', Booking::firstOrFail()->code);
    }

    public function test_overlapping_booking_is_rejected(): void
    {
        $this->post(route('booking.store'), $this->payload(['quantity' => 2, 'guests' => 4]))->assertRedirect();

        $this->post(route('booking.store'), $this->payload(['customer_email' => 'lain@example.com']))
            ->assertSessionHas('error');

        $this->assertSame(1, Booking::count());
    }

    public function test_validation_rejects_bad_input(): void
    {
        $this->post(route('booking.store'), $this->payload([
            'customer_phone' => '12345',
            'customer_email' => 'bukan-email',
            'check_in' => '2026-11-01',
            'quantity' => 0,
        ]))->assertSessionHasErrors(['customer_phone', 'customer_email', 'check_in', 'quantity']);

        $this->post(route('booking.store'), $this->payload(['guests' => 9]))->assertSessionHasErrors('guests');

        $this->post(route('booking.store'), $this->payload([
            'preorder' => [$this->coffee->id => ['qty' => 1, 'serve_date' => '2026-12-20', 'serve_time' => '08:00']],
        ]))->assertSessionHasErrors('preorder.0.serve_date');

        $this->assertSame(0, Booking::count());
    }

    public function test_client_cannot_inject_prices(): void
    {
        $this->post(route('booking.store'), $this->payload(['total' => 1, 'subtotal' => 1]));

        $this->assertSame(200000, Booking::firstOrFail()->subtotal);
    }

    public function test_availability_page_renders_booking_form(): void
    {
        $this->get(route('booking.cek', [
            'unit_type_id' => $this->unitType->id, 'check_in' => '2026-12-07', 'check_out' => '2026-12-09', 'guests' => 2,
        ]))->assertOk()->assertSee('Extra Bed')->assertSee('Kopi')->assertSee('Perkiraan biaya');
    }

    public function test_checkout_page_shows_summary_and_countdown(): void
    {
        $this->post(route('booking.store'), $this->fullPayload());
        $booking = Booking::firstOrFail();

        $this->get(route('checkout.show', $booking->code))
            ->assertOk()
            ->assertSee($booking->code)
            ->assertSee('Extra Bed')
            ->assertSee('Kopi')
            ->assertSee('Bayar sekarang')
            ->assertSee('data-remaining="900"', false);
    }

    public function test_checkout_shows_expired_state_after_hold_lapses(): void
    {
        $this->post(route('booking.store'), $this->payload());
        $booking = Booking::firstOrFail();

        $this->travel(16)->minutes();

        $this->get(route('checkout.show', $booking->code))
            ->assertOk()
            ->assertSee('Waktu pembayaran habis')
            ->assertDontSee('Bayar sekarang');

        $this->post(route('checkout.pay', $booking->code))->assertRedirect(route('checkout.show', $booking->code));
    }

    public function test_checkout_unknown_code_is_404_and_paid_booking_redirects(): void
    {
        $this->get(route('checkout.show', 'RCM-000000-AAAAAA'))->assertNotFound();

        $this->post(route('booking.store'), $this->payload());
        $booking = Booking::firstOrFail();
        $booking->update(['status' => BookingStatus::Paid]);

        $this->get(route('checkout.show', $booking->code))->assertRedirect(route('booking.status', $booking->code));
    }

    public function test_pay_redirects_to_gateway_url(): void
    {
        $this->mock(PaymentService::class)
            ->shouldReceive('initiate')
            ->once()
            ->andReturn(new PaymentIntent('https://pay.example.test/abc', 'ref-1'));

        $this->post(route('booking.store'), $this->payload());
        $booking = Booking::firstOrFail();

        $this->post(route('checkout.pay', $booking->code))->assertRedirect('https://pay.example.test/abc');
    }

    public function test_order_status_only_moves_one_step_forward(): void
    {
        $service = app(OrderService::class);
        $order = $service->createOrder(Order::SOURCE_PREORDER, [['menu_item_id' => $this->coffee->id, 'qty' => 1]]);

        $service->updateStatus($order, OrderStatus::Diproses);
        $this->assertSame('diproses', $order->fresh()->status);

        $this->expectException(InvalidOrderTransitionException::class);
        $service->updateStatus($order, OrderStatus::Selesai);
    }
}
