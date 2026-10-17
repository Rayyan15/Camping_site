<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Enums\RefundStatus;
use App\Exceptions\BookingRescheduleException;
use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\ManualPaymentException;
use App\Filament\Resources\Users\UserResource;
use App\Jobs\ReleaseExpiredHolds;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\BookingUnitNight;
use App\Models\Customer;
use App\Models\DiningSpot;
use App\Models\Employee;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\AttendanceRecapService;
use App\Services\BookingRescheduleService;
use App\Services\BookingService;
use App\Services\BookingStatusTransition;
use App\Services\DiningSpotService;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use App\Services\RefundService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Regressions for the October 2026 bug hunt, one test per finding.
 */
class BugHuntRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Customer $customer;

    private CarbonImmutable $monday;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['booking.tax_rate' => 0]);

        $type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 4,
            'base_price_weekday' => 500000, 'base_price_weekend' => 700000,
        ]);
        $this->unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'DOM-01', 'status' => Unit::STATUS_ACTIVE]);
        $this->customer = Customer::create(['name' => 'Budi', 'email' => 'budi@example.com', 'phone' => '0812']);
        $this->monday = CarbonImmutable::today()->addDays(20)->next('Monday');
    }

    private function booking(?CarbonImmutable $checkIn = null, int $nights = 2): Booking
    {
        $checkIn ??= $this->monday;

        return app(BookingService::class)->createBooking(
            $this->customer->id,
            $checkIn->toDateString(),
            $checkIn->addDays($nights)->toDateString(),
            2,
            [$this->unit->id],
        );
    }

    private function paid(Booking $booking): Booking
    {
        $booking->update(['status' => BookingStatus::Paid, 'paid_amount' => $booking->total]);

        return $booking->refresh();
    }

    private function breakfast(Booking $booking, CarbonImmutable $serveAt): Order
    {
        $category = MenuCategory::firstOrCreate(['name' => 'Sarapan'], ['sort_order' => 1]);
        $item = MenuItem::firstOrCreate(['name' => 'Nasi goreng'], ['category_id' => $category->id, 'price' => 30000, 'is_available' => true]);

        return app(OrderService::class)->createOrder(
            Order::SOURCE_PREORDER,
            [['menu_item_id' => $item->id, 'qty' => 1]],
            $booking->id,
            null,
            $serveAt,
        );
    }

    public function test_reschedule_moves_preordered_meals_with_the_stay(): void
    {
        $booking = $this->paid($this->booking());
        $meal = $this->breakfast($booking, $this->monday->addDay()->setTime(7, 0));
        $newIn = $this->monday->addWeek();

        app(BookingRescheduleService::class)->reschedule($booking, $newIn->toDateString(), $newIn->addDays(2)->toDateString());

        $this->assertSame($newIn->addDay()->setTime(7, 0)->toDateTimeString(), $meal->fresh()->scheduled_at->toDateTimeString());
    }

    public function test_reschedule_that_leaves_a_meal_outside_the_stay_is_refused(): void
    {
        $booking = $this->paid($this->booking(nights: 3));
        $meal = $this->breakfast($booking, $this->monday->addDays(3)->setTime(7, 0));
        $newIn = $this->monday->addWeek();

        try {
            app(BookingRescheduleService::class)->reschedule($booking, $newIn->toDateString(), $newIn->addDay()->toDateString());
            $this->fail('A meal outside the new stay must block the reschedule.');
        } catch (BookingRescheduleException) {
            $this->assertSame($this->monday->toDateString(), $booking->fresh()->check_in->toDateString());
            $this->assertSame($this->monday->addDays(3)->toDateString(), $meal->fresh()->scheduled_at->toDateString());
        }
    }

    public function test_manual_payment_on_a_cancelled_booking_is_refused(): void
    {
        $booking = $this->booking();
        $booking->update(['status' => BookingStatus::Cancelled]);

        $this->expectException(ManualPaymentException::class);

        app(PaymentService::class)->recordManual($booking, $booking->total, 'cash', null, User::factory()->create()->id);
    }

    public function test_refund_can_be_approved_after_the_booking_went_to_review(): void
    {
        $booking = $this->booking();
        $booking->update(['status' => BookingStatus::NeedsReview, 'paid_amount' => 200000]);
        $refund = Refund::create([
            'booking_id' => $booking->id, 'amount' => 200000, 'reason' => 'Tamu batal', 'status' => RefundStatus::Requested,
        ]);

        app(RefundService::class)->approve($refund, User::factory()->create()->id);

        $this->assertSame(BookingStatus::Refunded, $booking->fresh()->status);
    }

    public function test_lapsed_hold_with_a_down_payment_keeps_its_nights(): void
    {
        $withMoney = $this->booking();
        $withMoney->update(['paid_amount' => 200000, 'hold_expires_at' => now()->subMinute()]);

        (new ReleaseExpiredHolds)->handle();

        $this->assertSame(BookingStatus::NeedsReview, $withMoney->fresh()->status);
        $this->assertSame(2, BookingUnitNight::where('unit_id', $this->unit->id)->count());
    }

    public function test_lapsed_hold_without_money_releases_its_nights(): void
    {
        $empty = $this->booking();
        $empty->update(['hold_expires_at' => now()->subMinute()]);

        (new ReleaseExpiredHolds)->handle();

        $this->assertSame(BookingStatus::Expired, $empty->fresh()->status);
        $this->assertSame(0, BookingUnitNight::where('unit_id', $this->unit->id)->count());
    }

    public function test_check_in_is_refused_while_a_balance_is_open(): void
    {
        $booking = $this->booking(CarbonImmutable::today());
        $booking->update(['status' => BookingStatus::Paid, 'paid_amount' => $booking->total - 100000]);

        $this->expectException(InvalidBookingTransitionException::class);

        app(BookingStatusTransition::class)->apply($booking->refresh(), BookingStatus::CheckedIn);
    }

    public function test_cafe_table_linked_to_a_unit_never_bills_the_tent_booking(): void
    {
        $this->paid($this->booking(CarbonImmutable::today()));
        $table = DiningSpot::create(['name' => 'Meja 3', 'type' => DiningSpot::TYPE_TABLE, 'unit_id' => $this->unit->id, 'qr_token' => 'table-token']);
        $tent = DiningSpot::create(['name' => 'Tenda DOM-01', 'type' => DiningSpot::TYPE_TENT, 'unit_id' => $this->unit->id, 'qr_token' => 'tent-token']);

        $this->assertNull(app(DiningSpotService::class)->activeBooking($table));
        $this->assertNotNull(app(DiningSpotService::class)->activeBooking($tent));
    }

    public function test_report_and_proof_downloads_require_owner_two_factor(): void
    {
        $this->seed(RoleSeeder::class);
        config(['access.owner_two_factor_required' => true]);
        $owner = User::factory()->create();
        $owner->assignRole(User::ROLE_OWNER);

        $this->actingAs($owner)
            ->get(route('admin.reports.excel', ['type' => 'booking', 'periode' => 'bulan_ini']))
            ->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }

    public function test_staff_account_list_hides_guest_accounts(): void
    {
        $this->seed(RoleSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole(User::ROLE_CASHIER);
        $guest = User::factory()->create();

        $ids = UserResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($staff->id, $ids);
        $this->assertNotContains($guest->id, $ids);
    }

    public function test_attendance_on_an_off_day_does_not_inflate_the_recap(): void
    {
        config(['attendance.off_weekdays' => [7]]);
        $this->travelTo(CarbonImmutable::parse('2026-09-30 18:00'));
        $employee = Employee::create(['name' => 'Asep', 'position' => 'OB']);
        $employee->forceFill(['created_at' => '2026-09-01 08:00'])->save();
        Attendance::create(['employee_id' => $employee->id, 'date' => '2026-09-06', 'status' => AttendanceStatus::Present]);

        $row = app(AttendanceRecapService::class)->forEmployee($employee->refresh(), CarbonImmutable::parse('2026-09-01'));

        $this->assertSame(0, $row->present);
    }
}
