<?php

namespace Tests\Support;

use App\Models\Booking;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Raw inserts with explicit local dates so report figures can be asserted to the rupiah.
 */
trait ReportFixtures
{
    private function makeUser(string $role): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.Str::random(6).'@example.test',
            'password' => Str::random(24),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function customer(string $name = 'Andi Pratama'): int
    {
        return DB::table('customers')->insertGetId([
            'name' => $name, 'phone' => '081200000001', 'email' => 'tamu@example.test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function unitType(string $name): int
    {
        return DB::table('unit_types')->insertGetId([
            'name' => $name, 'slug' => Str::slug($name), 'capacity' => 4,
            'base_price_weekday' => 100000, 'base_price_weekend' => 150000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function unit(int $typeId, string $code, string $status = 'active'): int
    {
        return DB::table('units')->insertGetId([
            'unit_type_id' => $typeId, 'code' => $code, 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function block(int $unitId, string $from, string $to): void
    {
        DB::table('unit_blocks')->insert([
            'unit_id' => $unitId, 'start_date' => $from, 'end_date' => $to,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function booking(int $customerId, string $code, string $status, string $checkIn, string $checkOut, int $total = 0, int $paid = 0, ?int $unitId = null): int
    {
        $id = DB::table('bookings')->insertGetId([
            'code' => $code, 'customer_id' => $customerId, 'status' => $status,
            'check_in' => $checkIn, 'check_out' => $checkOut, 'guests' => 2,
            'subtotal' => $total, 'total' => $total, 'paid_amount' => $paid,
            'created_at' => $checkIn.' 08:00:00', 'updated_at' => now(),
        ]);

        if ($unitId !== null) {
            DB::table('booking_units')->insert([
                'booking_id' => $id, 'unit_id' => $unitId, 'check_in' => $checkIn, 'check_out' => $checkOut,
                'price_per_night' => 100000, 'nights' => 1, 'subtotal' => $total,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $id;
    }

    private function payment(string $type, int $id, int $amount, string $paidAt, string $direction = 'in', string $method = 'transfer', string $status = 'paid'): void
    {
        DB::table('payments')->insert([
            'payable_type' => (new $type)->getMorphClass(), 'payable_id' => $id, 'direction' => $direction,
            'method' => $method, 'amount' => $amount, 'status' => $status, 'paid_at' => $paidAt,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function menuItem(string $name, int $price): int
    {
        $categoryId = DB::table('menu_categories')->value('id') ?? DB::table('menu_categories')->insertGetId([
            'name' => 'Makanan', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId, 'name' => $name, 'price' => $price,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  array<int, array{0: int, 1: int}>  $lines menu item id and quantity */
    private function order(string $code, string $paymentStatus, string $createdAt, array $lines): int
    {
        $total = 0;
        foreach ($lines as [$itemId, $qty]) {
            $total += DB::table('menu_items')->where('id', $itemId)->value('price') * $qty;
        }

        $orderId = DB::table('orders')->insertGetId([
            'code' => $code, 'source' => Order::SOURCE_WALKIN, 'status' => 'selesai', 'total' => $total,
            'payment_status' => $paymentStatus, 'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);

        foreach ($lines as [$itemId, $qty]) {
            DB::table('order_items')->insert([
                'order_id' => $orderId, 'menu_item_id' => $itemId, 'qty' => $qty,
                'price' => DB::table('menu_items')->where('id', $itemId)->value('price'),
                'created_at' => $createdAt, 'updated_at' => $createdAt,
            ]);
        }

        return $orderId;
    }

    private function refund(int $bookingId, int $amount, string $status, string $createdAt, ?string $reason = null): void
    {
        DB::table('refunds')->insert([
            'booking_id' => $bookingId, 'amount' => $amount, 'status' => $status, 'reason' => $reason,
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
    }

    /**
     * October 2026 dataset used by the report tests. Callers freeze the clock at 2026-10-15 first.
     *
     * @return array{bookings: array<string, int>}
     */
    private function seedOctober(): array
    {
        $andi = $this->customer('Andi Pratama');
        $sari = $this->customer('Sari Utami');

        $dome = $this->unitType('Dome');
        $tent = $this->unitType('Tenda');
        $d1 = $this->unit($dome, 'D-1');
        $d2 = $this->unit($dome, 'D-2');
        $d3 = $this->unit($dome, 'D-3', 'maintenance');
        $t1 = $this->unit($tent, 'T-1');
        $this->block($t1, '2026-10-10', '2026-10-12');

        $bookings = [
            'B1' => $this->booking($andi, 'B-1', 'paid', '2026-10-01', '2026-10-04', 900000, 900000, $d1),
            'B2' => $this->booking($sari, 'B-2', 'checked_out', '2026-10-28', '2026-11-03', 1800000, 1800000, $d2),
            'B3' => $this->booking($andi, 'B-3', 'pending_payment', '2026-10-10', '2026-10-12', 300000, 0, $d1),
            'B4' => $this->booking($sari, 'B-4', 'cancelled', '2026-10-20', '2026-10-22', 400000, 0, $t1),
            'B5' => $this->booking($andi, 'B-5', 'checked_in', '2026-09-29', '2026-10-02', 450000, 450000, $t1),
            'B6' => $this->booking($sari, 'B-6', 'paid', '2026-10-05', '2026-10-07', 200000, 200000, $d3),
        ];

        $this->payment(Booking::class, $bookings['B1'], 900000, '2026-10-01 09:00:00', 'in', 'gateway');
        $this->payment(Booking::class, $bookings['B2'], 1800000, '2026-10-02 10:00:00', 'in', 'transfer');
        $this->payment(Booking::class, $bookings['B6'], 200000, '2026-10-03 11:00:00', 'in', 'cash');
        $this->payment(Booking::class, $bookings['B6'], 50000, '2026-10-04 12:00:00', 'out', 'transfer');
        $this->payment(Booking::class, $bookings['B1'], 70000, '2026-10-05 12:00:00', 'in', 'cash', 'pending');

        $nasi = $this->menuItem('Nasi Goreng', 25000);
        $kopi = $this->menuItem('Kopi Hitam', 15000);
        $mie = $this->menuItem('Mie Rebus', 20000);
        $o1 = $this->order('O-1', 'paid', '2026-10-02 12:00:00', [[$nasi, 3], [$kopi, 2]]);
        $o2 = $this->order('O-2', 'paid', '2026-10-06 19:00:00', [[$nasi, 1], [$mie, 3]]);
        $this->order('O-3', 'unpaid', '2026-10-07 19:00:00', [[$mie, 10]]);
        $this->order('O-4', 'paid', '2026-09-30 19:00:00', [[$kopi, 9]]);
        $this->payment(Order::class, $o1, 105000, '2026-10-02 12:30:00', 'in', 'cash');
        $this->payment(Order::class, $o2, 85000, '2026-10-06 19:30:00', 'in', 'gateway');

        $this->refund($bookings['B6'], 50000, 'paid', '2026-10-03 13:00:00', 'Ganti jadwal');
        $this->refund($bookings['B4'], 100000, 'requested', '2026-10-21 08:00:00');
        $this->refund($bookings['B3'], 30000, 'rejected', '2026-10-12 08:00:00', 'Di luar kebijakan');
        $this->refund($bookings['B5'], 999000, 'paid', '2026-09-30 08:00:00');

        return ['bookings' => $bookings];
    }
}
