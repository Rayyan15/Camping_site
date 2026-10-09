<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentSignatureException;
use App\Exceptions\PaymentException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BookingService;
use App\Services\Payment\MidtransGateway;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentTest extends TestCase
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

    private function book(): Booking
    {
        // Two weekday nights: subtotal 200000, tax 22000, total 222000.
        return app(BookingService::class)->createBooking(
            $this->customer->id, '2027-01-04', '2027-01-06', 2, [$this->unit->id],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function notification(string $orderId, int $amount, string $status = 'settlement', ?string $signature = null): array
    {
        $statusCode = '200';
        $gross = $amount.'.00';

        return [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $gross,
            'transaction_status' => $status,
            'signature_key' => $signature ?? hash('sha512', $orderId.$statusCode.$gross.self::SERVER_KEY),
        ];
    }

    private function fakeSnap(): void
    {
        Http::fake(['*midtrans.com/*' => Http::response(['token' => 't', 'redirect_url' => 'https://pay.test/abc'], 201)]);
    }

    public function test_initiate_creates_pending_payment_and_sends_snap_request(): void
    {
        $this->fakeSnap();
        $booking = $this->book();

        $intent = app(PaymentService::class)->initiate($booking);

        $this->assertSame('https://pay.test/abc', $intent->redirectUrl);
        $this->assertSame($booking->code.'-1', $intent->reference);

        $payment = Payment::where('gateway_ref', $intent->reference)->firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(222000, $payment->amount);
        $this->assertTrue($payment->payable->is($booking));

        Http::assertSent(function (Request $request) use ($intent) {
            return $request->hasHeader('Authorization', 'Basic '.base64_encode(self::SERVER_KEY.':'))
                && str_contains($request->url(), 'sandbox')
                && $request['transaction_details']['order_id'] === $intent->reference
                && $request['transaction_details']['gross_amount'] === 222000
                && $request['customer_details']['first_name'] === 'Tamu';
        });
    }

    public function test_attempt_after_the_first_link_lapsed_gets_a_new_reference(): void
    {
        $this->fakeSnap();
        $booking = $this->book();

        app(PaymentService::class)->initiate($booking);
        $this->travel(config('booking.hold_minutes') + 1)->minutes();
        $second = app(PaymentService::class)->initiate($booking);

        $this->assertSame($booking->code.'-2', $second->reference);
    }

    public function test_gateway_failure_marks_payment_failed(): void
    {
        Http::fake(['*' => Http::response(['error' => 'x'], 500)]);
        $booking = $this->book();

        try {
            app(PaymentService::class)->initiate($booking);
            $this->fail('Expected PaymentException');
        } catch (PaymentException) {
            $this->assertSame(PaymentStatus::Failed, Payment::firstOrFail()->status);
        }
    }

    public function test_fully_paid_booking_cannot_start_a_payment(): void
    {
        $booking = $this->book();
        $booking->update(['paid_amount' => $booking->total]);

        $this->expectException(PaymentException::class);
        app(PaymentService::class)->initiate($booking);
    }

    public function test_valid_settlement_marks_payment_and_booking_paid(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        $intent = app(PaymentService::class)->initiate($booking);

        $this->postJson(route('webhook.payment'), $this->notification($intent->reference, 222000))
            ->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::Paid, $booking->status);
        $this->assertSame(222000, $booking->paid_amount);

        $payment = Payment::firstOrFail();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_bad_signature_is_rejected_and_changes_nothing(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        $intent = app(PaymentService::class)->initiate($booking);

        $this->postJson(route('webhook.payment'), $this->notification($intent->reference, 222000, 'settlement', 'forged'))
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, Payment::firstOrFail()->status);
        $this->assertSame(BookingStatus::PendingPayment, $booking->refresh()->status);
    }

    public function test_unknown_order_id_returns_not_found(): void
    {
        $this->postJson(route('webhook.payment'), $this->notification('NOPE-1', 1000))
            ->assertNotFound();
    }

    public function test_same_notification_twice_is_idempotent(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        $intent = app(PaymentService::class)->initiate($booking);
        $payload = $this->notification($intent->reference, 222000);

        $this->postJson(route('webhook.payment'), $payload)->assertOk();
        $this->postJson(route('webhook.payment'), $payload)->assertOk();

        $this->assertSame(222000, $booking->refresh()->paid_amount);
        $this->assertSame(1, Payment::count());
    }

    public function test_expire_notification_marks_payment_expired(): void
    {
        $this->fakeSnap();
        $intent = app(PaymentService::class)->initiate($this->book());

        $this->postJson(route('webhook.payment'), $this->notification($intent->reference, 222000, 'expire'))
            ->assertOk();

        $this->assertSame(PaymentStatus::Expired, Payment::firstOrFail()->status);
    }

    public function test_late_webhook_with_unit_still_free_revives_the_booking(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        $intent = app(PaymentService::class)->initiate($booking);
        $booking->update(['status' => BookingStatus::Expired]);

        $this->postJson(route('webhook.payment'), $this->notification($intent->reference, 222000))->assertOk();

        $this->assertSame(BookingStatus::Paid, $booking->refresh()->status);
    }

    public function test_late_webhook_after_unit_was_rebooked_needs_review(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        $intent = app(PaymentService::class)->initiate($booking);

        $this->travel(30)->minutes();
        $booking->update(['status' => BookingStatus::Expired]);
        $other = Customer::create(['name' => 'Lain', 'phone' => '0822']);
        app(BookingService::class)->createBooking($other->id, '2027-01-04', '2027-01-06', 2, [$this->unit->id]);

        $this->postJson(route('webhook.payment'), $this->notification($intent->reference, 222000))->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::NeedsReview, $booking->status);
        $this->assertSame(222000, $booking->paid_amount);
    }

    public function test_partial_then_full_payment(): void
    {
        $booking = $this->book();
        $user = User::factory()->create();
        $service = app(PaymentService::class);

        $service->recordManual($booking, 100000, 'cash', null, $user->id);
        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertSame(100000, $booking->paid_amount);

        $service->recordManual($booking, 122000, 'transfer', 'proofs/a.jpg', $user->id);
        $this->assertSame(BookingStatus::Paid, $booking->status);
        $this->assertSame(222000, $booking->paid_amount);
    }

    public function test_record_manual_stores_payment_details(): void
    {
        $booking = $this->book();
        $user = User::factory()->create();

        $payment = app(PaymentService::class)->recordManual($booking, 222000, 'transfer', 'proofs/a.jpg', $user->id);

        $this->assertSame(PaymentMethod::Transfer, $payment->method);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('proofs/a.jpg', $payment->proof_path);
        $this->assertSame($user->id, $payment->recorded_by);
        $this->assertNull($payment->gateway_ref);
    }

    public function test_midtrans_gateway_rejects_missing_signature(): void
    {
        $this->assertFalse((new MidtransGateway)->isAuthentic(['order_id' => 'X']));
    }

    public function test_empty_server_key_is_rejected_instead_of_trusted(): void
    {
        config(['services.midtrans.server_key' => '']);

        $this->expectException(PaymentException::class);
        (new MidtransGateway)->isAuthentic(['order_id' => 'X', 'signature_key' => 'abc']);
    }

    public function test_signature_computed_with_empty_key_never_authenticates(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        $intent = app(PaymentService::class)->initiate($booking);
        $forged = hash('sha512', $intent->reference.'200222000.00');
        config(['services.midtrans.server_key' => '']);

        $this->postJson(route('webhook.payment'), $this->notification($intent->reference, 222000, 'settlement', $forged))
            ->assertStatus(500);

        $this->assertSame(PaymentStatus::Pending, Payment::firstOrFail()->status);
        $this->assertSame(BookingStatus::PendingPayment, $booking->refresh()->status);
    }

    public function test_amount_mismatch_is_rejected_and_changes_nothing(): void
    {
        $this->fakeSnap();
        $booking = $this->book();
        $intent = app(PaymentService::class)->initiate($booking);

        $this->postJson(route('webhook.payment'), $this->notification($intent->reference, 1000))
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, Payment::firstOrFail()->status);
        $booking->refresh();
        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertSame(0, $booking->paid_amount);
    }

    public function test_amount_mismatch_throws_a_signature_family_exception(): void
    {
        $this->fakeSnap();
        $intent = app(PaymentService::class)->initiate($this->book());

        $this->expectException(InvalidPaymentSignatureException::class);
        app(PaymentService::class)->handleNotification($this->notification($intent->reference, 221999));
    }
}
