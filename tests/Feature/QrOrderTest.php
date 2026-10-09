<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\QrPaymentChoice;
use App\Exceptions\OrderBillingException;
use App\Models\Booking;
use App\Models\BookingUnit;
use App\Models\Customer;
use App\Models\DiningSpot;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\DiningSpotService;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use Database\Seeders\DiningSpotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrOrderTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $coffee;

    private MenuItem $soldOut;

    private DiningSpot $table;

    private DiningSpot $tent;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $category = MenuCategory::create(['name' => 'Minuman', 'sort_order' => 1]);
        $this->coffee = MenuItem::create(['category_id' => $category->id, 'name' => 'Kopi Tubruk', 'price' => 12000, 'is_available' => true]);
        $this->soldOut = MenuItem::create(['category_id' => $category->id, 'name' => 'Teh Habis', 'price' => 8000, 'is_available' => false]);

        $type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'description' => 'Dome', 'capacity' => 4,
            'facilities' => [], 'base_price_weekday' => 500000, 'base_price_weekend' => 600000,
        ]);
        $this->unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'DOM-01', 'status' => 'active']);

        $spots = app(DiningSpotService::class);
        $this->table = $spots->create('Meja 1', DiningSpot::TYPE_TABLE);
        $this->tent = $spots->create('Tenda DOM-01', DiningSpot::TYPE_TENT, $this->unit->id);
    }

    private function stay(BookingStatus $status, string $checkIn, string $checkOut): Booking
    {
        $customer = Customer::create(['name' => 'Budi Santoso', 'phone' => '081234567890']);
        $booking = Booking::create([
            'code' => 'RCM-TEST-'.fake()->unique()->bothify('????##'), 'customer_id' => $customer->id,
            'check_in' => $checkIn, 'check_out' => $checkOut, 'guests' => 2, 'status' => $status,
            'subtotal' => 500000, 'tax' => 55000, 'total' => 555000, 'paid_amount' => 555000,
        ]);
        BookingUnit::create([
            'booking_id' => $booking->id, 'unit_id' => $this->unit->id,
            'check_in' => $checkIn, 'check_out' => $checkOut,
            'price_per_night' => 500000, 'nights' => 1, 'subtotal' => 500000,
        ]);

        return $booking;
    }

    private function activeStay(): Booking
    {
        return $this->stay(BookingStatus::CheckedIn, now()->subDay()->toDateString(), now()->addDay()->toDateString());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Sari',
            'customer_phone' => '081200000000',
            'payment_choice' => 'cashier',
            'items' => [$this->coffee->id => ['qty' => 2, 'notes' => 'tanpa gula']],
        ], $overrides);
    }

    public function test_menu_page_opens_for_a_valid_token_and_hides_unavailable_items(): void
    {
        $this->get(route('qr.show', $this->table->qr_token))
            ->assertOk()
            ->assertSee('Meja 1')
            ->assertSee('Kopi Tubruk')
            ->assertDontSee('Teh Habis');
    }

    public function test_unknown_token_is_not_found(): void
    {
        $this->get(route('qr.show', 'does-not-exist'))->assertNotFound();
        $this->post(route('qr.store', 'does-not-exist'), $this->payload())->assertNotFound();
    }

    public function test_order_is_priced_from_the_database_and_starts_unpaid(): void
    {
        $response = $this->post(route('qr.store', $this->table->qr_token), $this->payload([
            'items' => [$this->coffee->id => ['qty' => 2, 'notes' => 'tanpa gula'], 999 => ['qty' => 0]],
            'price' => 1,
        ]));

        $order = Order::firstOrFail();
        $response->assertRedirect(route('qr.track', [$this->table->qr_token, $order->code]));

        $this->assertSame(24000, $order->total);
        $this->assertSame(Order::SOURCE_QR, $order->source);
        $this->assertSame('baru', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertFalse($order->bill_to_booking);
        $this->assertSame($this->table->id, $order->dining_spot_id);
        $this->assertSame('tanpa gula', $order->items->first()->notes);
        $this->assertSame(12000, $order->items->first()->price);
    }

    public function test_invalid_lines_are_rejected(): void
    {
        $url = route('qr.store', $this->table->qr_token);

        $this->post($url, $this->payload(['items' => [$this->soldOut->id => ['qty' => 1]]]))->assertSessionHasErrors('items.0.menu_item_id');
        $this->post($url, $this->payload(['items' => [$this->coffee->id => ['qty' => 21]]]))->assertSessionHasErrors('items.0.qty');
        $this->post($url, $this->payload(['items' => [$this->coffee->id => ['qty' => 1, 'notes' => str_repeat('a', 200)]]]))->assertSessionHasErrors('items.0.notes');
        $this->post($url, $this->payload(['items' => []]))->assertSessionHasErrors('items');
        $this->post($url, $this->payload(['customer_name' => '']))->assertSessionHasErrors('customer_name');

        $this->assertSame(0, Order::count());
    }

    public function test_bill_to_booking_is_refused_when_the_spot_has_no_active_booking(): void
    {
        $this->post(route('qr.store', $this->table->qr_token), $this->payload(['payment_choice' => 'booking']))
            ->assertSessionHasErrors('payment_choice');

        $this->stay(BookingStatus::PendingPayment, now()->toDateString(), now()->addDay()->toDateString());
        $this->stay(BookingStatus::Paid, now()->addDays(3)->toDateString(), now()->addDays(4)->toDateString());

        $this->post(route('qr.store', $this->tent->qr_token), $this->payload(['payment_choice' => 'booking']))
            ->assertSessionHasErrors('payment_choice');

        $this->assertSame(0, Order::count());
    }

    public function test_bill_to_booking_resolves_the_booking_on_the_server(): void
    {
        $booking = $this->activeStay();
        $other = $this->stay(BookingStatus::Paid, now()->addDays(10)->toDateString(), now()->addDays(11)->toDateString());

        $this->get(route('qr.show', $this->tent->qr_token))->assertSee('Tagihkan ke booking');

        $this->post(route('qr.store', $this->tent->qr_token), $this->payload([
            'payment_choice' => 'booking',
            'booking_id' => $other->id,
        ]))->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame($booking->id, $order->booking_id);
        $this->assertTrue($order->bill_to_booking);
    }

    public function test_tent_page_never_exposes_booking_code_or_guest_identity(): void
    {
        $booking = $this->activeStay();

        $response = $this->get(route('qr.show', $this->tent->qr_token))
            ->assertOk()
            ->assertSee('Tagihkan ke booking')
            ->assertDontSee($booking->code)
            ->assertDontSee('Budi Santoso')
            ->assertDontSee('081234567890');

        $this->assertStringNotContainsString('value="Budi Santoso"', $response->getContent());
    }

    public function test_tracking_a_billed_order_does_not_reveal_the_booking(): void
    {
        $booking = $this->activeStay();

        $this->post(route('qr.store', $this->tent->qr_token), $this->payload(['payment_choice' => 'booking']))->assertRedirect();
        $order = Order::firstOrFail();

        $this->get(route('qr.track', [$this->tent->qr_token, $order->code]))
            ->assertOk()
            ->assertSee('Ditagihkan ke booking Anda')
            ->assertDontSee($booking->code)
            ->assertDontSee('Budi Santoso');

        $this->getJson(route('qr.track', [$this->tent->qr_token, $order->code]))
            ->assertOk()
            ->assertJsonMissingPath('booking_id')
            ->assertJsonMissingPath('booking_code');
    }

    public function test_cashier_choice_on_a_tent_with_booking_is_not_billed_to_it(): void
    {
        $this->activeStay();

        $this->post(route('qr.store', $this->tent->qr_token), $this->payload())->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertNull($order->booking_id);
        $this->assertFalse($order->bill_to_booking);
    }

    public function test_service_rejects_billing_without_an_active_booking(): void
    {
        $this->expectException(OrderBillingException::class);

        app(OrderService::class)->createQrOrder(
            $this->table,
            [['menu_item_id' => $this->coffee->id, 'qty' => 1]],
            'Sari',
            null,
            QrPaymentChoice::Booking,
        );
    }

    public function test_order_is_tracked_only_through_its_own_dining_spot(): void
    {
        $this->post(route('qr.store', $this->table->qr_token), $this->payload());
        $order = Order::firstOrFail();

        $this->get(route('qr.track', [$this->table->qr_token, $order->code]))
            ->assertOk()
            ->assertSee($order->code)
            ->assertSee('Rp 24.000');

        $this->getJson(route('qr.track', [$this->table->qr_token, $order->code]))
            ->assertOk()
            ->assertJson(['status' => 'baru', 'step' => 0, 'paid' => false]);

        $this->get(route('qr.track', [$this->tent->qr_token, $order->code]))->assertNotFound();
        $this->get(route('qr.track', [$this->table->qr_token, 'ORD-000000-AAAAAA']))->assertNotFound();
    }

    public function test_regenerating_a_token_kills_the_old_qr(): void
    {
        $oldToken = $this->table->qr_token;

        app(DiningSpotService::class)->regenerateToken($this->table);

        $this->get(route('qr.show', $oldToken))->assertNotFound();
        $this->get(route('qr.show', $this->table->fresh()->qr_token))->assertOk();
        $this->assertSame(32, strlen($this->table->fresh()->qr_token));
        $this->assertNotSame($oldToken, $this->table->fresh()->qr_token);
    }

    public function test_order_codes_have_a_random_suffix(): void
    {
        $service = app(OrderService::class);
        $items = [['menu_item_id' => $this->coffee->id, 'qty' => 1]];

        $first = $service->createOrder(Order::SOURCE_QR, $items);
        $second = $service->createOrder(Order::SOURCE_QR, $items);

        $this->assertMatchesRegularExpression('/^ORD-\d{6}-[A-Z2-9]{6}$/', $first->code);
        $this->assertNotSame($first->code, $second->code);
    }

    public function test_cashier_payment_is_recorded_once(): void
    {
        $service = app(OrderService::class);
        $order = $service->createOrder(Order::SOURCE_QR, [['menu_item_id' => $this->coffee->id, 'qty' => 2]], null, $this->table->id, null, 'Sari', null, false);

        $cashier = User::factory()->create();
        $payment = $service->markPaidAtCashier($order, PaymentMethod::Cash, $cashier->id);

        $this->assertSame(24000, $payment->amount);
        $this->assertSame(PaymentMethod::Cash, $payment->method);
        $this->assertTrue($order->fresh()->isPaid());

        $this->expectException(OrderBillingException::class);
        $service->markPaidAtCashier($order, PaymentMethod::Cash, $cashier->id);
    }

    public function test_seeder_creates_spots_once(): void
    {
        $this->seed(DiningSpotSeeder::class);
        $count = DiningSpot::count();
        $token = DiningSpot::where('name', 'Meja 2')->value('qr_token');

        $this->seed(DiningSpotSeeder::class);

        $this->assertSame($count, DiningSpot::count());
        $this->assertSame($token, DiningSpot::where('name', 'Meja 2')->value('qr_token'));
        $this->assertSame(1, DiningSpot::where('unit_id', $this->unit->id)->count());
    }

    public function test_online_order_stays_out_of_the_kitchen_until_the_gateway_settles_it(): void
    {
        $this->post(route('qr.store', $this->table->qr_token), $this->payload(['payment_choice' => 'online']))
            ->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertTrue($order->awaitsOnlinePayment());
        $this->assertFalse(Order::kitchenRelevant()->whereKey($order->id)->exists());

        $payment = $order->payments()->firstOrFail();
        $this->assertSame($order->total, $payment->amount);

        app(PaymentService::class)->handleNotification([
            'order_id' => $payment->gateway_ref,
            'transaction_status' => 'settlement',
        ]);

        $this->assertTrue($order->fresh()->isPaid());
        $this->assertTrue(Order::kitchenRelevant()->whereKey($order->id)->exists());
    }

    public function test_unpaid_online_order_can_reopen_payment_from_the_tracking_page(): void
    {
        $this->post(route('qr.store', $this->table->qr_token), $this->payload(['payment_choice' => 'online']));
        $order = Order::firstOrFail();

        $this->get(route('qr.track', [$this->table->qr_token, $order->code]))
            ->assertOk()
            ->assertSee('Buka pembayaran QRIS');

        $this->post(route('qr.pay', [$this->table->qr_token, $order->code]))->assertRedirect();
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_turnover_day_bills_the_guest_who_checked_in_not_the_one_leaving(): void
    {
        $today = now()->toDateString();
        $leaving = $this->stay(BookingStatus::CheckedIn, now()->subDays(2)->toDateString(), $today);
        $arriving = $this->stay(BookingStatus::CheckedIn, $today, now()->addDays(2)->toDateString());

        $this->assertTrue(app(DiningSpotService::class)->activeBooking($this->tent)->is($arriving));
        $this->assertFalse(app(DiningSpotService::class)->activeBooking($this->tent)->is($leaving));
    }

    public function test_paid_no_show_never_beats_the_checked_in_guest(): void
    {
        $noShow = $this->stay(BookingStatus::Paid, now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $inTent = $this->stay(BookingStatus::CheckedIn, now()->toDateString(), now()->addDays(2)->toDateString());

        $this->assertTrue(app(DiningSpotService::class)->activeBooking($this->tent)->is($inTent));
        $this->assertNotNull($noShow);
    }
}
