<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Exceptions\UnitUnavailableException;
use App\Jobs\ReleaseExpiredHolds;
use App\Models\Booking;
use App\Models\BookingUnit;
use App\Models\BookingUnitNight;
use App\Models\Customer;
use App\Models\RefundPolicy;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BookingService;
use App\Services\Payment\PaymentService;
use App\Services\RefundService;
use App\Services\UnitNightLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitNightLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Customer $customer;

    private string $checkIn;

    private string $checkOut;

    protected function setUp(): void
    {
        parent::setUp();

        RefundPolicy::create(['min_days_before' => 0, 'percent' => 100]);

        $type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 4,
            'base_price_weekday' => 500000, 'base_price_weekend' => 700000,
        ]);
        $this->unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'D-01', 'status' => Unit::STATUS_ACTIVE]);
        $this->customer = Customer::create(['name' => 'Budi', 'email' => 'budi@example.com', 'phone' => '0812']);
        $this->checkIn = now()->addDays(10)->toDateString();
        $this->checkOut = now()->addDays(12)->toDateString();
    }

    private function book(?int $customerId = null): Booking
    {
        return app(BookingService::class)->createBooking(
            $customerId ?? $this->customer->id,
            $this->checkIn,
            $this->checkOut,
            2,
            [$this->unit->id],
        );
    }

    private function nightsFor(Booking $booking): array
    {
        return BookingUnitNight::whereIn('booking_unit_id', $booking->bookingUnits()->select('id'))
            ->orderBy('night')
            ->pluck('night')
            ->map(fn ($night) => $night->toDateString())
            ->all();
    }

    public function test_booking_claims_every_night_but_not_the_checkout_night(): void
    {
        $booking = $this->book();

        $this->assertSame([
            $this->checkIn,
            now()->addDays(11)->toDateString(),
        ], $this->nightsFor($booking));
        $this->assertNotContains($this->checkOut, $this->nightsFor($booking));
    }

    public function test_back_to_back_stay_can_start_on_the_checkout_day(): void
    {
        $this->book();

        $next = app(BookingService::class)->createBooking(
            $this->customer->id, $this->checkOut, now()->addDays(13)->toDateString(), 2, [$this->unit->id],
        );

        $this->assertSame([$this->checkOut], $this->nightsFor($next));
    }

    public function test_ledger_rejects_overlap_even_when_row_locking_and_availability_checks_are_skipped(): void
    {
        $first = $this->book();

        $rival = Booking::create([
            'code' => 'RIVAL1', 'customer_id' => $this->customer->id,
            'check_in' => $this->checkIn, 'check_out' => $this->checkOut, 'guests' => 2,
            'status' => BookingStatus::PendingPayment, 'hold_expires_at' => now()->addMinutes(30),
            'subtotal' => 1, 'tax' => 0, 'total' => 1,
        ]);
        $line = $rival->bookingUnits()->create([
            'unit_id' => $this->unit->id, 'check_in' => $this->checkIn, 'check_out' => $this->checkOut,
            'price_per_night' => 1, 'nights' => 2, 'subtotal' => 1,
        ]);

        try {
            app(UnitNightLedger::class)->claim(collect([$line]));
            $this->fail('Overlapping claim must be rejected.');
        } catch (UnitUnavailableException $e) {
            $this->assertStringContainsString('dipesan', $e->getMessage());
        }

        $this->assertCount(2, $this->nightsFor($first));
        $this->assertSame(0, BookingUnitNight::where('booking_unit_id', $line->id)->count());
    }

    public function test_claim_is_idempotent_for_the_same_line(): void
    {
        $booking = $this->book();
        $lines = $booking->bookingUnits()->get();

        app(UnitNightLedger::class)->claim($lines);
        app(UnitNightLedger::class)->claim($lines);

        $this->assertCount(2, $this->nightsFor($booking));
    }

    public function test_release_is_idempotent(): void
    {
        $booking = $this->book();
        $ledger = app(UnitNightLedger::class);

        $ledger->release([$booking->id]);
        $ledger->release([$booking->id]);

        $this->assertSame([], $this->nightsFor($booking));
    }

    public function test_expiry_job_releases_nights_of_lapsed_holds_only(): void
    {
        $lapsed = $this->book();
        $lapsed->update(['hold_expires_at' => now()->subMinute()]);

        $otherUnit = Unit::create(['unit_type_id' => $this->unit->unit_type_id, 'code' => 'D-02', 'status' => Unit::STATUS_ACTIVE]);
        $live = app(BookingService::class)->createBooking(
            $this->customer->id, $this->checkIn, $this->checkOut, 2, [$otherUnit->id],
        );

        (new ReleaseExpiredHolds)->handle();

        $this->assertSame(BookingStatus::Expired, $lapsed->fresh()->status);
        $this->assertSame([], $this->nightsFor($lapsed));
        $this->assertCount(2, $this->nightsFor($live));
    }

    public function test_cancelling_a_pending_booking_releases_its_nights(): void
    {
        $booking = $this->book();

        app(RefundService::class)->cancel($booking, 'Berubah rencana');

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame([], $this->nightsFor($booking));
    }

    public function test_approved_refund_releases_nights_of_a_paid_booking(): void
    {
        $owner = User::factory()->create();
        $booking = $this->book();
        $booking->update(['status' => BookingStatus::Paid, 'paid_amount' => $booking->total, 'hold_expires_at' => null]);

        $service = app(RefundService::class);
        $refund = $service->cancel($booking->fresh(), 'Sakit');

        $this->assertCount(2, $this->nightsFor($booking));

        $service->approve($refund, $owner->id);

        $this->assertSame(BookingStatus::Refunded, $booking->fresh()->status);
        $this->assertSame([], $this->nightsFor($booking));
    }

    public function test_late_payment_on_expired_booking_reclaims_nights_when_units_are_free(): void
    {
        $owner = User::factory()->create();
        $booking = $this->book();
        $booking->update(['hold_expires_at' => now()->subMinute()]);
        (new ReleaseExpiredHolds)->handle();

        app(PaymentService::class)->recordManual($booking->fresh(), $booking->total, 'cash', null, $owner->id);

        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);
        $this->assertCount(2, $this->nightsFor($booking));
    }

    public function test_late_payment_goes_to_review_when_another_booking_took_the_nights(): void
    {
        $owner = User::factory()->create();
        $lapsed = $this->book();
        $lapsed->update(['hold_expires_at' => now()->subMinute()]);
        (new ReleaseExpiredHolds)->handle();

        $winner = $this->book();

        app(PaymentService::class)->recordManual($lapsed->fresh(), $lapsed->total, 'cash', null, $owner->id);

        $this->assertSame(BookingStatus::NeedsReview, $lapsed->fresh()->status);
        $this->assertSame([], $this->nightsFor($lapsed));
        $this->assertCount(2, $this->nightsFor($winner));
    }

    public function test_every_booking_line_has_its_own_ledger_rows(): void
    {
        $this->book();

        $this->assertSame(2, BookingUnitNight::count());
        $this->assertSame(1, BookingUnit::count());
    }
}
