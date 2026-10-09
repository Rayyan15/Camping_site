<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Events\BookingNeedsReview;
use App\Jobs\ReleaseExpiredHolds;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\RefundPolicy;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Notifications\BookingNeedsReviewNotification;
use App\Services\BookingService;
use App\Services\Payment\PaymentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NeedsReviewNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        RefundPolicy::create(['min_days_before' => 0, 'percent' => 100]);

        $type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 4,
            'base_price_weekday' => 500000, 'base_price_weekend' => 700000,
        ]);
        $this->unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'D-01', 'status' => Unit::STATUS_ACTIVE]);
        $this->customer = Customer::create(['name' => 'Budi', 'email' => 'budi@example.com', 'phone' => '0812']);
    }

    private function book(): Booking
    {
        return app(BookingService::class)->createBooking(
            $this->customer->id,
            now()->addDays(10)->toDateString(),
            now()->addDays(12)->toDateString(),
            2,
            [$this->unit->id],
        );
    }

    private function lapseThenLoseNights(): Booking
    {
        $lapsed = $this->book();
        $lapsed->update(['hold_expires_at' => now()->subMinute()]);
        (new ReleaseExpiredHolds)->handle();
        $this->book();

        return $lapsed->fresh();
    }

    private function userWithRole(string $role, bool $active = true): User
    {
        $user = User::factory()->create(['is_active' => $active]);
        $user->assignRole($role);

        return $user;
    }

    public function test_late_payment_on_taken_nights_notifies_active_owners_only(): void
    {
        $owner = $this->userWithRole('owner');
        $inactiveOwner = $this->userWithRole('owner', active: false);
        $frontOffice = $this->userWithRole('operator_fo');

        Notification::fake();
        $lapsed = $this->lapseThenLoseNights();

        app(PaymentService::class)->recordManual($lapsed, $lapsed->total, 'cash', null, $owner->id);

        $this->assertSame(BookingStatus::NeedsReview, $lapsed->fresh()->status);
        Notification::assertSentTo($owner, BookingNeedsReviewNotification::class);
        Notification::assertNotSentTo($inactiveOwner, BookingNeedsReviewNotification::class);
        Notification::assertNotSentTo($frontOffice, BookingNeedsReviewNotification::class);
    }

    public function test_the_event_fires_once_per_booking(): void
    {
        $owner = $this->userWithRole('owner');
        Event::fake([BookingNeedsReview::class]);
        $lapsed = $this->lapseThenLoseNights();

        app(PaymentService::class)->recordManual($lapsed, $lapsed->total, 'cash', null, $owner->id);

        Event::assertDispatchedTimes(BookingNeedsReview::class, 1);
    }

    public function test_the_stored_notification_names_the_booking(): void
    {
        $owner = $this->userWithRole('owner');
        $lapsed = $this->lapseThenLoseNights();

        app(PaymentService::class)->recordManual($lapsed, $lapsed->total, 'cash', null, $owner->id);

        $stored = $owner->notifications()->firstOrFail()->data;
        $this->assertSame('Booking perlu ditinjau', $stored['title']);
        $this->assertStringContainsString($lapsed->code, $stored['body']);
    }
}
