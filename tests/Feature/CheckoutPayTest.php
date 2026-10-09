<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Unit;
use App\Models\UnitType;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutPayTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01 10:00:00');
        config([
            'payment.gateway' => 'midtrans',
            'services.midtrans.server_key' => 'SB-Mid-server-test',
        ]);

        $type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 2,
            'base_price_weekday' => 100000, 'base_price_weekend' => 150000,
        ]);
        $unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'D1', 'status' => 'active']);
        $customer = Customer::create(['name' => 'Tamu', 'phone' => '0811', 'email' => 'tamu@example.com']);

        $this->booking = app(BookingService::class)->createBooking(
            $customer->id, '2027-01-04', '2027-01-06', 2, [$unit->id],
        );
    }

    public function test_gateway_failure_redirects_back_to_checkout_with_safe_error(): void
    {
        Http::fake(['*midtrans.com/*' => Http::response(['error_messages' => ['secret gateway detail']], 500)]);

        $response = $this->post(route('checkout.pay', $this->booking->access_token));

        $response->assertRedirect(route('checkout.show', $this->booking->access_token));
        $response->assertSessionHas('error', fn (string $message) => ! str_contains($message, 'secret gateway detail'));

        $this->get(route('checkout.show', $this->booking->access_token))
            ->assertOk()
            ->assertSee('role="alert"', false);
    }

    public function test_already_paid_booking_redirects_to_status_page(): void
    {
        Http::fake();
        $this->booking->update(['paid_amount' => $this->booking->total]);

        $response = $this->post(route('checkout.pay', $this->booking->access_token));

        $response->assertRedirect(route('booking.status', $this->booking->access_token));
        $response->assertSessionHas('success');
        Http::assertNothingSent();
    }
}
