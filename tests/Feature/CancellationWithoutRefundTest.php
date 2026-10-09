<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Exceptions\RefundException;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingUnitNight;
use App\Models\Customer;
use App\Models\Refund;
use App\Models\RefundPolicy;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BookingService;
use App\Services\CancellationOutcome;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancellationWithoutRefundTest extends TestCase
{
    use RefreshDatabase;

    private RefundService $service;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([[7, 100], [3, 50], [1, 0], [0, 0]] as [$days, $percent]) {
            RefundPolicy::create(['min_days_before' => $days, 'percent' => $percent]);
        }

        $type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 4,
            'base_price_weekday' => 500000, 'base_price_weekend' => 700000,
        ]);
        $this->unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'D-01', 'status' => Unit::STATUS_ACTIVE]);
        $this->service = app(RefundService::class);
    }

    private function paidBooking(int $daysAhead): Booking
    {
        $customer = Customer::create(['name' => 'Budi', 'email' => 'budi'.uniqid().'@example.com', 'phone' => '0812']);
        $booking = app(BookingService::class)->createBooking(
            $customer->id,
            now()->addDays($daysAhead)->toDateString(),
            now()->addDays($daysAhead + 2)->toDateString(),
            2,
            [$this->unit->id],
        );
        $booking->update(['status' => BookingStatus::Paid, 'paid_amount' => $booking->total, 'hold_expires_at' => null]);

        return $booking;
    }

    public function test_zero_percent_tier_cancels_without_creating_a_refund(): void
    {
        $booking = $this->paidBooking(2);
        $user = User::factory()->create();

        $result = $this->service->cancelWithOutcome($booking, 'Berubah rencana', $user->id);

        $this->assertSame(CancellationOutcome::CancelledWithoutRefund, $result->outcome);
        $this->assertNull($result->refund);
        $this->assertSame(0, Refund::count());

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertNull($booking->hold_expires_at);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame('Berubah rencana', $booking->cancellation_note);
    }

    public function test_cancellation_without_refund_is_logged_with_reason_and_actor(): void
    {
        $booking = $this->paidBooking(2);
        $user = User::factory()->create();

        $this->service->cancelWithOutcome($booking, 'Berubah rencana', $user->id);

        $log = ActivityLog::where('subject_id', $booking->id)->where('action', 'cancelled_without_refund')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Berubah rencana', $log->changes['new']['reason']);
    }

    public function test_cancellation_without_refund_releases_the_unit(): void
    {
        $booking = $this->paidBooking(2);

        $this->service->cancelWithOutcome($booking, 'Berubah rencana');

        $this->assertSame(0, BookingUnitNight::count());
        $this->assertTrue($this->unit->isFreeBetween(
            now()->addDays(2)->toDateString(),
            now()->addDays(4)->toDateString(),
        ));
    }

    public function test_double_submit_is_safe(): void
    {
        $booking = $this->paidBooking(2);
        $this->service->cancelWithOutcome($booking, 'Berubah rencana');

        $this->expectException(RefundException::class);
        $this->service->cancelWithOutcome($booking, 'Berubah rencana');
    }

    public function test_cancel_keeps_returning_null_for_the_legacy_caller(): void
    {
        $this->assertNull($this->service->cancel($this->paidBooking(2), 'Berubah rencana'));
    }

    public function test_refund_tier_still_files_a_request_and_keeps_the_unit_held(): void
    {
        $booking = $this->paidBooking(10);

        $result = $this->service->cancelWithOutcome($booking, 'Sakit');

        $this->assertSame(CancellationOutcome::RefundRequested, $result->outcome);
        $this->assertSame(1, Refund::count());
        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);
        $this->assertGreaterThan(0, BookingUnitNight::count());
    }

    public function test_a_zero_value_refund_can_still_not_be_requested_directly(): void
    {
        $this->expectException(RefundException::class);
        $this->service->request($this->paidBooking(2), 'Berubah rencana');
    }

    public function test_pending_booking_reports_a_plain_cancellation(): void
    {
        $booking = $this->paidBooking(10);
        $booking->update(['status' => BookingStatus::PendingPayment, 'paid_amount' => 0]);

        $result = $this->service->cancelWithOutcome($booking, 'Berubah rencana');

        $this->assertSame(CancellationOutcome::Cancelled, $result->outcome);
    }
}
