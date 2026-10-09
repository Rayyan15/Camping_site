<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Filament\Pages\OkupansiKalender;
use App\Models\Booking;
use App\Models\BookingUnit;
use App\Models\Customer;
use App\Models\Unit;
use App\Models\UnitBlock;
use App\Models\UnitType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class OccupancyCalendarPageTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-09 10:00:00');
        $this->seed(RoleSeeder::class);

        $type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 2,
            'base_price_weekday' => 100000, 'base_price_weekend' => 150000,
        ]);
        $this->unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'D-1', 'status' => 'active']);

        $customer = Customer::create(['name' => 'Budi Santoso', 'phone' => '081234567890', 'email' => 'budi.rahasia@example.test']);
        $today = CarbonImmutable::now()->startOfDay();
        $this->booking = Booking::create([
            'code' => 'RCM-OKUP01', 'customer_id' => $customer->id,
            'check_in' => $today->toDateString(), 'check_out' => $today->addDays(2)->toDateString(), 'guests' => 2,
            'status' => BookingStatus::Paid, 'subtotal' => 100000, 'tax' => 11000, 'total' => 111000, 'paid_amount' => 111000,
        ]);
        BookingUnit::create([
            'booking_id' => $this->booking->id, 'unit_id' => $this->unit->id, 'check_in' => $today->toDateString(),
            'check_out' => $today->addDays(2)->toDateString(), 'price_per_night' => 100000, 'nights' => 2, 'subtotal' => 200000,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::create(['name' => ucfirst($role), 'email' => $role.Str::random(6).'@example.test', 'password' => Str::random(24), 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_owner_and_front_office_see_the_timeline_with_a_legend(): void
    {
        foreach ([User::ROLE_OWNER, User::ROLE_FRONT_OFFICE] as $role) {
            $this->actingAs($this->userWithRole($role));

            Livewire::test(OkupansiKalender::class)
                ->assertOk()
                ->assertSee('Dome')
                ->assertSee('D-1')
                ->assertSee('Terisi')
                ->assertSee('Legenda status')
                ->assertSee('Hari ini');
        }
    }

    public function test_cashier_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_CASHIER));

        $this->assertFalse(OkupansiKalender::canAccess());
        $this->get('/admin/okupansi-kalender')->assertForbidden();
    }

    public function test_only_owner_and_front_office_hold_the_permission(): void
    {
        $this->assertTrue($this->userWithRole(User::ROLE_OWNER)->can('view_occupancy_calendar'));
        $this->assertTrue($this->userWithRole(User::ROLE_FRONT_OFFICE)->can('view_occupancy_calendar'));
        $this->assertFalse($this->userWithRole(User::ROLE_CASHIER)->can('view_occupancy_calendar'));
    }

    public function test_navigation_moves_by_the_window_width_and_returns_to_today(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));

        Livewire::test(OkupansiKalender::class)
            ->assertSet('startDate', '2026-10-09')
            ->assertSet('days', 14)
            ->call('next')->assertSet('startDate', '2026-10-23')
            ->call('previous')->call('previous')->assertSet('startDate', '2026-09-25')
            ->call('goToToday')->assertSet('startDate', '2026-10-09')
            ->call('setDays', 7)->assertSet('days', 7)
            ->call('next')->assertSet('startDate', '2026-10-16')
            ->call('setDays', 99)->assertSet('days', 7);
    }

    /**
     * The modal body is built by the page and rendered into a Livewire partial that the test harness does not
     * capture, so the same data and view are rendered here after confirming the action really mounts.
     */
    private function modalHtml(string $role, int $unitId, string $date): string
    {
        $this->actingAs($this->userWithRole($role));

        $page = Livewire::test(OkupansiKalender::class)
            ->mountAction('booking', ['unit' => $unitId, 'date' => $date])
            ->assertActionMounted('booking');

        $detail = new \ReflectionMethod($page->instance(), 'detail');

        return view('filament.pages.partials.okupansi-detail', $detail->invoke($page->instance(), ['unit' => $unitId, 'date' => $date]))->render();
    }

    public function test_detail_modal_shows_summary_and_links_to_edit_for_permitted_user(): void
    {
        $html = $this->modalHtml(User::ROLE_FRONT_OFFICE, $this->unit->id, '2026-10-09');

        $this->assertStringContainsString('RCM-OKUP01', $html);
        $this->assertStringContainsString('Budi S.', $html);
        $this->assertStringContainsString('Buka booking', $html);
        $this->assertStringContainsString('/admin/bookings/'.$this->booking->id.'/edit', $html);
    }

    public function test_detail_modal_for_a_block_shows_the_reason_and_period(): void
    {
        UnitBlock::create([
            'unit_id' => $this->unit->id, 'reason' => 'Perbaikan atap',
            'start_date' => '2026-10-12', 'end_date' => '2026-10-13',
        ]);

        $html = $this->modalHtml(User::ROLE_OWNER, $this->unit->id, '2026-10-12');

        $this->assertStringContainsString('Perbaikan atap', $html);
        $this->assertStringContainsString('13 Oktober 2026', $html);
        $this->assertStringNotContainsString('Buka booking', $html);
    }

    public function test_page_and_modal_never_expose_raw_guest_contact_or_full_name(): void
    {
        foreach ([User::ROLE_OWNER, User::ROLE_FRONT_OFFICE] as $role) {
            $html = $this->modalHtml($role, $this->unit->id, '2026-10-09');

            Livewire::test(OkupansiKalender::class)
                ->assertSee('Budi S.')
                ->assertDontSee('Santoso')
                ->assertDontSee('081234567890')
                ->assertDontSee('budi.rahasia@example.test');

            $this->assertStringNotContainsString('Santoso', $html);
            $this->assertStringNotContainsString('081234567890', $html);
            $this->assertStringNotContainsString('budi.rahasia@example.test', $html);
        }
    }

    public function test_detail_refuses_a_malformed_date(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_OWNER));
        $page = Livewire::test(OkupansiKalender::class)->instance();

        $this->expectException(NotFoundHttpException::class);

        (new \ReflectionMethod($page, 'detail'))->invoke($page, ['unit' => $this->unit->id, 'date' => 'bukan-tanggal']);
    }
}
