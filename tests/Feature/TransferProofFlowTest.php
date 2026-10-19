<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewReason;
use App\Enums\PaymentStatus;
use App\Events\TransferProofSubmitted;
use App\Exceptions\TransferProofException;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\PaymentLabels;
use App\Jobs\ReleaseExpiredHolds;
use App\Mail\BookingReceived;
use App\Mail\PaymentConfirmed;
use App\Mail\TransferProofReceived;
use App\Mail\TransferProofRejected;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Notifications\TransferProofSubmittedNotification;
use App\Services\BookingService;
use App\Services\DashboardMetricsService;
use App\Services\Payment\TransferProofService;
use App\Services\RefundService;
use App\Services\WhatsAppShareLink;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TransferProofFlowTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01 10:00:00');
        Storage::fake('local');
        Mail::fake();
        config([
            'site.bank.name' => 'Bank Uji',
            'site.bank.account_number' => '0000111122',
            'site.bank.account_holder' => 'Pemilik Uji',
            'site.whatsapp_number' => '6281200000000',
        ]);
        $this->seed(RoleSeeder::class);

        $type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 2,
            'base_price_weekday' => 100000, 'base_price_weekend' => 150000,
        ]);
        $unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'D1', 'status' => 'active']);
        $customer = Customer::create(['name' => 'Tamu Uji', 'phone' => '0811', 'email' => 'tamu@example.com']);

        $this->booking = app(BookingService::class)->createBooking(
            $customer->id, '2027-01-04', '2027-01-06', 2, [$unit->id],
        );
    }

    private function proofFile(string $name = 'bukti.png', string $mime = 'image/png', int $kilobytes = 20): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, $mime);
    }

    private function upload(?UploadedFile $file = null, array $extra = [])
    {
        return $this->post(route('checkout.proof', $this->booking->access_token), [
            'proof' => $file ?? $this->proofFile(),
            ...$extra,
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

    private function pendingProof(): Payment
    {
        return $this->booking->payments()->where('status', PaymentStatus::Pending)->latest('id')->firstOrFail();
    }

    public function test_hold_is_two_hours_by_default(): void
    {
        $this->assertSame(120, config('booking.hold_minutes'));
        $this->assertSame(24, config('booking.proof_review_hours'));
        $this->assertTrue($this->booking->hold_expires_at->equalTo(now()->addMinutes(120)));
    }

    public function test_valid_upload_creates_a_pending_transfer_with_the_server_amount(): void
    {
        $response = $this->upload(extra: ['amount' => 1]);

        $response->assertRedirect(route('booking.status', $this->booking->access_token));
        $response->assertSessionHas('success');

        $payment = $this->pendingProof();
        $this->assertSame(PaymentMethod::Transfer, $payment->method);
        $this->assertSame($this->booking->total, $payment->amount);
        $this->assertNull($payment->paid_at);
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->assertStringStartsWith('payment-proofs/', $payment->proof_path);
        $this->assertSame(0, $this->booking->fresh()->paid_amount);
        Mail::assertQueued(TransferProofReceived::class, fn ($mail) => $mail->hasTo('tamu@example.com'));
    }

    public function test_upload_extends_the_hold_while_staff_review(): void
    {
        $this->upload()->assertSessionHasNoErrors();

        $this->assertTrue($this->booking->fresh()->hold_expires_at->equalTo(now()->addHours(24)));
    }

    public function test_wrong_or_oversized_files_are_refused(): void
    {
        $this->upload($this->proofFile('catatan.txt', 'text/plain'))->assertSessionHasErrors('proof');
        $this->upload($this->proofFile('besar.png', 'image/png', 5000))->assertSessionHasErrors('proof');
        $this->post(route('checkout.proof', $this->booking->access_token))->assertSessionHasErrors('proof');

        $this->assertSame(0, $this->booking->payments()->count());
    }

    public function test_pdf_proof_is_accepted(): void
    {
        $this->upload($this->proofFile('bukti.pdf', 'application/pdf'))->assertSessionHasNoErrors();

        $this->assertSame(1, $this->booking->payments()->count());
    }

    public function test_new_upload_replaces_the_pending_one(): void
    {
        $this->upload();
        $first = $this->pendingProof();

        $this->upload();

        $this->assertSame(PaymentStatus::Expired, $first->fresh()->status);
        $this->assertNotSame($first->id, $this->pendingProof()->id);
        $this->assertSame(1, $this->booking->payments()->where('status', PaymentStatus::Pending)->count());
    }

    public function test_missing_bank_account_hides_the_form_and_refuses_uploads(): void
    {
        config(['site.bank.account_number' => null]);

        $this->get(route('checkout.show', $this->booking->access_token))
            ->assertOk()
            ->assertSee('Rekening belum diatur, hubungi admin.')
            ->assertDontSee('name="proof"', false);

        $this->upload()->assertRedirect(route('checkout.show', $this->booking->access_token))
            ->assertSessionHas('error', 'Rekening belum diatur, hubungi admin.');
        $this->assertSame(0, $this->booking->payments()->count());
    }

    public function test_checkout_shows_the_account_amount_and_upload_form(): void
    {
        $this->get(route('checkout.show', $this->booking->access_token))
            ->assertOk()
            ->assertSee('Bank Uji')
            ->assertSee('0000111122')
            ->assertSee('Pemilik Uji')
            ->assertSee('Rp '.number_format($this->booking->total, 0, ',', '.'))
            ->assertSee('name="proof"', false)
            ->assertSee('data-remaining="7200"', false)
            ->assertDontSee('name="plan"', false);
    }

    public function test_upload_after_the_hold_lapses_is_refused(): void
    {
        $this->travel(121)->minutes();

        $this->upload()->assertSessionHas('error');
        $this->assertSame(0, $this->booking->payments()->count());
    }

    public function test_uploading_for_a_paid_booking_goes_to_the_status_page(): void
    {
        $this->booking->update(['status' => BookingStatus::Paid]);

        $this->upload()->assertRedirect(route('booking.status', $this->booking->access_token));
        $this->assertSame(0, $this->booking->payments()->count());
    }

    public function test_confirming_marks_the_payment_paid_and_the_booking_paid(): void
    {
        $this->upload();
        $payment = $this->pendingProof();
        $staff = $this->loginAs(User::ROLE_FRONT_OFFICE);

        app(TransferProofService::class)->confirm($payment, $staff->id);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame($staff->id, $payment->reviewed_by);
        $booking = $this->booking->fresh();
        $this->assertSame(BookingStatus::Paid, $booking->status);
        $this->assertSame($booking->total, $booking->paid_amount);
        Mail::assertQueued(PaymentConfirmed::class, fn ($mail) => $mail->hasTo('tamu@example.com'));
    }

    public function test_down_payment_then_settlement(): void
    {
        Setting::updateOrCreate(['key' => 'down_payment_percent'], ['value' => '30']);
        $staff = $this->loginAs(User::ROLE_OWNER);
        $transfers = app(TransferProofService::class);

        $this->upload(extra: ['plan' => 'down_payment']);
        $dp = $this->pendingProof();
        $this->assertSame((int) ceil($this->booking->total * 0.3), $dp->amount);

        $transfers->confirm($dp, $staff->id);
        $booking = $this->booking->fresh();
        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertSame($dp->amount, $booking->paid_amount);

        $this->upload();
        $rest = $this->pendingProof();
        $this->assertSame($booking->total - $dp->amount, $rest->amount);

        $transfers->confirm($rest, $staff->id);
        $booking = $this->booking->fresh();
        $this->assertSame(BookingStatus::Paid, $booking->status);
        $this->assertSame($booking->total, $booking->paid_amount);
    }

    public function test_rejecting_requires_a_reason_and_lets_the_guest_upload_again(): void
    {
        $this->upload();
        $payment = $this->pendingProof();
        $staff = $this->loginAs(User::ROLE_FRONT_OFFICE);

        app(TransferProofService::class)->reject($payment, 'Nominal tidak sesuai', $staff->id);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('Nominal tidak sesuai', $payment->rejection_reason);
        $this->assertSame(0, $this->booking->fresh()->paid_amount);
        Mail::assertQueued(TransferProofRejected::class, fn ($mail) => $mail->reason === 'Nominal tidak sesuai');
        $this->assertTrue($this->booking->fresh()->hold_expires_at->equalTo(now()->addMinutes(120)));

        $this->get(route('booking.status', $this->booking->access_token))
            ->assertOk()
            ->assertSee('Bukti ditolak: Nominal tidak sesuai');
        $this->get(route('checkout.show', $this->booking->access_token))
            ->assertOk()
            ->assertSee('Bukti transfer ditolak: Nominal tidak sesuai');

        $this->upload()->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Pending, $this->pendingProof()->status);
    }

    public function test_a_reviewed_proof_cannot_be_reviewed_again(): void
    {
        $this->upload();
        $payment = $this->pendingProof();
        $transfers = app(TransferProofService::class);
        $staffId = $this->loginAs(User::ROLE_OWNER)->id;
        $transfers->confirm($payment, $staffId);

        $this->expectException(TransferProofException::class);
        $transfers->reject($payment, 'Terlambat', $staffId);
    }

    public function test_a_cancelled_booking_proof_is_not_confirmed(): void
    {
        $this->upload();
        $payment = $this->pendingProof();
        $this->booking->update(['status' => BookingStatus::Cancelled]);

        $this->expectException(TransferProofException::class);

        try {
            app(TransferProofService::class)->confirm($payment, $this->loginAs(User::ROLE_OWNER)->id);
        } finally {
            $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        }
    }

    public function test_status_page_tells_the_guest_the_proof_is_waiting(): void
    {
        $this->upload();

        $this->get(route('booking.status', $this->booking->access_token))
            ->assertOk()
            ->assertSee('Bukti diterima, menunggu konfirmasi admin');
        $this->get(route('checkout.show', $this->booking->access_token))
            ->assertOk()
            ->assertSee('Bukti diterima, menunggu konfirmasi admin')
            ->assertSee('Kirim ulang bukti bayar');
    }

    public function test_release_job_sends_a_booking_with_a_pending_proof_to_review(): void
    {
        $this->upload();
        $this->booking->update(['hold_expires_at' => now()->subMinute()]);

        (new ReleaseExpiredHolds)->handle();

        $this->assertSame(BookingStatus::NeedsReview, $this->booking->fresh()->status);
    }

    public function test_release_job_still_expires_a_booking_without_a_proof(): void
    {
        $this->booking->update(['hold_expires_at' => now()->subMinute()]);

        (new ReleaseExpiredHolds)->handle();

        $this->assertSame(BookingStatus::Expired, $this->booking->fresh()->status);
    }

    public function test_release_job_ignores_a_rejected_proof(): void
    {
        $this->upload();
        app(TransferProofService::class)->reject($this->pendingProof(), 'Buram', $this->loginAs(User::ROLE_OWNER)->id);
        $this->booking->update(['hold_expires_at' => now()->subMinute()]);

        (new ReleaseExpiredHolds)->handle();

        $this->assertSame(BookingStatus::Expired, $this->booking->fresh()->status);
    }

    public function test_admin_whatsapp_button_only_shows_when_the_number_is_set(): void
    {
        $this->get(route('checkout.show', $this->booking->access_token))->assertSee('Tanya admin via WhatsApp');
        $this->get(route('booking.status', $this->booking->access_token))->assertSee('Tanya admin via WhatsApp');

        config(['site.whatsapp_number' => null]);

        $this->get(route('checkout.show', $this->booking->access_token))->assertDontSee('Tanya admin via WhatsApp');
        $this->get(route('booking.status', $this->booking->access_token))->assertDontSee('Tanya admin via WhatsApp');
        $this->assertNull(app(WhatsAppShareLink::class)->forAdmin($this->booking));
    }

    public function test_admin_whatsapp_link_carries_the_booking_details(): void
    {
        $link = app(WhatsAppShareLink::class)->forAdmin($this->booking);

        $this->assertStringStartsWith('https://wa.me/6281200000000?text=', $link);
        $text = rawurldecode(substr($link, strlen('https://wa.me/6281200000000?text=')));
        $this->assertStringContainsString('Tamu Uji', $text);
        $this->assertStringContainsString($this->booking->code, $text);
        $this->assertStringContainsString('04/01/2027', $text);
        $this->assertStringContainsString('1 x Dome', $text);
        $this->assertStringContainsString(number_format($this->booking->total, 0, ',', '.'), $text);
    }

    public function test_staff_with_payment_permission_are_notified_of_a_new_proof(): void
    {
        $owner = $this->loginAs(User::ROLE_OWNER);
        $cashier = User::create(['name' => 'Kasir', 'email' => 'kasir@example.test', 'password' => 'secret-pass-123', 'is_active' => true]);
        $cashier->assignRole(User::ROLE_CASHIER);
        Notification::fake();

        $this->upload();

        Notification::assertSentTo($owner, TransferProofSubmittedNotification::class);
        Notification::assertNotSentTo($cashier, TransferProofSubmittedNotification::class);
    }

    public function test_event_fires_after_the_proof_is_stored(): void
    {
        Event::fake([TransferProofSubmitted::class]);

        $this->upload();

        Event::assertDispatched(TransferProofSubmitted::class);
    }

    public function test_booking_store_queues_the_received_email(): void
    {
        $unitType = UnitType::first();
        Unit::create(['unit_type_id' => $unitType->id, 'code' => 'D2', 'status' => 'active']);

        $this->post(route('booking.store'), [
            'unit_type_id' => $unitType->id,
            'check_in' => '2027-02-01',
            'check_out' => '2027-02-03',
            'guests' => 2,
            'quantity' => 1,
            'customer_name' => 'Budi Santoso',
            'customer_email' => 'budi@example.com',
            'customer_phone' => '0812-3456-7890',
        ])->assertRedirect();

        Mail::assertQueued(BookingReceived::class, fn ($mail) => $mail->hasTo('budi@example.com'));
    }

    public function test_mails_render_without_emoji_and_with_the_guest_instructions(): void
    {
        $received = (new BookingReceived($this->booking))->render();
        $this->assertStringContainsString('Booking diterima', $received);
        $this->assertStringContainsString('0000111122', $received);
        $this->assertStringContainsString('tunjukkan email konfirmasi', $received);

        $confirmed = (new PaymentConfirmed($this->booking))->render();
        $this->assertStringContainsString('tunjukkan email ini', $confirmed);

        $rejected = (new TransferProofRejected($this->booking, 'Buram'))->render();
        $this->assertStringContainsString('Alasan dari admin: Buram', $rejected);

        $this->assertStringContainsString('Cek email', (new TransferProofReceived($this->booking))->render());
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $received.$confirmed.$rejected);
    }

    public function test_mail_is_skipped_when_the_guest_has_no_email(): void
    {
        $this->booking->customer->update(['email' => null]);
        Mail::fake();

        $this->upload();

        Mail::assertNothingQueued();
    }

    public function test_front_office_confirms_from_the_payment_screen(): void
    {
        $this->upload();
        $payment = $this->pendingProof();
        $this->loginAs(User::ROLE_FRONT_OFFICE);

        Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
            ->callAction('confirm_transfer')
            ->assertNotified('Transfer dikonfirmasi');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(BookingStatus::Paid, $this->booking->fresh()->status);
    }

    public function test_rejecting_from_the_payment_screen_needs_a_reason(): void
    {
        $this->upload();
        $payment = $this->pendingProof();
        $this->loginAs(User::ROLE_OWNER);

        Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
            ->callAction('reject_proof', ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
            ->callAction('reject_proof', ['reason' => 'Foto terpotong'])
            ->assertNotified('Bukti ditolak');

        $this->assertSame('Foto terpotong', $payment->fresh()->rejection_reason);
    }

    public function test_cashier_cannot_review_a_booking_proof(): void
    {
        $this->upload();
        $payment = $this->pendingProof();
        $this->loginAs(User::ROLE_CASHIER);

        Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])->assertNotFound();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_list_filter_shows_only_proofs_awaiting_review(): void
    {
        $this->upload();
        $waiting = $this->pendingProof();
        $other = $this->booking->payments()->create([
            'direction' => 'in', 'method' => PaymentMethod::Cash, 'amount' => 1000,
            'status' => PaymentStatus::Paid, 'paid_at' => now(),
        ]);
        $this->loginAs(User::ROLE_OWNER);

        Livewire::test(ListPayments::class)
            ->filterTable('awaiting_proof_review', true)
            ->assertCanSeeTableRecords([$waiting])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_dashboard_counts_proofs_awaiting_review(): void
    {
        $metrics = app(DashboardMetricsService::class);
        $this->assertSame(0, $metrics->awaitingTransferProofCount());

        $this->upload();

        $this->assertSame(1, $metrics->awaitingTransferProofCount());
    }

    public function test_manual_gateway_rejects_every_webhook(): void
    {
        $this->assertSame('manual', config('payment.gateway'));

        $this->postJson('/webhook/payment', ['order_id' => 'X-1', 'transaction_status' => 'settlement'])
            ->assertStatus(403);
    }

    public function test_cancelling_with_an_unchecked_proof_flags_it_for_refund_review(): void
    {
        $this->upload();
        $payment = $this->pendingProof();

        app(RefundService::class)->cancelWithOutcome($this->booking, 'Batal rencana');

        $payment->refresh();
        $this->assertSame(BookingStatus::Cancelled, $this->booking->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(PaymentReviewReason::ProofPendingOnCancel, $payment->review_reason);
        $this->assertStringContainsString('bukti transfer belum diperiksa', PaymentLabels::attentionNote($payment));
    }
}
