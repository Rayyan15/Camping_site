<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\TestCase;

class FakePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function registerDevRoutes(): void
    {
        Route::middleware('web')->group(base_path('routes/dev.php'));
        Route::getRoutes()->refreshNameLookups();
    }

    private function pendingBooking(): Booking
    {
        $customer = Customer::create(['name' => 'Budi', 'email' => 'budi@example.com', 'phone' => '0812']);

        return Booking::create([
            'code' => 'FAKE01', 'customer_id' => $customer->id,
            'check_in' => '2026-12-07', 'check_out' => '2026-12-09', 'guests' => 2,
            'status' => BookingStatus::PendingPayment, 'hold_expires_at' => now()->addMinutes(15),
            'subtotal' => 200000, 'tax' => 22000, 'total' => 222000,
        ]);
    }

    public function test_paying_on_the_dev_page_settles_the_booking(): void
    {
        config(['payment.gateway' => 'fake']);
        $this->registerDevRoutes();
        $booking = $this->pendingBooking();

        $intent = app(PaymentService::class)->initiate($booking);
        $this->assertSame(route('dev.pay.show', $intent->reference), $intent->redirectUrl);

        $this->get($intent->redirectUrl)->assertOk()->assertSee('Simulasi pembayaran (hanya lokal)');

        $this->post(route('dev.pay', $intent->reference), ['outcome' => 'paid'])
            ->assertRedirect(route('booking.status', $booking->access_token));

        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, Payment::sole()->status);
    }

    public function test_failing_on_the_dev_page_leaves_the_booking_pending(): void
    {
        config(['payment.gateway' => 'fake']);
        $this->registerDevRoutes();
        $booking = $this->pendingBooking();
        $intent = app(PaymentService::class)->initiate($booking);

        $this->post(route('dev.pay', $intent->reference), ['outcome' => 'failed']);

        $this->assertSame(BookingStatus::PendingPayment, $booking->fresh()->status);
        $this->assertSame(PaymentStatus::Failed, Payment::sole()->status);
    }

    public function test_dev_routes_do_not_exist_outside_local(): void
    {
        $this->assertFalse(Route::has('dev.pay'));

        $this->get('/dev/pay/anything')->assertNotFound();
        $this->post('/dev/pay/anything')->assertStatus(404);
    }

    public function test_dev_routes_are_not_registered_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['payment.gateway' => 'fake']);
        Route::getRoutes()->refreshNameLookups();

        Route::middleware('web')->group(base_path('routes/web.php'));

        $this->assertFalse(Route::has('dev.pay'));
        $this->expectException(LogicException::class);
        new FakeGateway;
    }
}
