<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\RefundStatus;
use App\Exceptions\BookingReviewException;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingUnitNight;
use App\Models\Customer;
use App\Models\Refund;
use App\Models\RefundPolicy;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BookingReviewService;
use App\Services\BookingService;
use App\Services\RefundService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NeedsReviewResolutionTest extends TestCase
{
    use RefreshDatabase;

    private UnitType $type;

    private Customer $customer;

    private string $checkIn;

    private string $checkOut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        RefundPolicy::create(['min_days_before' => 7, 'percent' => 100]);
        RefundPolicy::create(['min_days_before' => 0, 'percent' => 0]);

        $this->type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 4,
            'base_price_weekday' => 500000, 'base_price_weekend' => 700000,
        ]);
        $this->customer = Customer::create(['name' => 'Budi', 'email' => 'budi@example.com', 'phone' => '0812']);
        $this->checkIn = now()->addDays(10)->toDateString();
        $this->checkOut = now()->addDays(12)->toDateString();
    }

    private function unit(string $code): Unit
    {
        return Unit::create(['unit_type_id' => $this->type->id, 'code' => $code, 'status' => Unit::STATUS_ACTIVE]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::create([
            'name' => $role, 'email' => $role.'@example.test', 'password' => 'secret-pass-123', 'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** The unit is held by a paid booking, as when a lapsed hold was sold to someone else. */
    private function takeUnit(Unit $unit): Booking
    {
        $taker = Customer::create(['name' => 'Siti', 'email' => 'siti@example.com', 'phone' => '0813']);
        $booking = app(BookingService::class)->createBooking($taker->id, $this->checkIn, $this->checkOut, 2, [$unit->id]);
        $booking->update(['status' => BookingStatus::Paid, 'paid_amount' => $booking->total]);

        return $booking;
    }

    private function reviewBooking(Unit $unit): Booking
    {
        $booking = Booking::create([
            'code' => 'REV'.strtoupper(substr(uniqid(), -6)),
            'customer_id' => $this->customer->id,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'guests' => 2,
            'status' => BookingStatus::NeedsReview,
            'subtotal' => 1000000, 'tax' => 0, 'total' => 1000000, 'paid_amount' => 1000000,
            'review_started_at' => now()->subHours(6),
        ]);
        $booking->bookingUnits()->create([
            'unit_id' => $unit->id, 'check_in' => $this->checkIn, 'check_out' => $this->checkOut,
            'price_per_night' => 500000, 'nights' => 2, 'subtotal' => 1000000,
        ]);

        return $booking;
    }

    public function test_move_puts_booking_on_a_free_unit_and_claims_the_ledger(): void
    {
        $taken = $this->unit('D-01');
        $free = $this->unit('D-02');
        $this->takeUnit($taken);
        $booking = $this->reviewBooking($taken);
        $owner = $this->userWithRole('owner');

        app(BookingReviewService::class)->moveToFreeUnit($booking, $owner);

        $booking->refresh();
        $this->assertSame(BookingStatus::Paid, $booking->status);
        $this->assertNull($booking->review_started_at);
        $this->assertSame($free->id, $booking->bookingUnits()->first()->unit_id);
        $this->assertSame(2, BookingUnitNight::whereIn('booking_unit_id', $booking->bookingUnits()->select('id'))
            ->where('unit_id', $free->id)->count());
        $this->assertTrue(ActivityLog::where('subject_id', $booking->id)->where('action', 'review_moved_unit')->exists());
    }

    public function test_move_is_rejected_when_no_unit_is_free(): void
    {
        $taken = $this->unit('D-01');
        $this->takeUnit($taken);
        $booking = $this->reviewBooking($taken);

        try {
            app(BookingReviewService::class)->moveToFreeUnit($booking, $this->userWithRole('owner'));
            $this->fail('Expected a rejection when no unit is free.');
        } catch (BookingReviewException $e) {
            $this->assertStringContainsString('refund penuh', $e->getMessage());
        }

        $this->assertSame(BookingStatus::NeedsReview, $booking->fresh()->status);
        $this->assertSame($taken->id, $booking->bookingUnits()->first()->unit_id);
    }

    public function test_move_twice_is_safe(): void
    {
        $taken = $this->unit('D-01');
        $this->unit('D-02');
        $this->takeUnit($taken);
        $booking = $this->reviewBooking($taken);
        $owner = $this->userWithRole('owner');
        $service = app(BookingReviewService::class);

        $service->moveToFreeUnit($booking, $owner);

        $this->expectException(BookingReviewException::class);
        $service->moveToFreeUnit($booking, $owner);
    }

    public function test_full_refund_ignores_the_policy_tiers(): void
    {
        $taken = $this->unit('D-01');
        $this->takeUnit($taken);
        $booking = $this->reviewBooking($taken);
        $owner = $this->userWithRole('owner');

        // The tier for a stay starting tomorrow is 0%, yet the guest still gets everything back.
        $booking->update(['check_in' => now()->addDay()->toDateString(), 'check_out' => now()->addDays(3)->toDateString()]);

        $refund = app(RefundService::class)->cancelForPropertyFault($booking, 'Unit terjual dua kali', $owner);

        $this->assertSame(1000000, $refund->amount);
        $this->assertSame(RefundStatus::Approved, $refund->status);
        $this->assertSame($owner->id, $refund->requested_by);
        $this->assertSame('Unit terjual dua kali', $refund->reason);
        $this->assertSame(BookingStatus::Refunded, $booking->fresh()->status);
        $this->assertTrue(ActivityLog::where('subject_id', $booking->id)->where('action', 'review_cancelled_full_refund')->exists());
    }

    public function test_full_refund_is_only_for_users_who_can_approve_refunds(): void
    {
        $taken = $this->unit('D-01');
        $this->takeUnit($taken);
        $booking = $this->reviewBooking($taken);

        foreach (['operator_fo', 'operator_kasir'] as $role) {
            try {
                app(RefundService::class)->cancelForPropertyFault($booking, 'Alasan', $this->userWithRole($role));
                $this->fail("{$role} must not refund in full.");
            } catch (BookingReviewException) {
                continue;
            }
        }

        $this->assertSame(BookingStatus::NeedsReview, $booking->fresh()->status);
        $this->assertSame(0, Refund::count());
    }

    public function test_full_refund_twice_is_safe(): void
    {
        $taken = $this->unit('D-01');
        $this->takeUnit($taken);
        $booking = $this->reviewBooking($taken);
        $owner = $this->userWithRole('owner');
        $service = app(RefundService::class);

        $service->cancelForPropertyFault($booking, 'Unit terjual dua kali', $owner);

        try {
            $service->cancelForPropertyFault($booking, 'Unit terjual dua kali', $owner);
            $this->fail('A second submit must be rejected.');
        } catch (BookingReviewException) {
            $this->assertSame(1, Refund::count());
        }
    }

    public function test_owner_resolves_review_from_the_booking_table(): void
    {
        $taken = $this->unit('D-01');
        $free = $this->unit('D-02');
        $this->takeUnit($taken);
        $booking = $this->reviewBooking($taken);
        $this->actingAs($this->userWithRole('owner'));

        Livewire::test(ListBookings::class)
            ->callTableAction('resolve_review', $booking, ['outcome' => 'move']);

        $this->assertSame($free->id, $booking->bookingUnits()->first()->unit_id);
        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);
    }

    public function test_refund_outcome_requires_a_written_reason(): void
    {
        $taken = $this->unit('D-01');
        $this->takeUnit($taken);
        $booking = $this->reviewBooking($taken);
        $this->actingAs($this->userWithRole('owner'));

        Livewire::test(ListBookings::class)
            ->callTableAction('resolve_review', $booking, ['outcome' => 'refund', 'reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        $this->assertSame(BookingStatus::NeedsReview, $booking->fresh()->status);
    }

    public function test_front_office_and_cashier_do_not_see_the_action(): void
    {
        $taken = $this->unit('D-01');
        $this->takeUnit($taken);
        $booking = $this->reviewBooking($taken);

        foreach (['operator_fo', 'operator_kasir'] as $role) {
            $this->actingAs($this->userWithRole($role));

            Livewire::test(ListBookings::class)->assertTableActionHidden('resolve_review', $booking);
        }
    }

    public function test_action_is_hidden_for_bookings_not_in_review(): void
    {
        $unit = $this->unit('D-01');
        $paid = $this->takeUnit($unit);
        $this->actingAs($this->userWithRole('owner'));

        Livewire::test(ListBookings::class)->assertTableActionHidden('resolve_review', $paid);
    }

    public function test_review_tab_lists_only_review_bookings_and_navigation_badge_counts_them(): void
    {
        $taken = $this->unit('D-01');
        $paid = $this->takeUnit($taken);
        $review = $this->reviewBooking($taken);
        $this->actingAs($this->userWithRole('owner'));

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'needs_review')
            ->assertCanSeeTableRecords([$review])
            ->assertCanNotSeeTableRecords([$paid]);

        $this->assertSame('1', BookingResource::getNavigationBadge());
    }

    public function test_navigation_badge_is_empty_without_review_bookings(): void
    {
        $this->assertNull(BookingResource::getNavigationBadge());
    }
}
