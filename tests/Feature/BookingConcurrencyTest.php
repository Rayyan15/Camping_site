<?php

namespace Tests\Feature;

use App\Exceptions\UnitUnavailableException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Unit;
use App\Models\UnitType;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private UnitType $type;

    /** @var array<int, Unit> */
    private array $units = [];

    private string $checkIn;

    private string $checkOut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 4,
            'base_price_weekday' => 500000, 'base_price_weekend' => 700000,
        ]);

        foreach (['D-01', 'D-02', 'D-03'] as $code) {
            $this->units[$code] = Unit::create(['unit_type_id' => $this->type->id, 'code' => $code, 'status' => Unit::STATUS_ACTIVE]);
        }

        $this->checkIn = now()->addDays(10)->toDateString();
        $this->checkOut = now()->addDays(12)->toDateString();
    }

    private function checkout(array $overrides = []): Booking
    {
        return app(BookingService::class)->createFromCheckout(array_merge([
            'customer_name' => 'Budi',
            'customer_email' => 'budi@example.com',
            'customer_phone' => '0812',
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'guests' => 2,
            'unit_type_id' => $this->type->id,
            'quantity' => 1,
        ], $overrides));
    }

    private function unitIdsOf(Booking $booking): array
    {
        return $booking->bookingUnits()->orderBy('unit_id')->pluck('unit_id')->all();
    }

    public function test_second_checkout_gets_next_free_unit_when_first_unit_is_claimed(): void
    {
        $first = $this->checkout();
        $second = $this->checkout(['customer_email' => 'sari@example.com', 'customer_phone' => '0899']);

        $this->assertSame([$this->units['D-01']->id], $this->unitIdsOf($first));
        $this->assertSame([$this->units['D-02']->id], $this->unitIdsOf($second));
    }

    public function test_quantity_skips_claimed_units_and_fills_the_request(): void
    {
        $this->checkout();

        $booking = $this->checkout(['quantity' => 2, 'customer_email' => 'sari@example.com', 'customer_phone' => '0899']);

        $this->assertSame(
            [$this->units['D-02']->id, $this->units['D-03']->id],
            $this->unitIdsOf($booking),
        );
    }

    public function test_checkout_fails_when_stock_is_short_and_leaves_no_partial_booking(): void
    {
        $this->checkout(['quantity' => 2]);
        $bookings = Booking::count();
        $customers = Customer::count();

        try {
            $this->checkout(['quantity' => 2, 'customer_email' => 'sari@example.com', 'customer_phone' => '0899']);
            $this->fail('Only one unit is left, two were requested.');
        } catch (UnitUnavailableException) {
            $this->assertSame($bookings, Booking::count());
            $this->assertSame($customers, Customer::count());
        }
    }

    public function test_same_email_and_phone_reuses_customer_and_updates_the_name(): void
    {
        $first = $this->checkout();
        $second = $this->checkout(['customer_name' => 'Budi Santoso']);

        $this->assertSame($first->customer_id, $second->customer_id);
        $this->assertSame('Budi Santoso', Customer::find($first->customer_id)->name);
    }

    public function test_same_email_with_another_phone_gets_a_separate_customer(): void
    {
        $first = $this->checkout();
        $second = $this->checkout(['customer_name' => 'Orang Lain', 'customer_phone' => '0877']);

        $this->assertNotSame($first->customer_id, $second->customer_id);

        $original = Customer::find($first->customer_id);
        $this->assertSame('Budi', $original->name);
        $this->assertSame('0812', $original->phone);
        $this->assertSame(1, $original->bookings()->count());

        $newcomer = Customer::find($second->customer_id);
        $this->assertSame('Orang Lain', $newcomer->name);
        $this->assertSame('0877', $newcomer->phone);
    }
}
