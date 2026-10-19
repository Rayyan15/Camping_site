<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SettingKey;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BookingService;
use App\Services\Payment\PaymentService;
use App\Services\SettingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuestPaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;

    private SettingRepository $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01 10:00:00');
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        config(['site.bank.name' => 'Bank Uji', 'site.bank.account_number' => '0000111122', 'site.bank.account_holder' => 'Pemilik Uji']);

        $type = UnitType::create(['name' => 'Dome', 'slug' => 'dome', 'capacity' => 2, 'base_price_weekday' => 100000, 'base_price_weekend' => 150000]);
        $unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'D1', 'status' => 'active']);
        $customer = Customer::create(['name' => 'Tamu Uji', 'phone' => '0811', 'email' => 'tamu@example.com']);
        $this->booking = app(BookingService::class)->createBooking($customer->id, '2027-01-04', '2027-01-06', 2, [$unit->id]);
        $this->settings = app(SettingRepository::class);
    }

    private function enable(SettingKey $key, bool $on = true): void
    {
        $this->settings->put($key, $on ? '1' : '0');
    }

    public function test_only_transfer_is_offered_by_default(): void
    {
        $this->assertSame([PaymentMethod::Transfer], $this->settings->guestPaymentMethods());

        $this->get(route('checkout.show', $this->booking->access_token))
            ->assertOk()->assertSee('Transfer bank')->assertDontSee('Tunai saat tiba');
    }

    public function test_qris_needs_its_image_and_records_a_qris_proof(): void
    {
        $this->enable(SettingKey::PaymentQrisEnabled);
        $this->assertNotContains(PaymentMethod::Qris, $this->settings->guestPaymentMethods());

        $this->settings->put(SettingKey::QrisImage, 'qris/kode.png');
        $this->get(route('checkout.show', $this->booking->access_token))->assertSee('Kode QRIS', false);

        $this->post(route('checkout.proof', $this->booking->access_token), [
            'method' => 'qris',
            'proof' => UploadedFile::fake()->create('bukti.png', 20, 'image/png'),
        ])->assertRedirect(route('booking.status', $this->booking->access_token));

        $payment = $this->booking->payments()->sole();
        $this->assertSame(PaymentMethod::Qris, $payment->method);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
    }

    public function test_a_switched_off_method_is_refused(): void
    {
        $this->enable(SettingKey::PaymentTransferEnabled, false);
        $this->enable(SettingKey::PaymentCashEnabled);

        $this->post(route('checkout.proof', $this->booking->access_token), [
            'proof' => UploadedFile::fake()->create('bukti.png', 20, 'image/png'),
        ])->assertRedirect(route('checkout.show', $this->booking->access_token))->assertSessionHas('error');

        $this->assertSame(0, $this->booking->payments()->count());
    }

    public function test_cash_holds_the_tent_until_the_end_of_check_in_day_and_staff_payment_closes_it(): void
    {
        $this->enable(SettingKey::PaymentCashEnabled);

        $this->post(route('checkout.cash', $this->booking->access_token))->assertSessionHas('success');

        $this->booking->refresh();
        $this->assertSame('2027-01-04 23:59:59', $this->booking->hold_expires_at->format('Y-m-d H:i:s'));
        $this->assertTrue($this->booking->payments()->cashOnArrival()->exists());
        $this->get(route('checkout.show', $this->booking->access_token))->assertSee('Bayar tunai saat tiba');

        $staff = User::create(['name' => 'FO', 'email' => 'fo@example.test', 'password' => 'secret-pass-123', 'is_active' => true]);
        app(PaymentService::class)->recordManual($this->booking, $this->booking->total, 'cash', null, $staff->id);

        $this->assertFalse($this->booking->payments()->cashOnArrival()->exists());
    }
}
