<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Mail\PaymentConfirmed;
use App\Models\Booking;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_to_english_translates_the_landing_page(): void
    {
        $this->from(route('home'))->get(route('lang.switch', 'en'))->assertRedirect(route('home'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Find a tent')
            ->assertDontSee('Cari tenda kosong');
    }

    public function test_indonesian_stays_the_default_and_unknown_locales_are_ignored(): void
    {
        $this->get(route('lang.switch', 'fr'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<html lang="id">', false)
            ->assertSee('Cari tenda kosong');
    }

    public function test_guest_email_uses_the_language_the_booking_was_made_in(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '0812', 'email' => 'ana@example.com']);
        $booking = Booking::create([
            'code' => 'RCM-261201-ENG001', 'customer_id' => $customer->id,
            'check_in' => '2026-12-20', 'check_out' => '2026-12-22', 'guests' => 2,
            'status' => BookingStatus::Paid, 'subtotal' => 200000, 'total' => 200000, 'paid_amount' => 200000,
            'locale' => 'en',
        ]);

        $html = (new PaymentConfirmed($booking))->render();

        $this->assertStringContainsString('Payment confirmed', $html);
        $this->assertStringContainsString('Booking code', $html);
        $this->assertStringNotContainsString('Pembayaran dikonfirmasi', $html);
    }
}
