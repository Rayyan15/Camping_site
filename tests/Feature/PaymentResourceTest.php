<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewReason;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentResourceTest extends TestCase
{
    use RefreshDatabase;

    private const PROOF_PATH = 'payment-proofs/bukti-uji.png';

    private Booking $booking;

    private Order $order;

    private Payment $bookingPayment;

    private Payment $orderPayment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put(self::PROOF_PATH, 'isi-bukti');

        $customer = Customer::create(['name' => 'Tamu Uji', 'phone' => '081234567890']);
        $this->booking = Booking::create([
            'code' => 'BK-UJI', 'customer_id' => $customer->id, 'check_in' => '2026-12-01',
            'check_out' => '2026-12-02', 'guests' => 2, 'status' => BookingStatus::Paid,
            'subtotal' => 100000, 'total' => 100000, 'paid_amount' => 100000,
        ]);
        $this->order = Order::create([
            'code' => 'FB-UJI', 'source' => Order::SOURCE_WALKIN, 'customer_name' => 'Pembeli Uji',
            'status' => 'new', 'total' => 45000, 'payment_status' => Order::PAYMENT_PAID,
        ]);

        $this->bookingPayment = $this->booking->payments()->create([
            'direction' => PaymentDirection::In, 'method' => PaymentMethod::Transfer, 'amount' => 100000,
            'status' => PaymentStatus::Paid, 'paid_at' => '2026-11-20 10:00:00', 'proof_path' => self::PROOF_PATH,
        ]);
        $this->orderPayment = $this->order->payments()->create([
            'direction' => PaymentDirection::In, 'method' => PaymentMethod::Cash, 'amount' => 45000,
            'status' => PaymentStatus::Paid, 'paid_at' => '2026-11-21 12:00:00',
        ]);
    }

    private function loginAs(string $role): User
    {
        $user = User::create([
            'name' => $role, 'email' => $role.'@example.test', 'password' => 'secret-pass-123', 'is_active' => true,
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_owner_sees_booking_and_order_payments_without_decimals(): void
    {
        $this->loginAs(User::ROLE_OWNER);

        Livewire::test(ListPayments::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->bookingPayment, $this->orderPayment])
            ->assertSee('Rp')
            ->assertDontSee('100.000,00');
    }

    public function test_filters_narrow_the_list(): void
    {
        $this->loginAs(User::ROLE_OWNER);
        $reviewed = $this->booking->payments()->create([
            'direction' => PaymentDirection::In, 'method' => PaymentMethod::Gateway, 'amount' => 5000,
            'status' => PaymentStatus::Paid, 'paid_at' => '2026-11-22 09:00:00',
            'review_reason' => PaymentReviewReason::Overpaid,
        ]);
        $refund = $this->booking->payments()->create([
            'direction' => PaymentDirection::Out, 'method' => PaymentMethod::Transfer, 'amount' => 20000,
            'status' => PaymentStatus::Pending,
        ]);

        Livewire::test(ListPayments::class)
            ->filterTable('method', PaymentMethod::Cash->value)
            ->assertCanSeeTableRecords([$this->orderPayment])
            ->assertCanNotSeeTableRecords([$this->bookingPayment, $reviewed, $refund])
            ->removeTableFilter('method')
            ->filterTable('direction', PaymentDirection::Out->value)
            ->assertCanSeeTableRecords([$refund])
            ->assertCanNotSeeTableRecords([$this->bookingPayment, $reviewed])
            ->removeTableFilter('direction')
            ->filterTable('status', PaymentStatus::Pending->value)
            ->assertCanSeeTableRecords([$refund])
            ->assertCanNotSeeTableRecords([$this->orderPayment])
            ->removeTableFilter('status')
            ->filterTable('needs_review', true)
            ->assertCanSeeTableRecords([$reviewed])
            ->assertCanNotSeeTableRecords([$this->bookingPayment, $this->orderPayment])
            ->removeTableFilter('needs_review')
            ->filterTable('paid_range', ['from' => '2026-11-21', 'until' => '2026-11-21'])
            ->assertCanSeeTableRecords([$this->orderPayment])
            ->assertCanNotSeeTableRecords([$this->bookingPayment, $reviewed]);
    }

    public function test_cashier_only_sees_order_payments(): void
    {
        $this->loginAs(User::ROLE_CASHIER);

        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords([$this->orderPayment])
            ->assertCanNotSeeTableRecords([$this->bookingPayment]);

        $this->assertFalse(PaymentResource::canView($this->bookingPayment));
        $this->assertTrue(PaymentResource::canView($this->orderPayment));
    }

    public function test_front_office_only_sees_booking_payments(): void
    {
        $this->loginAs(User::ROLE_FRONT_OFFICE);

        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords([$this->bookingPayment])
            ->assertCanNotSeeTableRecords([$this->orderPayment]);
    }

    public function test_payments_are_read_only(): void
    {
        $this->loginAs(User::ROLE_OWNER);

        $this->assertFalse(PaymentResource::canCreate());
        $this->assertFalse(PaymentResource::canEdit($this->bookingPayment));
        $this->assertFalse(PaymentResource::canDelete($this->bookingPayment));
    }

    public function test_view_page_shows_proof_button_only_to_entitled_staff(): void
    {
        $this->loginAs(User::ROLE_OWNER);
        Livewire::test(ViewPayment::class, ['record' => $this->bookingPayment->getKey()])
            ->assertSuccessful()
            ->assertSee('BK-UJI')
            ->assertActionVisible('view_proof');

        Livewire::test(ViewPayment::class, ['record' => $this->orderPayment->getKey()])
            ->assertActionHidden('view_proof');
    }

    public function test_cashier_cannot_open_a_booking_payment_page(): void
    {
        $this->loginAs(User::ROLE_CASHIER);

        $this->get(PaymentResource::getUrl('view', ['record' => $this->bookingPayment]))->assertNotFound();
    }

    public function test_owner_and_front_office_can_open_the_proof_inline(): void
    {
        foreach ([User::ROLE_OWNER, User::ROLE_FRONT_OFFICE] as $role) {
            $this->loginAs($role);

            $response = $this->get(route('admin.payments.proof', $this->bookingPayment));

            $response->assertOk();
            $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_cashier_cannot_open_a_booking_proof(): void
    {
        $this->loginAs(User::ROLE_CASHIER);

        $this->get(route('admin.payments.proof', $this->bookingPayment))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.payments.proof', $this->bookingPayment))->assertRedirect();
    }

    public function test_missing_proof_file_or_path_gives_404(): void
    {
        $this->loginAs(User::ROLE_OWNER);

        $this->get(route('admin.payments.proof', $this->orderPayment))->assertNotFound();

        Storage::disk('local')->delete(self::PROOF_PATH);
        $this->get(route('admin.payments.proof', $this->bookingPayment))->assertNotFound();
    }

    public function test_proof_is_stored_on_the_private_disk_only(): void
    {
        Storage::disk('local')->assertExists(self::PROOF_PATH);
        Storage::disk('public')->assertMissing(self::PROOF_PATH);
    }
}
