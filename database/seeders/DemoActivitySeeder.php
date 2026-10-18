<?php

namespace Database\Seeders;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Enums\CleaningLogStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Exceptions\UnitUnavailableException;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\CleaningLog;
use App\Models\Customer;
use App\Models\DiningSpot;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationCriteria;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use App\Services\BookingService;
use App\Services\OrderService;
use App\Services\UnitNightLedger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Activity for a stakeholder demo: three months of bookings, payments, kitchen orders, attendance,
 * cleaning logs and evaluations, so dashboards, reports and lists have something to show.
 * Prices go through BookingService and OrderService, never typed here. Run it on its own:
 * php artisan db:seed --class=DemoActivitySeeder. Guests use @contoh.test addresses so the
 * data is recognisable and the seeder refuses to run twice.
 */
class DemoActivitySeeder extends Seeder
{
    private const EMAIL_DOMAIN = 'contoh.test';

    private const BOOKING_COUNT = 45;

    private const ORDER_COUNT = 60;

    /** @var list<array{0: string, 1: string}> */
    private const GUESTS = [
        ['Rina Kusuma', '081211110001'], ['Andi Pratama', '081211110002'], ['Siti Rahmawati', '081211110003'],
        ['Budi Santoso', '081211110004'], ['Dewi Lestari', '081211110005'], ['Fajar Nugroho', '081211110006'],
        ['Maya Anggraini', '081211110007'], ['Rizky Ramadhan', '081211110008'], ['Intan Permata', '081211110009'],
        ['Yoga Saputra', '081211110010'], ['Nadia Putri', '081211110011'], ['Hendra Wijaya', '081211110012'],
        ['Laras Ayuningtyas', '081211110013'], ['Teguh Firmansyah', '081211110014'], ['Citra Maharani', '081211110015'],
        ['Dimas Aditya', '081211110016'], ['Wulan Sari', '081211110017'], ['Agus Setiawan', '081211110018'],
    ];

    /** @var list<array{0: string, 1: string}> */
    private const STAFF = [
        ['Ujang Supriatna', 'Petugas kebersihan'], ['Asep Hidayat', 'Petugas kebersihan'], ['Neneng Rohaeti', 'Juru masak'],
        ['Dadang Kurnia', 'Juru masak'], ['Euis Komalasari', 'Front office'], ['Cecep Mulyana', 'Keamanan'],
    ];

    public function __construct(
        private readonly BookingService $bookings,
        private readonly OrderService $orders,
        private readonly UnitNightLedger $ledger,
    ) {}

    public function run(): void
    {
        if (Customer::where('email', 'like', '%@'.self::EMAIL_DOMAIN)->exists()) {
            $this->command->warn('Data demo sudah ada, seeder dilewati.');

            return;
        }

        mt_srand(2026);
        $today = CarbonImmutable::today();
        $owner = User::role(User::ROLE_OWNER)->first();

        $customers = $this->customers();
        $bookings = $this->seedBookings($customers, $today, $owner?->id);
        $this->seedOrders($bookings, $today);
        $employees = $this->seedStaff();
        $this->seedAttendance($employees, $today);
        $this->seedCleaning($employees, $today);
        $this->seedEvaluations($employees, $today, $owner?->id);

        $this->command->info("Data demo dibuat: {$bookings->count()} booking, ".self::ORDER_COUNT.' pesanan, '.count(self::STAFF).' karyawan.');
    }

    /** @return Collection<int, Customer> */
    private function customers(): Collection
    {
        return collect(self::GUESTS)->map(fn (array $guest, int $i) => Customer::create([
            'name' => $guest[0],
            'phone' => $guest[1],
            'email' => 'tamu'.($i + 1).'@'.self::EMAIL_DOMAIN,
        ]));
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @return Collection<int, Booking>
     */
    private function seedBookings(Collection $customers, CarbonImmutable $today, ?int $ownerId): Collection
    {
        $created = collect();

        for ($i = 0; $i < self::BOOKING_COUNT; $i++) {
            // Spread from 75 days ago to 21 days ahead, weighted towards the past so reports have history.
            $checkIn = $today->addDays(mt_rand(-75, 21));
            $nights = mt_rand(1, 3);
            $booking = $this->tryBooking($customers->random(), $checkIn, $nights);

            if ($booking === null) {
                continue;
            }

            $this->settle($booking, $checkIn, $nights, $today, $ownerId);
            $created->push($booking->refresh());
        }

        return $created;
    }

    private function tryBooking(Customer $customer, CarbonImmutable $checkIn, int $nights): ?Booking
    {
        $checkOut = $checkIn->addDays($nights);
        $unit = Unit::where('status', Unit::STATUS_ACTIVE)
            ->freeBetween($checkIn->toDateString(), $checkOut->toDateString())
            ->inRandomOrder()
            ->first();

        if ($unit === null) {
            return null;
        }

        try {
            return $this->bookings->createBooking($customer->id, $checkIn->toDateString(), $checkOut->toDateString(), mt_rand(2, 4), [$unit->id]);
        } catch (UnitUnavailableException) {
            return null;
        }
    }

    /**
     * Moves a fresh booking to the status its dates imply and records the money that status needs.
     */
    private function settle(Booking $booking, CarbonImmutable $checkIn, int $nights, CarbonImmutable $today, ?int $ownerId): void
    {
        $checkOut = $checkIn->addDays($nights);
        $bookedAt = $checkIn->subDays(mt_rand(3, 20))->setTime(mt_rand(8, 21), mt_rand(0, 59));
        $booking->forceFill(['created_at' => min($bookedAt, now())])->save();
        $roll = mt_rand(1, 100);

        if ($checkIn->isFuture() && $roll <= 15) {
            $booking->update(['hold_expires_at' => now()->addMinutes(mt_rand(5, 14))]);

            return;
        }

        if ($roll > 96) {
            $this->cancelWithRefund($booking, $ownerId, $checkIn->isPast());

            return;
        }

        $status = match (true) {
            $checkOut->lte($today) => BookingStatus::CheckedOut,
            $checkIn->lte($today) => BookingStatus::CheckedIn,
            default => BookingStatus::Paid,
        };

        $this->pay($booking, $booking->total, $bookedAt);
        $booking->update(['status' => $status, 'paid_amount' => $booking->total, 'hold_expires_at' => null]);
    }

    private function pay(Booking $booking, int $amount, CarbonImmutable $paidAt): void
    {
        $method = [PaymentMethod::Gateway, PaymentMethod::Gateway, PaymentMethod::Transfer, PaymentMethod::Cash][mt_rand(0, 3)];

        $booking->payments()->create([
            'direction' => PaymentDirection::In,
            'method' => $method,
            'amount' => $amount,
            'status' => PaymentStatus::Paid,
            'paid_at' => min($paidAt->addHours(mt_rand(0, 6)), now()),
            'gateway_ref' => $method === PaymentMethod::Gateway ? $booking->code.'-1' : null,
        ]);
    }

    private function cancelWithRefund(Booking $booking, ?int $ownerId, bool $done): void
    {
        $this->pay($booking, $booking->total, CarbonImmutable::instance($booking->created_at));
        $booking->update(['paid_amount' => $booking->total, 'hold_expires_at' => null]);

        Refund::create([
            'booking_id' => $booking->id,
            'amount' => $booking->total,
            'reason' => 'Tamu membatalkan lebih dari 7 hari sebelum check-in.',
            'status' => $done ? RefundStatus::Paid : RefundStatus::Requested,
            'approved_by' => $done ? $ownerId : null,
        ]);

        if ($done) {
            $booking->update(['status' => BookingStatus::Refunded, 'cancelled_at' => now()]);
            $this->ledger->release([$booking->id]);
            $booking->payments()->create([
                'direction' => PaymentDirection::Out,
                'method' => PaymentMethod::Transfer,
                'amount' => $booking->total,
                'status' => PaymentStatus::Paid,
                'paid_at' => now()->subDays(mt_rand(1, 10)),
            ]);

            return;
        }

        $booking->update(['status' => BookingStatus::Paid]);
    }

    /** @param  Collection<int, Booking>  $bookings */
    private function seedOrders(Collection $bookings, CarbonImmutable $today): void
    {
        $menu = MenuItem::where('is_available', true)->pluck('id');
        $spots = DiningSpot::where('type', DiningSpot::TYPE_TABLE)->pluck('id');

        if ($menu->isEmpty()) {
            return;
        }

        $stays = $bookings->whereIn('status', [BookingStatus::CheckedIn, BookingStatus::CheckedOut])->values();

        for ($i = 0; $i < self::ORDER_COUNT; $i++) {
            $items = collect(range(1, mt_rand(1, 3)))
                ->map(fn () => ['menu_item_id' => $menu->random(), 'qty' => mt_rand(1, 3)])
                ->unique('menu_item_id')->values()->all();
            $placedAt = $today->subDays(mt_rand(0, 60))->setTime(mt_rand(7, 21), mt_rand(0, 59));
            $billed = $stays->isNotEmpty() && mt_rand(1, 100) <= 30;
            $booking = $billed ? $stays->random() : null;

            $order = $this->orders->createOrder(
                $billed ? Order::SOURCE_QR : [Order::SOURCE_QR, Order::SOURCE_WALKIN][mt_rand(0, 1)],
                $items,
                $booking?->id,
                $billed || $spots->isEmpty() ? null : $spots->random(),
                null,
                self::GUESTS[mt_rand(0, count(self::GUESTS) - 1)][0],
                null,
                $billed,
            );

            // Older orders are finished; today's orders stay spread over the kitchen board.
            $status = $placedAt->isToday()
                ? [OrderStatus::Baru, OrderStatus::Diproses, OrderStatus::Siap][mt_rand(0, 2)]
                : OrderStatus::Selesai;

            $order->forceFill([
                'status' => $status->value,
                'created_at' => min($placedAt, now()),
                'payment_status' => $billed || $status !== OrderStatus::Selesai ? $order->payment_status : Order::PAYMENT_PAID,
            ])->save();
        }
    }

    /** @return Collection<int, Employee> */
    private function seedStaff(): Collection
    {
        $morning = Shift::firstOrCreate(['name' => 'Pagi'], ['start_time' => '07:00', 'end_time' => '15:00', 'late_tolerance_minutes' => 10]);
        $evening = Shift::firstOrCreate(['name' => 'Sore'], ['start_time' => '14:00', 'end_time' => '22:00', 'late_tolerance_minutes' => 10]);

        return collect(self::STAFF)->map(function (array $staff, int $i) use ($morning, $evening): Employee {
            $employee = Employee::create([
                'name' => $staff[0],
                'position' => $staff[1],
                'shift_id' => $i % 2 === 0 ? $morning->id : $evening->id,
            ]);
            // Joined before the attendance window, so the recap counts every seeded day.
            $employee->forceFill(['created_at' => now()->subMonths(4)])->save();

            return $employee;
        });
    }

    /** @param  Collection<int, Employee>  $employees */
    private function seedAttendance(Collection $employees, CarbonImmutable $today): void
    {
        $off = config('attendance.off_weekdays');

        foreach ($employees as $employee) {
            $start = $employee->shift->start_time ?? '07:00';

            for ($day = $today->subDays(45); $day->lt($today); $day = $day->addDay()) {
                if (in_array($day->dayOfWeekIso, $off, true)) {
                    continue;
                }

                $roll = mt_rand(1, 100);
                $status = match (true) {
                    $roll <= 82 => AttendanceStatus::Present,
                    $roll <= 91 => AttendanceStatus::Late,
                    $roll <= 95 => AttendanceStatus::Excused,
                    $roll <= 98 => AttendanceStatus::Sick,
                    default => AttendanceStatus::Absent,
                };
                $works = in_array($status, [AttendanceStatus::Present, AttendanceStatus::Late], true);
                $in = CarbonImmutable::parse($day->toDateString().' '.$start)->addMinutes($status === AttendanceStatus::Late ? mt_rand(15, 45) : mt_rand(-10, 5));

                Attendance::create([
                    'employee_id' => $employee->id,
                    'date' => $day->toDateString(),
                    'clock_in' => $works ? $in : null,
                    'clock_out' => $works ? $in->addHours(8)->addMinutes(mt_rand(0, 20)) : null,
                    'status' => $status,
                    'source' => AttendanceSource::Manual,
                    'note' => $works ? null : 'Dicatat admin.',
                ]);
            }
        }
    }

    /** @param  Collection<int, Employee>  $employees */
    private function seedCleaning(Collection $employees, CarbonImmutable $today): void
    {
        $cleaners = $employees->where('position', 'Petugas kebersihan')->values();
        $units = Unit::pluck('id');
        $photos = glob(database_path('seeders/images/tents/*.webp')) ?: [];

        if ($cleaners->isEmpty() || $units->isEmpty() || $photos === []) {
            return;
        }

        // Proof photos live on the private disk, like the ones staff upload.
        $stored = collect($photos)->take(6)->values()->map(function (string $source, int $i): string {
            $path = 'cleaning_logs/demo-'.($i + 1).'.webp';
            Storage::disk('local')->put($path, (string) file_get_contents($source));

            return $path;
        });

        for ($i = 0; $i < 40; $i++) {
            $cleanedAt = $today->subDays(mt_rand(0, 30))->setTime(mt_rand(11, 15), mt_rand(0, 59));

            CleaningLog::create([
                'unit_id' => $units->random(),
                'employee_id' => $cleaners->random()->id,
                'cleaned_at' => $cleanedAt,
                'status' => $cleanedAt->isToday() ? CleaningLogStatus::Pending : CleaningLogStatus::Approved,
                'photo_path' => $stored->random(),
                'notes' => mt_rand(1, 5) === 1 ? 'Ganti sprei dan isi ulang air minum.' : null,
            ]);
        }
    }

    /** @param  Collection<int, Employee>  $employees */
    private function seedEvaluations(Collection $employees, CarbonImmutable $today, ?int $ownerId): void
    {
        $criteria = EvaluationCriteria::where('is_active', true)->get();

        if ($criteria->isEmpty()) {
            return;
        }

        $period = $today->subMonthNoOverflow()->format('Y-m');

        foreach ($employees as $employee) {
            $scores = $criteria->mapWithKeys(fn (EvaluationCriteria $c) => [$c->id => mt_rand(70, 98)]);
            $total = (int) round($criteria->sum(fn (EvaluationCriteria $c) => $scores[$c->id] * $c->weight) / max(1, $criteria->sum('weight')));

            $evaluation = Evaluation::create([
                'employee_id' => $employee->id,
                'period' => $period,
                'total_score' => $total,
                'notes' => null,
                'evaluated_by' => $ownerId,
            ]);

            foreach ($scores as $criteriaId => $score) {
                $evaluation->scores()->create(['criteria_id' => $criteriaId, 'score' => $score]);
            }
        }
    }
}
