<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Exceptions\RefundException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Refund;
use App\Models\RefundPolicy;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BookingService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundCancellationTest extends TestCase
{
    use RefreshDatabase;

    private RefundService $service;

    private Unit $unit;

    private int $ownerId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([[7, 100], [3, 50], [0, 0]] as [$days, $percent]) {
            RefundPolicy::create(['min_days_before' => $days, 'percent' => $percent]);
        }

        $type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 4,
            'base_price_weekday' => 500000, 'base_price_weekend' => 700000,
        ]);
        $this->unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'D-01', 'status' => Unit::STATUS_ACTIVE]);
        $this->service = app(RefundService::class);
        $this->ownerId = User::factory()->create()->id;
    }

    private function booking(int $daysAhead, BookingStatus $status = BookingStatus::Paid, int $paid = 1000001): Booking
    {
        $customer = Customer::create(['name' => 'Budi', 'email' => 'budi'.uniqid().'@example.com', 'phone' => '0812']);
        $booking = Booking::create([
            'code' => 'RAY'.strtoupper(substr(uniqid(), -6)),
            'customer_id' => $customer->id,
            'check_in' => now()->addDays($daysAhead)->toDateString(),
            'check_out' => now()->addDays($daysAhead + 2)->toDateString(),
            'guests' => 2,
            'status' => $status,
            'hold_expires_at' => $status === BookingStatus::PendingPayment ? now()->addMinutes(30) : null,
            'subtotal' => $paid, 'tax' => 0, 'total' => $paid, 'paid_amount' => $status === BookingStatus::Paid ? $paid : 0,
        ]);
        $booking->bookingUnits()->create([
            'unit_id' => $this->unit->id, 'check_in' => $booking->check_in, 'check_out' => $booking->check_out,
            'price_per_night' => 500000, 'nights' => 2, 'subtotal' => $paid,
        ]);

        return $booking;
    }

    public function test_calculation_follows_policy_tiers_and_rounds_down(): void
    {
        $this->assertSame(1000001, $this->service->calculate($this->booking(10))->amount);
        $this->assertSame(500000, $this->service->calculate($this->booking(4))->amount);
        $this->assertSame(50, $this->service->calculate($this->booking(3))->percent);
        $this->assertSame(0, $this->service->calculate($this->booking(2))->amount);
        $this->assertSame(7, $this->service->calculate($this->booking(7))->daysBefore);
    }

    public function test_cancelling_pending_booking_frees_the_hold_without_refund(): void
    {
        $booking = $this->booking(10, BookingStatus::PendingPayment);
        $this->assertTrue(Booking::occupying()->whereKey($booking->id)->exists());

        $this->assertNull($this->service->cancel($booking, 'Berubah rencana'));

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertFalse(Booking::occupying()->whereKey($booking->id)->exists());
        $this->assertSame(0, Refund::count());
    }

    public function test_cancelling_paid_booking_creates_one_requested_refund_and_keeps_units_held(): void
    {
        $booking = $this->booking(10);

        $refund = $this->service->cancel($booking, 'Sakit');

        $this->assertSame(RefundStatus::Requested, $refund->status);
        $this->assertSame(1000001, $refund->amount);
        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);
        $this->assertTrue(Booking::occupying()->whereKey($booking->id)->exists());
    }

    public function test_double_submit_is_blocked(): void
    {
        $booking = $this->booking(10);
        $this->service->cancel($booking, 'Sakit');

        $this->expectException(RefundException::class);
        try {
            $this->service->cancel($booking, 'Sakit lagi');
        } finally {
            $this->assertSame(1, Refund::count());
        }
    }

    public function test_booking_on_or_after_check_in_day_cannot_be_cancelled(): void
    {
        $this->expectException(RefundException::class);

        $this->service->cancel($this->booking(0), 'Terlambat');
    }

    public function test_approve_refunds_booking_and_releases_unit(): void
    {
        $booking = $this->booking(10);
        $refund = $this->service->cancel($booking, 'Sakit');
        $range = [$booking->check_in->toDateString(), $booking->check_out->toDateString()];
        $bookingService = app(BookingService::class);
        $this->assertCount(0, $bookingService->checkAvailability($this->unit->unit_type_id, ...$range));

        $this->service->approve($refund, $this->ownerId);

        $this->assertSame(BookingStatus::Refunded, $booking->fresh()->status);
        $this->assertSame(RefundStatus::Approved, $refund->fresh()->status);
        $this->assertCount(1, $bookingService->checkAvailability($this->unit->unit_type_id, ...$range));

        $this->service->markPaid($refund, $this->ownerId);
        $this->assertSame(RefundStatus::Paid, $refund->fresh()->status);

        $payout = $booking->payments()->sole();
        $this->assertSame(PaymentDirection::Out, $payout->direction);
        $this->assertSame(PaymentMethod::Manual, $payout->method);
        $this->assertSame(PaymentStatus::Paid, $payout->status);
        $this->assertSame($refund->amount, $payout->amount);
        $this->assertSame($this->ownerId, $payout->recorded_by);
        $this->assertNull($payout->gateway_ref);
        $this->assertTrue($booking->refunds->contains($refund));
    }

    public function test_invalid_transitions_throw(): void
    {
        $refund = $this->service->cancel($this->booking(10), 'Sakit');

        try {
            $this->service->markPaid($refund, $this->ownerId);
            $this->fail('markPaid on a requested refund must throw');
        } catch (RefundException) {
            $this->assertSame(RefundStatus::Requested, $refund->fresh()->status);
        }

        $this->service->reject($refund, $this->ownerId, 'Di luar kebijakan');
        $this->assertSame(RefundStatus::Rejected, $refund->fresh()->status);

        $this->expectException(RefundException::class);
        $this->service->approve($refund, $this->ownerId);
    }

    public function test_status_page_renders_and_unknown_code_is_404(): void
    {
        $booking = $this->booking(10);

        $this->get(route('booking.status', $booking->access_token))
            ->assertOk()
            ->assertSee($booking->code)
            ->assertSee('Lunas');

        $this->get(route('booking.status', 'TIDAKADA'))->assertNotFound();
        $this->post(route('booking.cancel', 'TIDAKADA'), ['reason' => 'Sakit sekali'])->assertNotFound();
    }

    public function test_cancel_endpoint_requires_reason_and_creates_request(): void
    {
        $booking = $this->booking(10);

        $this->post(route('booking.cancel', $booking->access_token), ['reason' => ''])->assertSessionHasErrors('reason');

        $this->post(route('booking.cancel', $booking->access_token), ['reason' => 'Sakit mendadak'])
            ->assertRedirect(route('booking.status', $booking->access_token));

        $this->assertSame(1, Refund::count());
    }
}
