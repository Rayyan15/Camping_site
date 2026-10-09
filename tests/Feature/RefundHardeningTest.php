<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\RefundStatus;
use App\Exceptions\RefundException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Refund;
use App\Models\RefundPolicy;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundHardeningTest extends TestCase
{
    use RefreshDatabase;

    private RefundService $service;

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
        Unit::create(['unit_type_id' => $type->id, 'code' => 'D-01', 'status' => Unit::STATUS_ACTIVE]);
        $this->service = app(RefundService::class);
        $this->ownerId = User::factory()->create()->id;
    }

    private function paidBooking(int $daysAhead): Booking
    {
        $customer = Customer::create(['name' => 'Budi', 'email' => 'budi'.uniqid().'@example.com', 'phone' => '0812']);

        return Booking::create([
            'code' => 'RAY'.strtoupper(substr(uniqid(), -6)),
            'customer_id' => $customer->id,
            'check_in' => now()->addDays($daysAhead)->toDateString(),
            'check_out' => now()->addDays($daysAhead + 2)->toDateString(),
            'guests' => 2,
            'status' => BookingStatus::Paid,
            'subtotal' => 1000000, 'tax' => 0, 'total' => 1000000, 'paid_amount' => 1000000,
        ]);
    }

    public function test_checked_in_booking_cannot_be_refunded(): void
    {
        $booking = $this->paidBooking(10);
        $refund = $this->service->request($booking, 'Sakit');
        $booking->update(['status' => BookingStatus::CheckedIn]);

        try {
            $this->service->approve($refund, $this->ownerId);
            $this->fail('approve must throw for a checked-in booking');
        } catch (RefundException) {
            $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);
            $this->assertSame(RefundStatus::Requested, $refund->fresh()->status);
        }
    }

    public function test_zero_percent_refund_is_rejected(): void
    {
        try {
            $this->service->request($this->paidBooking(2), 'Mendadak');
            $this->fail('a zero-amount refund must not be created');
        } catch (RefundException) {
            $this->assertSame(0, Refund::count());
        }
    }

    public function test_approving_twice_is_rejected(): void
    {
        $refund = $this->service->request($this->paidBooking(10), 'Sakit');
        $this->service->approve($refund, $this->ownerId);

        $this->expectException(RefundException::class);
        $this->service->approve($refund, $this->ownerId);
    }

    public function test_mark_paid_cannot_exceed_paid_amount(): void
    {
        $booking = $this->paidBooking(10);
        $refund = $this->service->request($booking, 'Sakit');
        $this->service->approve($refund, $this->ownerId);
        $refund->update(['amount' => 1000001]);

        try {
            $this->service->markPaid($refund, $this->ownerId);
            $this->fail('markPaid must throw when amount exceeds paid_amount');
        } catch (RefundException) {
            $this->assertSame(0, $booking->payments()->count());
            $this->assertSame(RefundStatus::Approved, $refund->fresh()->status);
        }
    }

    public function test_calculate_does_not_mutate_callers_now(): void
    {
        $now = now()->setTime(15, 30);
        $original = $now->toDateTimeString();

        $this->service->calculate($this->paidBooking(10), $now);

        $this->assertSame($original, $now->toDateTimeString());
    }
}
