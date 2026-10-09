<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BookingActionsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        $this->customer = Customer::create(['name' => 'Tamu Uji', 'phone' => '081234567890']);
    }

    private function loginAs(string $role): User
    {
        $user = User::create([
            'name' => $role,
            'email' => $role.'@example.test',
            'password' => 'secret-pass-123',
            'is_active' => true,
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function makeBooking(BookingStatus $status, int $paid = 0): Booking
    {
        return Booking::create([
            'code' => 'BK-'.strtoupper($status->value),
            'customer_id' => $this->customer->id,
            'check_in' => '2026-12-01',
            'check_out' => '2026-12-02',
            'guests' => 2,
            'status' => $status,
            'subtotal' => 100000,
            'total' => 100000,
            'paid_amount' => $paid,
        ]);
    }

    public function test_front_office_checks_a_paid_booking_in_and_out(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 14:00', 'Asia/Jakarta'));
        $this->loginAs('operator_fo');
        $booking = $this->makeBooking(BookingStatus::Paid, 100000);

        Livewire::test(ListBookings::class)
            ->assertTableActionHidden('check_out', $booking)
            ->callTableAction('check_in', $booking);

        $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);

        Livewire::test(ListBookings::class)
            ->assertTableActionHidden('check_in', $booking)
            ->callTableAction('check_out', $booking);

        $this->assertSame(BookingStatus::CheckedOut, $booking->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['subject_type' => (new Booking)->getMorphClass(), 'subject_id' => $booking->id]);
    }

    public function test_owner_can_check_in_from_the_edit_page(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 14:00', 'Asia/Jakarta'));
        $this->loginAs('owner');
        $booking = $this->makeBooking(BookingStatus::Paid, 100000);

        Livewire::test(EditBooking::class, ['record' => $booking->getKey()])
            ->callAction('check_in')
            ->assertFormSet(['status' => BookingStatus::CheckedIn->value]);

        $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);
    }

    public function test_cashier_does_not_see_booking_actions(): void
    {
        $this->loginAs('operator_kasir');
        $paid = $this->makeBooking(BookingStatus::Paid, 100000);

        Livewire::test(ListBookings::class)
            ->assertTableActionHidden('check_in', $paid)
            ->assertTableActionHidden('record_payment', $paid);

        $this->assertFalse(auth()->user()->can('checkIn', $paid));
        $this->assertSame(BookingStatus::Paid, $paid->fresh()->status);
    }

    public function test_invalid_transitions_are_not_offered(): void
    {
        $this->loginAs('owner');
        $pending = $this->makeBooking(BookingStatus::PendingPayment);
        $cancelled = $this->makeBooking(BookingStatus::Cancelled);
        $review = $this->makeBooking(BookingStatus::NeedsReview, 100000);

        Livewire::test(ListBookings::class)
            ->assertTableActionHidden('check_in', $pending)
            ->assertTableActionHidden('check_out', $pending)
            ->assertTableActionHidden('check_in', $cancelled)
            ->assertTableActionHidden('check_in', $review)
            ->assertTableActionHidden('record_payment', $cancelled);
    }

    public function test_delete_actions_are_gone(): void
    {
        $this->loginAs('owner');
        $booking = $this->makeBooking(BookingStatus::Paid, 100000);

        Livewire::test(EditBooking::class, ['record' => $booking->getKey()])
            ->assertActionDoesNotExist('delete');
    }

    public function test_front_office_records_a_transfer_with_a_private_proof(): void
    {
        $user = $this->loginAs('operator_fo');
        $booking = $this->makeBooking(BookingStatus::PendingPayment);
        $booking->update(['hold_expires_at' => now()->addHour()]);

        Livewire::test(ListBookings::class)
            ->callTableAction('record_payment', $booking, [
                'method' => PaymentMethod::Transfer->value,
                'amount' => 100000,
                'proof_path' => UploadedFile::fake()->image('bukti.png'),
                'note' => 'Transfer BCA',
            ])
            ->assertHasNoTableActionErrors();

        $payment = Payment::firstOrFail();
        $this->assertSame(100000, $payment->amount);
        $this->assertSame($user->id, $payment->recorded_by);
        $this->assertNotNull($payment->proof_path);
        $this->assertStringStartsWith('payment-proofs/', $payment->proof_path);
        $this->assertStringEndsWith('.png', $payment->proof_path);
        $this->assertStringNotContainsString('bukti', $payment->proof_path);
        Storage::disk('local')->assertExists($payment->proof_path);
        Storage::disk('public')->assertMissing($payment->proof_path);
        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);
    }

    public function test_invalid_amounts_and_proofs_are_rejected(): void
    {
        $this->loginAs('operator_fo');
        $booking = $this->makeBooking(BookingStatus::Paid, 40000);

        $rejected = [
            ['method' => PaymentMethod::Cash->value, 'amount' => 0],
            ['method' => PaymentMethod::Cash->value, 'amount' => -5000],
            ['method' => PaymentMethod::Cash->value, 'amount' => 60001],
            ['method' => PaymentMethod::Cash->value, 'amount' => 'abc'],
            ['method' => PaymentMethod::Transfer->value, 'amount' => 60000, 'proof_path' => null],
            ['method' => PaymentMethod::Transfer->value, 'amount' => 60000, 'proof_path' => UploadedFile::fake()->create('virus.exe', 10)],
            ['method' => PaymentMethod::Transfer->value, 'amount' => 60000, 'proof_path' => UploadedFile::fake()->create('besar.pdf', 3000, 'application/pdf')],
        ];

        foreach ($rejected as $data) {
            Livewire::test(ListBookings::class)
                ->callTableAction('record_payment', $booking, $data)
                ->assertHasTableActionErrors();
        }

        $this->assertSame(0, Payment::count());
        $this->assertSame(40000, $booking->fresh()->paid_amount);
    }
}
