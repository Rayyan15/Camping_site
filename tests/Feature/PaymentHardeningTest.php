<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentReviewReason;
use App\Enums\PaymentStatus;
use App\Enums\SettingKey;
use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\InvalidPaymentAmountException;
use App\Exceptions\InvalidPaymentSignatureException;
use App\Exceptions\ManualPaymentException;
use App\Exceptions\PaymentException;
use App\Jobs\ReleaseExpiredHolds;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundPolicy;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BookingBilling;
use App\Services\BookingService;
use App\Services\BookingStatusTransition;
use App\Services\CancellationOutcome;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use App\Services\RefundService;
use App\Services\SettingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-test';

    private Unit $unit;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01 10:00:00');
        config([
            'payment.gateway' => 'midtrans',
            'services.midtrans.server_key' => self::SERVER_KEY,
        ]);

        $type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 2,
            'base_price_weekday' => 100000, 'base_price_weekend' => 150000,
        ]);
        $this->unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'D1', 'status' => 'active']);
        $this->customer = Customer::create(['name' => 'Tamu', 'phone' => '0811', 'email' => 'tamu@example.com']);
    }

    /** Two weekday nights: subtotal 200000, tax 22000, total 222000. */
    private function book(): Booking
    {
        return app(BookingService::class)->createBooking(
            $this->customer->id, '2027-01-04', '2027-01-06', 2, [$this->unit->id],
        );
    }

    private function fakeSnap(): void
    {
        Http::fake(['*midtrans.com/*' => Http::response(['token' => 't', 'redirect_url' => 'https://pay.test/abc'], 201)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function notification(string $orderId, int $amount, string $status = 'settlement'): array
    {
        $gross = $amount.'.00';

        return [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $gross,
            'transaction_status' => $status,
            'signature_key' => hash('sha512', $orderId.'200'.$gross.self::SERVER_KEY),
        ];
    }

    private function addRoomBilledFood(Booking $booking, int $price = 20000, int $qty = 5): void
    {
        $category = MenuCategory::create(['name' => 'Minuman', 'sort_order' => 1]);
        $item = MenuItem::create(['category_id' => $category->id, 'name' => 'Kopi', 'price' => $price, 'is_available' => true]);

        $booking->orders()->create([
            'code' => 'ORD-X', 'source' => Order::SOURCE_QR, 'status' => 'baru',
            'total' => $price * $qty, 'payment_status' => Order::PAYMENT_UNPAID, 'bill_to_booking' => true,
        ])->items()->create(['menu_item_id' => $item->id, 'qty' => $qty, 'price' => $price]);
    }

    public function test_pay_twice_reuses_the_pending_payment(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        $service = app(PaymentService::class);

        $first = $service->initiate($booking);
        $second = $service->initiate($booking);

        $this->assertSame($first->reference, $second->reference);
        $this->assertSame('https://pay.test/abc', $second->redirectUrl);
        $this->assertSame(1, Payment::count());
        Http::assertSentCount(1);
    }

    public function test_pending_payment_is_not_reused_after_it_expired(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        $service = app(PaymentService::class);

        $first = $service->initiate($booking);
        $this->travel(config('booking.hold_minutes') + 1)->minutes();
        $second = $service->initiate($booking);

        $this->assertNotSame($first->reference, $second->reference);
        $this->assertSame(2, Payment::count());
    }

    public function test_pending_payment_is_not_reused_when_the_amount_changed(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        $service = app(PaymentService::class);

        $first = $service->initiate($booking);
        $service->recordManual($booking, 100000, 'cash', null, User::factory()->create()->id);
        $second = $service->initiate($booking->fresh());

        $this->assertNotSame($first->reference, $second->reference);
        $this->assertSame(122000, Payment::where('gateway_ref', $second->reference)->value('amount'));
    }

    public function test_second_link_paid_after_the_first_is_recorded_but_flagged_for_refund(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        $service = app(PaymentService::class);

        $first = $service->initiate($booking);
        $this->travel(config('booking.hold_minutes') + 1)->minutes();
        $second = $service->initiate($booking);

        $service->handleNotification($this->notification($first->reference, 222000));
        $service->handleNotification($this->notification($second->reference, 222000));

        $booking->refresh();
        $this->assertSame(222000, $booking->paid_amount);
        $this->assertSame(BookingStatus::Paid, $booking->status);

        $duplicate = Payment::where('gateway_ref', $second->reference)->firstOrFail();
        $this->assertSame(PaymentStatus::Paid, $duplicate->status);
        $this->assertSame(PaymentReviewReason::Overpaid, $duplicate->review_reason);
        $this->assertNull(Payment::where('gateway_ref', $first->reference)->value('review_reason'));
    }

    public function test_timeout_marks_payment_failed_with_a_network_reason(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));
        $booking = $this->book();

        try {
            app(PaymentService::class)->initiate($booking);
            $this->fail('Expected PaymentException');
        } catch (PaymentException $e) {
            $this->assertTrue($e->isNetworkFailure());
        }

        $payment = Payment::firstOrFail();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame(PaymentFailureReason::GatewayUnreachable, $payment->failure_reason);
    }

    public function test_settlement_after_a_network_failure_still_pays_the_booking(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));
        $booking = $this->book();
        $service = app(PaymentService::class);

        try {
            $service->initiate($booking);
        } catch (PaymentException) {
        }
        $reference = Payment::firstOrFail()->gateway_ref;

        $service->handleNotification($this->notification($reference, 222000));

        $payment = Payment::firstOrFail();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNull($payment->failure_reason);
        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);
        $this->assertSame(222000, $booking->fresh()->paid_amount);
    }

    public function test_network_failed_payment_still_rejects_bad_signature_and_wrong_amount(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));
        $booking = $this->book();
        $service = app(PaymentService::class);

        try {
            $service->initiate($booking);
        } catch (PaymentException) {
        }
        $reference = Payment::firstOrFail()->gateway_ref;

        $forged = $this->notification($reference, 222000);
        $forged['signature_key'] = 'forged';

        try {
            $service->handleNotification($forged);
            $this->fail('Expected signature rejection');
        } catch (InvalidPaymentSignatureException) {
        }

        try {
            $service->handleNotification($this->notification($reference, 1000));
            $this->fail('Expected amount rejection');
        } catch (InvalidPaymentAmountException) {
        }

        $this->assertSame(PaymentStatus::Failed, Payment::firstOrFail()->status);
        $this->assertSame(0, $booking->fresh()->paid_amount);
    }

    public function test_network_failed_payment_ignores_non_settlement_notifications(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));
        $booking = $this->book();
        $service = app(PaymentService::class);

        try {
            $service->initiate($booking);
        } catch (PaymentException) {
        }
        $reference = Payment::firstOrFail()->gateway_ref;

        $service->handleNotification($this->notification($reference, 222000, 'expire'));

        $this->assertSame(PaymentStatus::Failed, Payment::firstOrFail()->status);
    }

    public function test_definitive_gateway_rejection_ignores_later_settlement(): void
    {
        Http::fake(['*' => Http::response(['error' => 'x'], 500)]);
        $booking = $this->book();
        $service = app(PaymentService::class);

        try {
            $service->initiate($booking);
        } catch (PaymentException $e) {
            $this->assertFalse($e->isNetworkFailure());
        }
        $payment = Payment::firstOrFail();
        $this->assertNull($payment->failure_reason);

        $service->handleNotification($this->notification($payment->gateway_ref, 222000));

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(0, $booking->fresh()->paid_amount);
    }

    public function test_extra_food_with_tax_is_charged_online_and_settles_the_invoice(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        // 100000 of food plus 11 percent tax.
        $this->addRoomBilledFood($booking);
        $service = app(PaymentService::class);

        $intent = $service->initiate($booking);

        $this->assertSame(333000, Payment::where('gateway_ref', $intent->reference)->value('amount'));
        Http::assertSent(fn (Request $request) => $request['transaction_details']['gross_amount'] === 333000);

        $service->handleNotification($this->notification($intent->reference, 333000));

        $booking->refresh();
        $this->assertSame(333000, $booking->paid_amount);
        $this->assertSame(BookingStatus::Paid, $booking->status);

        $invoice = (new InvoiceService)->invoiceData($booking);
        $this->assertSame(333000, $invoice['grandTotal']);
        $this->assertSame(33000, $invoice['tax']);
        $this->assertSame(0, $invoice['balance']);
        $this->assertTrue($invoice['isSettled']);
    }

    public function test_food_ordered_after_payment_leaves_a_balance_payable_online(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        $service = app(PaymentService::class);
        $service->handleNotification($this->notification($service->initiate($booking)->reference, 222000));
        $this->addRoomBilledFood($booking);

        $intent = $service->initiate($booking->fresh());

        $this->assertSame(111000, Payment::where('gateway_ref', $intent->reference)->value('amount'));
    }

    public function test_manual_payment_rejects_non_positive_amounts(): void
    {
        $booking = $this->book();
        $user = User::factory()->create();

        foreach ([0, -5000] as $amount) {
            try {
                app(PaymentService::class)->recordManual($booking, $amount, 'cash', null, $user->id);
                $this->fail('Expected ManualPaymentException');
            } catch (ManualPaymentException) {
            }
        }

        $this->assertSame(0, Payment::count());
        $this->assertSame(0, $booking->fresh()->paid_amount);
    }

    public function test_manual_payment_cannot_exceed_the_outstanding_balance(): void
    {
        $booking = $this->book();
        $user = User::factory()->create();
        $service = app(PaymentService::class);

        $service->recordManual($booking, 200000, 'cash', null, $user->id);

        $this->expectException(ManualPaymentException::class);
        $this->expectExceptionMessage('sisa tagihan');

        try {
            $service->recordManual($booking, 22001, 'cash', null, $user->id);
        } finally {
            $this->assertSame(200000, $booking->fresh()->paid_amount);
        }
    }

    public function test_manual_payment_counts_extra_food_in_the_balance(): void
    {
        $booking = $this->book();
        $this->addRoomBilledFood($booking);

        $payment = app(PaymentService::class)->recordManual($booking, 333000, 'cash', null, User::factory()->create()->id);

        $this->assertSame(333000, $payment->amount);
        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);
    }

    public function test_manual_payment_is_refused_on_a_refunded_booking(): void
    {
        $booking = $this->book();
        $booking->update(['status' => BookingStatus::Refunded]);

        $this->expectException(ManualPaymentException::class);

        app(PaymentService::class)->recordManual($booking, 222000, 'cash', null, User::factory()->create()->id);
    }

    public function test_down_payment_is_refused_on_an_expired_booking_but_full_payment_revives_it(): void
    {
        $booking = $this->book();
        $booking->update(['status' => BookingStatus::Expired]);
        $user = User::factory()->create();
        $service = app(PaymentService::class);

        try {
            $service->recordManual($booking, 100000, 'cash', null, $user->id);
            $this->fail('Expected ManualPaymentException');
        } catch (ManualPaymentException) {
        }

        $service->recordManual($booking, 222000, 'cash', null, $user->id);

        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);
    }

    public function test_down_payment_holds_the_units_until_the_end_of_the_check_in_day(): void
    {
        config(['booking.manual_dp_hold_minutes' => 600]);
        $booking = $this->book();

        app(PaymentService::class)->recordManual($booking, 100000, 'cash', null, User::factory()->create()->id);

        $booking->refresh();
        $expected = now()->addMinutes(600)->max($booking->check_in->copy()->setTime(23, 59, 59));
        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertTrue($booking->hold_expires_at->equalTo($expected));
    }

    public function test_down_payment_never_shortens_a_longer_hold(): void
    {
        config(['booking.manual_dp_hold_minutes' => 5]);
        $booking = $this->book();
        $booking->update(['hold_expires_at' => now()->addHours(3)]);

        app(PaymentService::class)->recordManual($booking, 100000, 'cash', null, User::factory()->create()->id);

        $this->assertTrue($booking->fresh()->hold_expires_at->greaterThanOrEqualTo(now()->addHours(3)));
    }

    public function test_gateway_down_payment_charges_the_configured_percent_and_keeps_the_units(): void
    {
        $this->fakeSnap();
        app(SettingRepository::class)->put(SettingKey::DownPaymentPercent, '30');
        $booking = $this->book();
        $service = app(PaymentService::class);

        $intent = $service->initiate($booking, downPayment: true);
        $this->assertSame(66600, Payment::where('gateway_ref', $intent->reference)->value('amount'));

        $service->handleNotification($this->notification($intent->reference, 66600));

        $booking->refresh();
        $this->assertSame(66600, $booking->paid_amount);
        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertTrue($booking->hold_expires_at->greaterThanOrEqualTo($booking->check_in->copy()->setTime(23, 59, 59)));

        $rest = $service->initiate($booking);
        $service->handleNotification($this->notification($rest->reference, 155400));

        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);
    }

    public function test_down_payment_is_refused_when_the_owner_has_not_enabled_it(): void
    {
        $this->fakeSnap();

        $this->expectException(PaymentException::class);

        app(PaymentService::class)->initiate($this->book(), downPayment: true);
    }

    public function test_lapsed_hold_with_a_down_payment_goes_to_review_instead_of_expiring(): void
    {
        $booking = $this->book();
        app(PaymentService::class)->recordManual($booking, 100000, 'cash', null, User::factory()->create()->id);
        $booking->refresh()->update(['hold_expires_at' => now()->subMinute()]);

        (new ReleaseExpiredHolds)->handle();

        $booking->refresh();
        $this->assertSame(BookingStatus::NeedsReview, $booking->status);
        $this->assertSame(100000, $booking->paid_amount);
    }

    public function test_guest_cancelling_a_booking_with_a_down_payment_files_a_refund(): void
    {
        RefundPolicy::create(['min_days_before' => 0, 'percent' => 100]);
        $booking = $this->book();
        $user = User::factory()->create();
        app(PaymentService::class)->recordManual($booking, 100000, 'cash', null, $user->id);

        $result = app(RefundService::class)->cancelWithOutcome($booking->fresh(), 'Batal');

        $this->assertSame(CancellationOutcome::RefundRequested, $result->outcome);
        $this->assertSame(100000, $result->refund->amount);

        app(RefundService::class)->approve($result->refund, $user->id);
        $this->assertSame(BookingStatus::Refunded, $booking->fresh()->status);
    }

    public function test_admin_cannot_plain_cancel_a_booking_holding_a_down_payment(): void
    {
        $booking = $this->book();
        app(PaymentService::class)->recordManual($booking, 100000, 'cash', null, User::factory()->create()->id);

        $this->expectException(InvalidBookingTransitionException::class);

        app(BookingStatusTransition::class)->apply($booking->fresh(), BookingStatus::Cancelled);
    }

    public function test_old_link_paid_after_cancellation_does_not_revive_the_booking(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        $service = app(PaymentService::class);
        $intent = $service->initiate($booking);

        app(RefundService::class)->cancelWithOutcome($booking->fresh(), 'Batal');
        $this->assertSame(PaymentStatus::Expired, Payment::where('gateway_ref', $intent->reference)->value('status'));

        $service->handleNotification($this->notification($intent->reference, 222000));

        $booking->refresh();
        $payment = Payment::where('gateway_ref', $intent->reference)->firstOrFail();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame(0, $booking->paid_amount);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(PaymentReviewReason::BookingClosed, $payment->review_reason);
    }

    public function test_card_capture_denied_by_fraud_check_is_a_failure(): void
    {
        $this->assertSame(PaymentStatus::Failed, PaymentStatus::fromGatewayStatus('capture', 'deny'));
        $this->assertSame(PaymentStatus::Pending, PaymentStatus::fromGatewayStatus('capture', 'challenge'));
        $this->assertSame(PaymentStatus::Paid, PaymentStatus::fromGatewayStatus('capture', 'accept'));
    }

    public function test_check_in_is_refused_before_the_check_in_date(): void
    {
        $booking = $this->book();
        $booking->update(['status' => BookingStatus::Paid]);

        $this->expectException(InvalidBookingTransitionException::class);

        app(BookingStatusTransition::class)->apply($booking, BookingStatus::CheckedIn);
    }

    public function test_a_new_amount_closes_the_previous_payment_link(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        $service = app(PaymentService::class);

        $first = $service->initiate($booking);
        $this->addRoomBilledFood($booking);
        $second = $service->initiate($booking->fresh());

        $this->assertNotSame($first->reference, $second->reference);
        $this->assertSame(PaymentStatus::Expired, Payment::where('gateway_ref', $first->reference)->value('status'));
    }

    public function test_food_billed_to_a_booking_keeps_the_tax_rate_of_its_day(): void
    {
        $booking = $this->book();
        $category = MenuCategory::create(['name' => 'Makan', 'sort_order' => 1]);
        $item = MenuItem::create(['category_id' => $category->id, 'name' => 'Nasi', 'price' => 100000, 'is_available' => true]);

        app(OrderService::class)->createOrder(Order::SOURCE_QR, [['menu_item_id' => $item->id, 'qty' => 1]], $booking->id, billToBooking: true);
        $before = app(BookingBilling::class)->grandTotal($booking);

        app(SettingRepository::class)->put(SettingKey::TaxRate, '0.2');

        $this->assertSame($before, app(BookingBilling::class)->grandTotal($booking->fresh()));
    }
}
