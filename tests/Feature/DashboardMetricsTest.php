<?php

namespace Tests\Feature;

use App\Filament\Widgets\DashboardStats;
use App\Models\Booking;
use App\Models\Order;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    private DashboardMetricsService $metrics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metrics = new DashboardMetricsService;
    }

    public function test_empty_database_yields_zeroes(): void
    {
        $this->assertSame(0, $this->metrics->checkInsToday());
        $this->assertSame(0, $this->metrics->checkOutsToday());
        $this->assertSame(0, $this->metrics->occupancyPercent());
        $this->assertSame(0, $this->metrics->activeFoodOrderCount());
        $this->assertSame(['camping' => 0, 'food' => 0], $this->metrics->monthlyRevenue());
        $this->assertSame(array_fill(0, 14, 0), $this->metrics->bookingsPerDay()['data']);
        $this->assertSame([], $this->metrics->occupancyByUnitType()['labels']);
    }

    public function test_arrivals_departures_and_occupancy_are_counted(): void
    {
        $today = $this->metrics->today();
        $customerId = $this->makeCustomer();
        $dome = $this->makeUnitType('Dome');
        $tent = $this->makeUnitType('Tenda');

        $domeA = $this->makeUnit($dome, 'D-1');
        $this->makeUnit($dome, 'D-2');
        $tentA = $this->makeUnit($tent, 'T-1');
        $this->makeUnit($tent, 'T-2', 'maintenance');

        $arriving = $this->makeBooking($customerId, 'B-1', 'paid', $today, $today->addDays(2));
        $this->attachUnit($arriving, $domeA);

        $staying = $this->makeBooking($customerId, 'B-2', 'checked_in', $today->subDay(), $today->addDay());
        $this->attachUnit($staying, $tentA);

        $leaving = $this->makeBooking($customerId, 'B-3', 'checked_out', $today->subDays(2), $today);
        $this->attachUnit($leaving, $domeA);

        $unpaid = $this->makeBooking($customerId, 'B-4', 'pending_payment', $today, $today->addDay());
        $this->attachUnit($unpaid, $domeA);

        $this->assertSame(1, $this->metrics->checkInsToday());
        $this->assertSame(1, $this->metrics->checkOutsToday());
        $this->assertSame(3, $this->metrics->activeUnitCount());
        $this->assertSame(2, $this->metrics->occupiedUnitCount());
        $this->assertSame(67, $this->metrics->occupancyPercent());

        $byType = $this->metrics->occupancyByUnitType();
        $this->assertSame(['Dome', 'Tenda'], $byType['labels']);
        $this->assertSame([1, 1], $byType['occupied']);
        $this->assertSame([1, 0], $byType['available']);
    }

    public function test_revenue_splits_camping_and_food_for_current_month(): void
    {
        $today = $this->metrics->today();
        $customerId = $this->makeCustomer();
        $booking = $this->makeBooking($customerId, 'B-1', 'paid', $today, $today->addDay());
        $orderId = DB::table('orders')->insertGetId([
            'code' => 'O-1', 'source' => 'walkin', 'status' => 'baru', 'total' => 50000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('orders')->insert([
            'code' => 'O-2', 'source' => 'walkin', 'status' => 'selesai', 'total' => 10000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->makePayment(Booking::class, $booking, 750000, 'paid', $today->addHours(10));
        $this->makePayment(Booking::class, $booking, 100000, 'pending', $today->addHours(10));
        $this->makePayment(Order::class, $orderId, 50000, 'paid', $today->addHours(11));
        $this->makePayment(Order::class, $orderId, 99000, 'paid', $today->startOfMonth()->subDay());

        $this->assertSame(['camping' => 750000, 'food' => 50000], $this->metrics->monthlyRevenue());
        $this->assertSame(1, $this->metrics->activeFoodOrderCount());
    }

    public function test_active_food_orders_skip_preorders_of_dead_bookings(): void
    {
        $today = $this->metrics->today();
        $customerId = $this->makeCustomer();
        $paid = $this->makeBooking($customerId, 'B-1', 'paid', $today, $today->addDay());
        $expired = $this->makeBooking($customerId, 'B-2', 'expired', $today, $today->addDay());
        $cancelled = $this->makeBooking($customerId, 'B-3', 'cancelled', $today, $today->addDay());

        foreach ([[$paid, 'preorder'], [$expired, 'preorder'], [$cancelled, 'preorder'], [null, 'qr']] as $i => [$bookingId, $source]) {
            DB::table('orders')->insert([
                'code' => "O-{$i}", 'source' => $source, 'booking_id' => $bookingId, 'status' => 'baru', 'total' => 10000,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->assertSame(2, $this->metrics->activeFoodOrderCount());
    }

    public function test_preorders_awaiting_payment_count_only_live_pending_holds(): void
    {
        $today = $this->metrics->today();
        $customerId = $this->makeCustomer();
        $livePending = $this->makeBooking($customerId, 'B-1', 'pending_payment', $today, $today->addDay());
        $lapsedPending = $this->makeBooking($customerId, 'B-2', 'pending_payment', $today, $today->addDay());
        DB::table('bookings')->where('id', $livePending)->update(['hold_expires_at' => now()->addMinutes(30)]);
        DB::table('bookings')->where('id', $lapsedPending)->update(['hold_expires_at' => now()->subMinute()]);

        $others = [
            $this->makeBooking($customerId, 'B-3', 'expired', $today, $today->addDay()),
            $this->makeBooking($customerId, 'B-4', 'cancelled', $today, $today->addDay()),
            $this->makeBooking($customerId, 'B-5', 'paid', $today, $today->addDay()),
        ];

        $rows = [[$livePending, 'preorder'], [$lapsedPending, 'preorder'], [$livePending, 'qr'], [null, 'walkin']];
        foreach ([...array_map(fn ($id) => [$id, 'preorder'], $others), ...$rows] as $i => [$bookingId, $source]) {
            DB::table('orders')->insert([
                'code' => "O-{$i}", 'source' => $source, 'booking_id' => $bookingId, 'status' => 'baru', 'total' => 10000,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->assertSame(1, $this->metrics->preorderAwaitingPaymentCount());
        $this->assertSame(1, Order::query()->kitchenRelevant()->where('source', 'preorder')->count());
    }

    public function test_dashboard_stats_show_awaiting_preorders_only_to_permitted_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs($this->makeUser(User::ROLE_OWNER));
        Livewire::test(DashboardStats::class)
            ->assertSee('Pre-order Menunggu Pembayaran')
            ->assertSee('Belum masuk antrean dapur');

        $this->actingAs($this->makeUser(User::ROLE_FRONT_OFFICE));
        Livewire::test(DashboardStats::class)->assertSee('Pre-order Menunggu Pembayaran');

        $this->actingAs($this->makeUser(User::ROLE_CASHIER));
        $this->assertFalse(DashboardStats::canView());
    }

    public function test_refund_out_reduces_camping_revenue_without_touching_food(): void
    {
        $today = $this->metrics->today();
        $booking = $this->makeBooking($this->makeCustomer(), 'B-1', 'paid', $today, $today->addDay());
        $orderId = DB::table('orders')->insertGetId([
            'code' => 'O-1', 'source' => 'walkin', 'status' => 'baru', 'total' => 50000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->makePayment(Booking::class, $booking, 750000, 'paid', $today->addHours(10));
        $this->makePayment(Booking::class, $booking, 500000, 'paid', $today->addHours(12), 'out');
        $this->makePayment(Booking::class, $booking, 200000, 'pending', $today->addHours(12), 'out');
        $this->makePayment(Order::class, $orderId, 50000, 'paid', $today->addHours(11));
        $this->makePayment(Booking::class, $booking, 300000, 'paid', $today->startOfMonth()->subDay(), 'out');

        $this->assertSame(['camping' => 250000, 'food' => 50000], $this->metrics->monthlyRevenue());
    }

    public function test_bookings_per_day_covers_last_fourteen_days(): void
    {
        $today = $this->metrics->today();
        $customerId = $this->makeCustomer();

        $this->makeBooking($customerId, 'B-1', 'paid', $today, $today->addDay(), $today->addHours(9));
        $this->makeBooking($customerId, 'B-2', 'paid', $today, $today->addDay(), $today->addHours(12));
        $this->makeBooking($customerId, 'B-3', 'paid', $today, $today->addDay(), $today->subDays(13)->addHours(9));
        $this->makeBooking($customerId, 'B-4', 'paid', $today, $today->addDay(), $today->subDays(14)->addHours(9));

        $series = $this->metrics->bookingsPerDay();

        $this->assertCount(14, $series['data']);
        $this->assertSame(1, $series['data'][0]);
        $this->assertSame(2, $series['data'][13]);
        $this->assertSame(3, array_sum($series['data']));
    }

    private function makeUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.Str::random(6).'@example.test',
            'password' => Str::random(24),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCustomer(): int
    {
        return DB::table('customers')->insertGetId([
            'name' => 'Tamu Uji', 'phone' => '0800000000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeUnitType(string $name): int
    {
        return DB::table('unit_types')->insertGetId([
            'name' => $name, 'slug' => strtolower($name), 'capacity' => 4,
            'base_price_weekday' => 100000, 'base_price_weekend' => 150000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeUnit(int $typeId, string $code, string $status = 'active'): int
    {
        return DB::table('units')->insertGetId([
            'unit_type_id' => $typeId, 'code' => $code, 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeBooking(int $customerId, string $code, string $status, $checkIn, $checkOut, $createdAt = null): int
    {
        return DB::table('bookings')->insertGetId([
            'code' => $code, 'customer_id' => $customerId, 'status' => $status,
            'check_in' => $checkIn->toDateString(), 'check_out' => $checkOut->toDateString(),
            'guests' => 2, 'subtotal' => 100000, 'total' => 100000,
            'created_at' => ($createdAt ?? now())->utc(), 'updated_at' => now(),
        ]);
    }

    private function attachUnit(int $bookingId, int $unitId): void
    {
        $booking = DB::table('bookings')->find($bookingId);

        DB::table('booking_units')->insert([
            'booking_id' => $bookingId, 'unit_id' => $unitId,
            'check_in' => $booking->check_in, 'check_out' => $booking->check_out,
            'price_per_night' => 100000, 'nights' => 1, 'subtotal' => 100000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makePayment(string $type, int $id, int $amount, string $status, $paidAt, string $direction = 'in'): void
    {
        DB::table('payments')->insert([
            'payable_type' => (new $type)->getMorphClass(), 'payable_id' => $id, 'direction' => $direction, 'method' => 'transfer',
            'amount' => $amount, 'status' => $status, 'paid_at' => $paidAt->utc(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
