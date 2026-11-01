<?php

namespace Tests\Feature;

use App\Enums\AddonUnit;
use App\Enums\BookingStatus;
use App\Exceptions\BookingAddonChangeException;
use App\Models\Addon;
use App\Models\Customer;
use App\Models\Unit;
use App\Models\UnitType;
use App\Services\BookingAddonService;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingAddonChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_add_and_remove_add_ons_and_the_total_follows(): void
    {
        $type = UnitType::create(['name' => 'Dome', 'slug' => 'dome', 'capacity' => 4, 'base_price_weekday' => 500000, 'base_price_weekend' => 500000]);
        $unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'DOM-01', 'status' => Unit::STATUS_ACTIVE]);
        $customer = Customer::create(['name' => 'Budi', 'email' => 'budi@example.com', 'phone' => '0812']);
        $bed = Addon::create(['name' => 'Extra bed', 'price' => 100000, 'unit' => AddonUnit::PerNight, 'is_active' => true]);
        $atv = Addon::create(['name' => 'ATV', 'price' => 250000, 'unit' => AddonUnit::PerItem, 'is_active' => true]);
        $checkIn = CarbonImmutable::today()->addDays(10);

        $booking = app(BookingService::class)->createBooking($customer->id, $checkIn->toDateString(), $checkIn->addDays(2)->toDateString(), 2, [$unit->id], [$bed->id => 1]);
        $booking->update(['status' => BookingStatus::Paid, 'paid_amount' => $booking->total]);
        $this->assertSame(1200000, $booking->total);

        $difference = app(BookingAddonService::class)->replace($booking, [$bed->id => 0, $atv->id => 1]);

        $booking->refresh();
        $this->assertSame(50000, $difference);
        $this->assertSame(1250000, $booking->total);
        $this->assertSame(['ATV x1'], $booking->load('addons.addon')->addonLabels());

        $booking->update(['status' => BookingStatus::CheckedOut]);
        $this->expectException(BookingAddonChangeException::class);
        app(BookingAddonService::class)->replace($booking, [$atv->id => 2]);
    }
}
