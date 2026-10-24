<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Events\BookingRescheduled;
use App\Exceptions\BookingRescheduleException;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Mail\RescheduleConfirmed;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingUnitNight;
use App\Models\Customer;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BookingRescheduleService;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class BookingRescheduleTest extends TestCase
{
    use RefreshDatabase;

    private UnitType $type;

    private Customer $customer;

    /** A Monday far enough ahead, so Monday to Wednesday nights are all weekday priced. */
    private CarbonImmutable $monday;

    protected function setUp(): void
    {
        parent::setUp();

        // The guest email view is owned by the frontend task; mail is asserted, not rendered.
        Mail::fake();

        $this->type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 4,
            'base_price_weekday' => 500000, 'base_price_weekend' => 700000,
        ]);
        $this->customer = Customer::create(['name' => 'Budi', 'email' => 'budi@example.com', 'phone' => '0812']);
        $this->monday = CarbonImmutable::today()->addDays(20)->next('Monday');
    }

    private function unit(string $code): Unit
    {
        return Unit::create(['unit_type_id' => $this->type->id, 'code' => $code, 'status' => Unit::STATUS_ACTIVE]);
    }

    private function paidBooking(Unit $unit, ?CarbonImmutable $checkIn = null, int $nights = 2): Booking
    {
        $checkIn ??= $this->monday;
        $booking = app(BookingService::class)->createBooking(
            $this->customer->id,
            $checkIn->toDateString(),
            $checkIn->addDays($nights)->toDateString(),
            2,
            [$unit->id],
        );
        $booking->update(['status' => BookingStatus::Paid, 'paid_amount' => $booking->total]);

        return $booking->refresh();
    }

    private function service(): BookingRescheduleService
    {
        return app(BookingRescheduleService::class);
    }

    private function nextWeekStay(int $nights = 2): array
    {
        $in = $this->monday->addWeek();

        return [$in->toDateString(), $in->addDays($nights)->toDateString()];
    }

    public function test_guest_moves_a_paid_booking_and_the_price_is_recomputed_by_the_server(): void
    {
        Event::fake([BookingRescheduled::class]);
        $booking = $this->paidBooking($this->unit('DOM-01'));
        $this->assertSame(1000000, $booking->total);
        [$newIn, $newOut] = $this->nextWeekStay(1);

        $this->post(route('booking.reschedule', $booking->access_token), [
            'check_in' => $newIn,
            'check_out' => $newOut,
            'total' => 1,
        ])->assertRedirect(route('booking.status', $booking->access_token))->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame($newIn, $booking->check_in->toDateString());
        $this->assertSame(500000, $booking->total);
        $this->assertSame(1, $booking->reschedule_count);
        $this->assertSame(500000, (int) $booking->bookingUnits()->sum('subtotal'));
        $this->assertSame(500000, $booking->reschedule_credit);
        Event::assertDispatched(BookingRescheduled::class);
    }

    public function test_guest_is_refused_when_the_new_dates_cost_more(): void
    {
        $booking = $this->paidBooking($this->unit('DOM-01'));
        $friday = $this->monday->next('Friday');

        $this->post(route('booking.reschedule', $booking->access_token), [
            'check_in' => $friday->toDateString(),
            'check_out' => $friday->addDays(2)->toDateString(),
        ])->assertSessionHas('error');

        $booking->refresh();
        $this->assertSame($this->monday->toDateString(), $booking->check_in->toDateString());
        $this->assertSame(0, $booking->reschedule_count);
        $this->assertSame(1000000, $booking->total);
    }

    public function test_staff_can_accept_a_higher_price_and_the_balance_stays_open(): void
    {
        $booking = $this->paidBooking($this->unit('DOM-01'));
        $friday = $this->monday->next('Friday');

        $result = $this->service()->reschedule($booking, $friday->toDateString(), $friday->addDays(2)->toDateString(), true);

        $this->assertSame(400000, $result->priceDifference);
        $booking->refresh();
        $this->assertSame(1400000, $booking->total);
        $this->assertSame(1000000, $booking->paid_amount);
        $this->assertSame(BookingStatus::Paid, $booking->status);
    }

    public function test_cheaper_dates_record_the_overpayment_for_manual_refund(): void
    {
        $friday = $this->monday->next('Friday');
        $booking = $this->paidBooking($this->unit('DOM-01'), $friday);
        $this->assertSame(1400000, $booking->total);
        [$newIn, $newOut] = $this->nextWeekStay();

        $result = $this->service()->reschedule($booking, $newIn, $newOut);

        $this->assertSame(-400000, $result->priceDifference);
        $this->assertSame(400000, $booking->refresh()->reschedule_credit);
    }

    public function test_second_reschedule_is_refused(): void
    {
        $booking = $this->paidBooking($this->unit('DOM-01'));
        [$newIn, $newOut] = $this->nextWeekStay();
        $this->service()->reschedule($booking, $newIn, $newOut);

        $later = $this->monday->addWeeks(2);

        $this->expectException(BookingRescheduleException::class);
        $this->expectExceptionMessage('satu kali');

        $this->service()->reschedule($booking->refresh(), $later->toDateString(), $later->addDays(2)->toDateString());
    }

    public function test_less_than_seven_days_before_check_in_is_refused(): void
    {
        $booking = $this->paidBooking($this->unit('DOM-01'), CarbonImmutable::today()->addDays(6));
        [$newIn, $newOut] = $this->nextWeekStay();

        $this->assertNotNull($this->service()->blockedReason($booking));
        $this->expectException(BookingRescheduleException::class);

        $this->service()->reschedule($booking, $newIn, $newOut);
    }

    public function test_exactly_seven_days_before_check_in_is_still_allowed(): void
    {
        $booking = $this->paidBooking($this->unit('DOM-01'), CarbonImmutable::today()->addDays(7));

        $this->assertNull($this->service()->blockedReason($booking));
    }

    public function test_full_dates_are_refused_and_nothing_changes(): void
    {
        $mine = $this->paidBooking($this->unit('DOM-01'));
        $first = $mine->bookingUnits()->first()->unit;
        $other = $this->paidBooking($this->unit('DOM-02'), $this->monday->addWeek());
        $third = $this->paidBooking($first, $this->monday->addWeek());
        [$newIn, $newOut] = $this->nextWeekStay();

        try {
            $this->service()->reschedule($mine, $newIn, $newOut);
            $this->fail('Expected the full dates to be refused.');
        } catch (BookingRescheduleException $e) {
            $this->assertStringContainsString('tidak tersedia', $e->getMessage());
        }

        $this->assertSame(0, $mine->refresh()->reschedule_count);
        $this->assertSame($this->monday->toDateString(), $mine->check_in->toDateString());
        $this->assertSame(2, BookingUnitNight::whereIn('booking_unit_id', $mine->bookingUnits()->select('id'))->count());
        $this->assertSame(2, BookingUnitNight::whereIn('booking_unit_id', $other->bookingUnits()->select('id'))->count());
        $this->assertSame(2, BookingUnitNight::whereIn('booking_unit_id', $third->bookingUnits()->select('id'))->count());
    }

    public function test_booking_moves_to_another_unit_of_the_same_type_when_its_own_is_taken(): void
    {
        $first = $this->unit('DOM-01');
        $second = $this->unit('DOM-02');
        $mine = $this->paidBooking($first);
        $this->paidBooking($first, $this->monday->addWeek());
        [$newIn, $newOut] = $this->nextWeekStay();

        $this->service()->reschedule($mine, $newIn, $newOut);

        $this->assertSame($second->id, $mine->bookingUnits()->first()->unit_id);
        $this->assertSame(2, BookingUnitNight::where('unit_id', $second->id)->count());
    }

    public function test_old_nights_are_released_and_new_ones_claimed_without_overlap(): void
    {
        $unit = $this->unit('DOM-01');
        $booking = $this->paidBooking($unit);
        [$newIn, $newOut] = $this->nextWeekStay();

        $this->service()->reschedule($booking, $newIn, $newOut);

        $nights = BookingUnitNight::where('unit_id', $unit->id)->orderBy('night')->get()
            ->map(fn ($row) => substr((string) $row->getRawOriginal('night'), 0, 10))->all();
        $this->assertSame([$newIn, CarbonImmutable::parse($newIn)->addDay()->toDateString()], $nights);

        $this->paidBooking($unit);
        $this->assertSame(4, BookingUnitNight::where('unit_id', $unit->id)->count());
    }

    public function test_pending_booking_cannot_be_rescheduled(): void
    {
        $booking = $this->paidBooking($this->unit('DOM-01'));
        $booking->update(['status' => BookingStatus::PendingPayment]);
        [$newIn, $newOut] = $this->nextWeekStay();

        $this->expectException(BookingRescheduleException::class);

        $this->service()->reschedule($booking, $newIn, $newOut);
    }

    public function test_the_move_is_written_to_the_activity_log(): void
    {
        $booking = $this->paidBooking($this->unit('DOM-01'));
        [$newIn, $newOut] = $this->nextWeekStay();

        $this->service()->reschedule($booking, $newIn, $newOut);

        $this->assertTrue(ActivityLog::where('action', 'rescheduled')->where('subject_id', $booking->id)->exists());
    }

    public function test_status_page_shows_the_form_then_the_used_message(): void
    {
        $booking = $this->paidBooking($this->unit('DOM-01'));
        [$newIn, $newOut] = $this->nextWeekStay();

        $this->get(route('booking.status', $booking->access_token))
            ->assertOk()
            ->assertSee('Pilih tanggal baru');

        $this->service()->reschedule($booking, $newIn, $newOut);

        $this->get(route('booking.status', $booking->access_token))
            ->assertOk()
            ->assertDontSee('Pilih tanggal baru')
            ->assertSee('sudah pernah diubah');
    }

    public function test_request_validates_dates(): void
    {
        $booking = $this->paidBooking($this->unit('DOM-01'));

        $this->post(route('booking.reschedule', $booking->access_token), [
            'check_in' => $this->monday->addWeek()->toDateString(),
            'check_out' => $this->monday->toDateString(),
        ])->assertSessionHasErrors('check_out');
    }

    public function test_front_office_can_reschedule_from_the_admin_panel(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::create(['name' => 'FO', 'email' => 'fo@example.test', 'password' => 'secret-pass-123', 'is_active' => true]);
        $user->assignRole('operator_fo');
        $booking = $this->paidBooking($this->unit('DOM-01'));
        [$newIn, $newOut] = $this->nextWeekStay();

        Livewire::actingAs($user)->test(ListBookings::class)
            ->callTableAction('reschedule', $booking, ['check_in' => $newIn, 'check_out' => $newOut])
            ->assertHasNoTableActionErrors();

        $this->assertSame($newIn, $booking->refresh()->check_in->toDateString());
        $this->assertSame(1, $booking->reschedule_count);
    }

    public function test_accepted_price_increase_emails_the_guest_the_remaining_balance(): void
    {
        $booking = $this->paidBooking($this->unit('DOM-01'));
        $friday = $this->monday->next('Friday');

        $this->service()->reschedule($booking, $friday->toDateString(), $friday->addDays(2)->toDateString(), true);

        Mail::assertQueued(RescheduleConfirmed::class, fn (RescheduleConfirmed $mail): bool => $mail->hasTo('budi@example.com')
            && $mail->outstanding === 400000
            && $mail->credit === 0);
    }

    public function test_status_page_receives_the_remaining_balance_after_a_price_increase(): void
    {
        $booking = $this->paidBooking($this->unit('DOM-01'));
        $friday = $this->monday->next('Friday');
        $this->service()->reschedule($booking, $friday->toDateString(), $friday->addDays(2)->toDateString(), true);

        $this->get(route('booking.status', $booking->access_token))
            ->assertOk()
            ->assertViewHas('outstanding', 400000);
    }

    public function test_no_reschedule_email_when_the_guest_has_no_email(): void
    {
        $this->customer->update(['email' => null]);
        $booking = $this->paidBooking($this->unit('DOM-01'));

        $this->service()->reschedule($booking, ...$this->nextWeekStay());

        Mail::assertNothingQueued();
    }
}
