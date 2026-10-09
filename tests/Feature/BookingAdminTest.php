<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Widgets\LatestBookings;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Services\BookingStatusTransition;
use App\Services\UnitNightLedger;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingAdminTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'password' => 'secret-pass-123',
            'is_active' => true,
        ]);
        $owner->assignRole(User::ROLE_OWNER);
        $this->actingAs($owner);

        $this->customer = Customer::create(['name' => 'Tamu Uji', 'phone' => '081234567890']);
    }

    private function makeBooking(BookingStatus $status, string $code): Booking
    {
        return Booking::create([
            'code' => $code,
            'customer_id' => $this->customer->id,
            'check_in' => '2026-12-01',
            'check_out' => '2026-12-02',
            'guests' => 2,
            'status' => $status,
            'subtotal' => 100000,
            'total' => 100000,
            'paid_amount' => $status === BookingStatus::PendingPayment ? 0 : 100000,
        ]);
    }

    /**
     * @return array<int, Booking>
     */
    private function bookingsOfEveryStatus(): array
    {
        return array_map(
            fn (BookingStatus $status): Booking => $this->makeBooking($status, 'BK-'.strtoupper($status->value)),
            BookingStatus::cases(),
        );
    }

    public function test_list_page_renders_bookings_of_every_status(): void
    {
        $bookings = $this->bookingsOfEveryStatus();

        Livewire::test(ListBookings::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($bookings);
    }

    public function test_latest_bookings_widget_renders_every_status_label(): void
    {
        $this->bookingsOfEveryStatus();

        $component = Livewire::test(LatestBookings::class)->assertSuccessful();

        $component->assertSee(BookingStatus::Paid->getLabel());
    }

    public function test_edit_form_loads_paid_status_and_saves_a_valid_transition(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 14:00', 'Asia/Jakarta'));
        $booking = $this->makeBooking(BookingStatus::Paid, 'BK-PAID');

        Livewire::test(EditBooking::class, ['record' => $booking->getKey()])
            ->assertFormSet(['status' => BookingStatus::Paid->value])
            ->fillForm(['status' => BookingStatus::CheckedIn->value, 'notes' => 'Datang sore'])
            ->call('save')
            ->assertHasNoFormErrors();

        $booking->refresh();
        $this->assertSame(BookingStatus::CheckedIn, $booking->status);
        $this->assertSame('Datang sore', $booking->notes);
    }

    public function test_cancelling_a_pending_booking_from_the_form_frees_its_nights(): void
    {
        $booking = $this->makeBooking(BookingStatus::PendingPayment, 'BK-CANCEL');

        $this->mock(UnitNightLedger::class)
            ->shouldReceive('release')
            ->once()
            ->with([$booking->getKey()]);

        Livewire::test(EditBooking::class, ['record' => $booking->getKey()])
            ->fillForm(['status' => BookingStatus::Cancelled->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertNull($booking->hold_expires_at);
    }

    public function test_edit_form_rejects_unknown_and_unpaid_statuses(): void
    {
        $booking = $this->makeBooking(BookingStatus::PendingPayment, 'BK-PEND');

        foreach (['confirmed', 'completed', BookingStatus::Paid->value] as $invalid) {
            Livewire::test(EditBooking::class, ['record' => $booking->getKey()])
                ->fillForm(['status' => $invalid])
                ->call('save')
                ->assertHasFormErrors(['status']);
        }

        $this->assertSame(BookingStatus::PendingPayment, $booking->fresh()->status);
    }

    public function test_edit_form_does_not_change_dates_or_amounts(): void
    {
        $booking = $this->makeBooking(BookingStatus::Paid, 'BK-LOCK');

        Livewire::test(EditBooking::class, ['record' => $booking->getKey()])
            ->fillForm(['check_in' => '2027-01-01', 'total' => 1, 'paid_amount' => 1])
            ->call('save');

        $booking->refresh();
        $this->assertSame('2026-12-01', $booking->check_in->toDateString());
        $this->assertSame(100000, $booking->total);
        $this->assertSame(100000, $booking->paid_amount);
    }

    public function test_create_route_does_not_exist(): void
    {
        $this->assertArrayNotHasKey('create', BookingResource::getPages());
        $this->assertFalse(BookingResource::hasPage('create'));
    }

    public function test_transition_service_only_offers_manual_moves(): void
    {
        $transition = new BookingStatusTransition;

        $this->assertSame([BookingStatus::CheckedIn], $transition->allowedFrom(BookingStatus::Paid));
        $this->assertSame([], $transition->allowedFrom(BookingStatus::Refunded));
        $this->assertArrayNotHasKey(BookingStatus::Paid->value, $transition->optionsFor(BookingStatus::PendingPayment));
    }
}
