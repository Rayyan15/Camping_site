<?php

namespace App\Services\Reports;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Enums\ReportType;
use App\Enums\ReportValueType as Type;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /** Listing reports stop here so a huge period cannot exhaust memory in the PDF or spreadsheet. */
    public const MAX_ROWS = 2000;

    public const TOP_MENU_LIMIT = 50;

    /** Booking statuses whose nights count as sold. */
    private const SOLD_STATUSES = [BookingStatus::Paid, BookingStatus::CheckedIn, BookingStatus::CheckedOut];

    public function build(ReportType $type, ReportPeriod $period): ReportTable
    {
        return match ($type) {
            ReportType::Booking => $this->bookings($period),
            ReportType::Occupancy => $this->occupancy($period),
            ReportType::TopMenu => $this->topMenu($period),
            ReportType::Refund => $this->refunds($period),
            ReportType::PaymentMethod => $this->paymentsByMethod($period),
            ReportType::Revenue => $this->revenueByCategory($period),
        };
    }

    public function bookings(ReportPeriod $period): ReportTable
    {
        // A refund filed this period puts its booking here too, so the refund report and this one always agree.
        $query = Booking::query()->where(fn ($inPeriod) => $inPeriod
            ->whereBetween('check_in', [$period->from->toDateString(), $period->to->toDateString()])
            ->orWhereHas('refunds', fn ($refunds) => $refunds->where('created_at', '>=', $period->from->toDateTimeString())->where('created_at', '<', $period->untilExclusive()->toDateTimeString())));

        // Lapsed holds never took money, so they are only counted in the summary, never listed beside paid stays.
        $bookings = (clone $query)->where('status', '!=', BookingStatus::Expired->value)->with(['customer:id,name', 'addons', 'orders'])->orderBy('check_in')->orderBy('id')->limit(self::MAX_ROWS + 1)->get();
        $truncated = $bookings->count() > self::MAX_ROWS;

        // The owner reads the tent apart from extras (ATV, firewood) and food ordered with the booking.
        $rows = $bookings->take(self::MAX_ROWS)->map(function (Booking $booking) {
            $addons = (int) $booking->addons->sum('subtotal');
            $food = $this->bookingFoodTotal($booking);

            return [
                $booking->code,
                $booking->customer->name ?? '-',
                $booking->check_in->format('d/m/Y'),
                $booking->check_out->format('d/m/Y'),
                $booking->status->getLabel(),
                $booking->total - $addons - $food,
                $addons,
                $food,
                $booking->total,
                $booking->paid_amount,
            ];
        })->all();

        $perStatus = (clone $query)->toBase()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $expired = (int) ($perStatus[BookingStatus::Expired->value] ?? 0);
        $summary = [['label' => 'Jumlah booking', 'value' => (int) $perStatus->sum() - $expired, 'type' => Type::Count]];
        foreach (BookingStatus::cases() as $status) {
            if ($status !== BookingStatus::Expired && $perStatus->has($status->value)) {
                $summary[] = ['label' => $status->getLabel(), 'value' => (int) $perStatus[$status->value], 'type' => Type::Count];
            }
        }
        // Totals cover sold stays only, so a cancelled or unpaid booking never inflates the tent figure.
        $soldRows = array_values(array_filter($rows, fn (array $row, int $index) => in_array($bookings[$index]->status, self::SOLD_STATUSES, true), ARRAY_FILTER_USE_BOTH));
        foreach ([5 => 'Sewa tenda, lunas (Rp)', 6 => 'Tambahan, lunas (Rp)', 7 => 'Makanan, lunas (Rp)'] as $index => $label) {
            $summary[] = ['label' => $label, 'value' => (int) array_sum(array_column($soldRows, $index)), 'type' => Type::Money];
        }
        $summary[] = ['label' => 'Total dibayar (Rp)', 'value' => (int) (clone $query)->where('status', '!=', BookingStatus::Expired->value)->sum('paid_amount'), 'type' => Type::Money];
        if ($expired > 0) {
            $summary[] = ['label' => 'Kedaluwarsa, tidak ditampilkan', 'value' => $expired, 'type' => Type::Count];
        }

        return new ReportTable(
            ReportType::Booking,
            $period,
            [
                $this->column('Kode', Type::Text),
                $this->column('Pelanggan', Type::Text),
                $this->column('Check-in', Type::Text),
                $this->column('Check-out', Type::Text),
                $this->column('Status', Type::Text),
                $this->column('Sewa tenda (Rp)', Type::Money),
                $this->column('Tambahan (Rp)', Type::Money),
                $this->column('Makanan (Rp)', Type::Money),
                $this->column('Total (Rp)', Type::Money),
                $this->column('Dibayar (Rp)', Type::Money),
            ],
            $rows,
            $summary,
            'Booking dengan tanggal check-in pada periode, ditambah booking yang refund-nya diajukan pada periode. Booking kedaluwarsa (tidak dibayar sampai batas waktu) tidak dimuat dan hanya dihitung jumlahnya. Nomor telepon dan email pelanggan tidak dimuat.'.$this->truncationNote($truncated),
            $truncated,
        );
    }

    public function occupancy(ReportPeriod $period): ReportTable
    {
        $units = Unit::query()->with(['unitType:id,name', 'blocks'])->where('status', Unit::STATUS_ACTIVE)->get();
        $occupiedByUnit = $this->soldNightsByUnit($period);
        $nightsInPeriod = $period->days();

        $perType = [];
        foreach ($units as $unit) {
            $name = $unit->unitType->name;
            $perType[$name] ??= ['units' => 0, 'available' => 0, 'occupied' => 0];
            $perType[$name]['units']++;
            $perType[$name]['available'] += $nightsInPeriod - $this->blockedNights($unit, $period);
            $perType[$name]['occupied'] += $occupiedByUnit[$unit->id] ?? 0;
        }
        ksort($perType);

        $rows = [];
        $totalAvailable = 0;
        $totalOccupied = 0;
        foreach ($perType as $name => $figures) {
            $rows[] = [$name, $figures['units'], $figures['available'], $figures['occupied'], $this->percent($figures['occupied'], $figures['available'])];
            $totalAvailable += $figures['available'];
            $totalOccupied += $figures['occupied'];
        }

        return new ReportTable(
            ReportType::Occupancy,
            $period,
            [
                $this->column('Tipe unit', Type::Text),
                $this->column('Unit aktif', Type::Count),
                $this->column('Malam tersedia', Type::Count),
                $this->column('Malam terisi', Type::Count),
                $this->column('Okupansi (%)', Type::Percent),
            ],
            $rows,
            [
                ['label' => 'Malam tersedia', 'value' => $totalAvailable, 'type' => Type::Count],
                ['label' => 'Malam terisi', 'value' => $totalOccupied, 'type' => Type::Count],
                ['label' => 'Okupansi (%)', 'value' => $this->percent($totalOccupied, $totalAvailable), 'type' => Type::Percent],
            ],
            'Okupansi = malam terisi dibagi malam tersedia. Malam terisi: malam menginap booking berstatus Lunas, Check-in, atau Check-out pada unit aktif. '
            .'Malam tersedia: jumlah hari periode dikali unit aktif, dikurangi hari unit diblokir. Unit nonaktif tidak dihitung.',
        );
    }

    public function topMenu(ReportPeriod $period): ReportTable
    {
        $items = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->where('orders.payment_status', Order::PAYMENT_PAID)
            ->where('orders.created_at', '>=', $period->from->toDateTimeString())
            ->where('orders.created_at', '<', $period->untilExclusive()->toDateTimeString())
            ->groupBy('menu_items.id', 'menu_items.name')
            ->selectRaw('menu_items.name as name, SUM(order_items.qty) as sold, SUM(order_items.qty * order_items.price) as revenue')
            ->orderByDesc('sold')
            ->orderBy('menu_items.name')
            ->limit(self::TOP_MENU_LIMIT)
            ->get();

        return new ReportTable(
            ReportType::TopMenu,
            $period,
            [
                $this->column('Menu', Type::Text),
                $this->column('Terjual', Type::Count),
                $this->column('Omzet (Rp)', Type::Money),
            ],
            $items->map(fn ($item) => [$item->name, (int) $item->sold, (int) $item->revenue])->all(),
            [
                ['label' => 'Total terjual', 'value' => (int) $items->sum('sold'), 'type' => Type::Count],
                ['label' => 'Total omzet (Rp)', 'value' => (int) $items->sum('revenue'), 'type' => Type::Money],
            ],
            'Item pada pesanan yang sudah dibayar, dengan tanggal pesanan pada periode. Diurutkan dari yang paling banyak terjual, '.self::TOP_MENU_LIMIT.' teratas.',
        );
    }

    public function refunds(ReportPeriod $period): ReportTable
    {
        $query = Refund::query()
            ->where('created_at', '>=', $period->from->toDateTimeString())
            ->where('created_at', '<', $period->untilExclusive()->toDateTimeString());

        $refunds = (clone $query)->with('booking.customer')->orderBy('created_at')->orderBy('id')->limit(self::MAX_ROWS + 1)->get();
        $truncated = $refunds->count() > self::MAX_ROWS;

        $rows = $refunds->take(self::MAX_ROWS)->map(fn (Refund $refund) => [
            $refund->booking->code ?? '-',
            $refund->booking?->customer->name ?? '-',
            $refund->created_at->format('d/m/Y'),
            $refund->status->getLabel(),
            $refund->amount,
            $refund->reason ?? '-',
        ])->all();

        $perStatus = (clone $query)->toBase()->selectRaw('status, COUNT(*) as total, SUM(amount) as amount')->groupBy('status')->get()->keyBy('status');
        $summary = [];
        foreach (RefundStatus::cases() as $status) {
            if ($perStatus->has($status->value)) {
                $summary[] = ['label' => $status->getLabel(), 'value' => (int) $perStatus[$status->value]->total, 'type' => Type::Count];
            }
        }
        $summary[] = [
            'label' => 'Total dikembalikan (Rp)',
            'value' => (int) ($perStatus[RefundStatus::Paid->value]->amount ?? 0),
            'type' => Type::Money,
        ];

        return new ReportTable(
            ReportType::Refund,
            $period,
            [
                $this->column('Kode booking', Type::Text),
                $this->column('Pelanggan', Type::Text),
                $this->column('Diajukan', Type::Text),
                $this->column('Status', Type::Text),
                $this->column('Jumlah (Rp)', Type::Money),
                $this->column('Alasan', Type::Text),
            ],
            $rows,
            $summary,
            'Refund dengan tanggal pengajuan pada periode. Total dikembalikan hanya menghitung refund berstatus Sudah Ditransfer.'.$this->truncationNote($truncated),
            $truncated,
        );
    }

    public function paymentsByMethod(ReportPeriod $period): ReportTable
    {
        $groups = Payment::query()
            ->settled()
            ->paidBetween($period->from->toDateTimeString(), $period->untilExclusive()->toDateTimeString())
            ->selectRaw('method, direction, COUNT(*) as transactions, SUM(amount) as total')
            ->groupBy('method', 'direction')
            ->get();

        $rows = [];
        $in = 0;
        $out = 0;
        foreach ([PaymentDirection::In, PaymentDirection::Out] as $direction) {
            foreach (PaymentMethod::cases() as $method) {
                $group = $groups->first(fn (Payment $row) => $row->method === $method && $row->direction === $direction);
                if ($group === null) {
                    continue;
                }
                $rows[] = [$this->methodLabel($method), $direction === PaymentDirection::In ? 'Masuk' : 'Refund keluar', (int) $group->transactions, (int) $group->total];
                $direction === PaymentDirection::In ? $in += (int) $group->total : $out += (int) $group->total;
            }
        }

        return new ReportTable(
            ReportType::PaymentMethod,
            $period,
            [
                $this->column('Metode', Type::Text),
                $this->column('Arah', Type::Text),
                $this->column('Transaksi', Type::Count),
                $this->column('Total (Rp)', Type::Money),
            ],
            $rows,
            [
                ['label' => 'Total masuk (Rp)', 'value' => $in, 'type' => Type::Money],
                ['label' => 'Total refund keluar (Rp)', 'value' => $out, 'type' => Type::Money],
                ['label' => 'Neto (Rp)', 'value' => $in - $out, 'type' => Type::Money],
            ],
            'Pembayaran berstatus berhasil dengan tanggal bayar pada periode. Uang masuk dan refund keluar dipisah per metode.',
        );
    }

    /**
     * Same settled cash as the payment method report, split into tent rent, add-ons and food. A
     * booking payment covers several things at once, so it is spread over that booking's tent,
     * add-on and billed food lines in proportion to their amounts.
     */
    public function revenueByCategory(ReportPeriod $period): ReportTable
    {
        $categories = [
            'tent' => 'Sewa tenda',
            'addon' => 'Add-on (extra bed, kayu bakar, aktivitas)',
            'food_booking' => 'F&B ditagih ke booking',
            'food_direct' => 'F&B langsung (QR dan walk-in)',
        ];
        $in = array_fill_keys(array_keys($categories), 0);
        $out = $in;

        Payment::query()
            ->settled()
            ->paidBetween($period->from->toDateTimeString(), $period->untilExclusive()->toDateTimeString())
            ->with(['payable' => fn ($morph) => $morph->morphWith([
                Booking::class => ['bookingUnits', 'addons', 'orders'],
            ])])
            ->get()
            ->each(function (Payment $payment) use (&$in, &$out): void {
                $split = $this->splitPayment($payment);
                foreach ($split as $category => $amount) {
                    $payment->direction === PaymentDirection::In ? $in[$category] += $amount : $out[$category] += $amount;
                }
            });

        $rows = [];
        foreach ($categories as $key => $label) {
            $rows[] = [$label, $in[$key], $out[$key], $in[$key] - $out[$key]];
        }

        $tentNet = $in['tent'] + $in['addon'] - $out['tent'] - $out['addon'];
        $foodNet = $in['food_booking'] + $in['food_direct'] - $out['food_booking'] - $out['food_direct'];

        return new ReportTable(
            ReportType::Revenue,
            $period,
            [
                $this->column('Kategori', Type::Text),
                $this->column('Masuk (Rp)', Type::Money),
                $this->column('Refund keluar (Rp)', Type::Money),
                $this->column('Neto (Rp)', Type::Money),
            ],
            $rows,
            [
                ['label' => 'Tenda dan add-on, neto (Rp)', 'value' => $tentNet, 'type' => Type::Money],
                ['label' => 'F&B, neto (Rp)', 'value' => $foodNet, 'type' => Type::Money],
                ['label' => 'Total neto (Rp)', 'value' => $tentNet + $foodNet, 'type' => Type::Money],
            ],
            'Pembayaran berhasil dengan tanggal bayar pada periode, sama dengan laporan pembayaran per metode. Pembayaran booking dibagi ke tenda, add-on, dan makanan sesuai porsi rincian booking.',
        );
    }

    /** Food billed to the booking, the same figure the revenue split and the booking report use. */
    private function bookingFoodTotal(Booking $booking): int
    {
        return (int) $booking->orders
            ->filter(fn (Order $order): bool => $order->bill_to_booking && $order->status !== OrderStatus::Dibatalkan->value)
            ->sum(fn (Order $order): int => (int) $order->total + (int) $order->tax);
    }

    /** @return array<string, int> category => rupiah, summing exactly to the payment amount */
    private function splitPayment(Payment $payment): array
    {
        $payable = $payment->payable;

        if ($payable instanceof Order) {
            return ['food_direct' => (int) $payment->amount];
        }

        if (! $payable instanceof Booking) {
            return ['tent' => (int) $payment->amount];
        }

        $parts = [
            'addon' => (int) $payable->addons->sum('subtotal'),
            'food_booking' => $this->bookingFoodTotal($payable),
        ];
        $base = (int) $payable->bookingUnits->sum('subtotal') + array_sum($parts);

        if ($base <= 0) {
            return ['tent' => (int) $payment->amount];
        }

        $split = array_map(fn (int $part): int => intdiv((int) $payment->amount * $part, $base), $parts);

        // Tent takes the remainder so rounding never loses or invents a rupiah.
        return ['tent' => (int) $payment->amount - array_sum($split)] + $split;
    }

    /** @return array<int, int> sold nights per unit id inside the period, active units only */
    private function soldNightsByUnit(ReportPeriod $period): array
    {
        $lines = DB::table('booking_units')
            ->join('bookings', 'bookings.id', '=', 'booking_units.booking_id')
            ->join('units', 'units.id', '=', 'booking_units.unit_id')
            ->where('units.status', Unit::STATUS_ACTIVE)
            ->whereIn('bookings.status', array_map(fn (BookingStatus $status) => $status->value, self::SOLD_STATUSES))
            ->where('booking_units.check_in', '<', $period->untilExclusive()->toDateString())
            ->where('booking_units.check_out', '>', $period->from->toDateString())
            ->get(['booking_units.unit_id', 'booking_units.check_in', 'booking_units.check_out']);

        $nights = [];
        foreach ($lines as $line) {
            $first = max(CarbonImmutable::parse($line->check_in), $period->from);
            $last = min(CarbonImmutable::parse($line->check_out), $period->untilExclusive());
            $nights[$line->unit_id] = ($nights[$line->unit_id] ?? 0) + max(0, (int) $first->diffInDays($last));
        }

        return $nights;
    }

    /** Days of the period a unit is blocked; a block's end date is inclusive and overlapping blocks count once. */
    private function blockedNights(Unit $unit, ReportPeriod $period): int
    {
        $blocked = [];
        foreach ($unit->blocks as $block) {
            $start = max(CarbonImmutable::parse($block->start_date), $period->from);
            $end = min(CarbonImmutable::parse($block->end_date), $period->to);
            for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                $blocked[$day->toDateString()] = true;
            }
        }

        return count($blocked);
    }

    private function percent(int $part, int $whole): int
    {
        return $whole > 0 ? (int) round($part / $whole * 100) : 0;
    }

    private function methodLabel(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::Gateway => 'Gateway',
            PaymentMethod::Transfer => 'Transfer',
            PaymentMethod::Cash => 'Tunai',
            PaymentMethod::Manual => 'Manual',
            PaymentMethod::Qris => 'QRIS',
        };
    }

    private function truncationNote(bool $truncated): string
    {
        return $truncated ? ' Hanya '.self::MAX_ROWS.' baris pertama ditampilkan; persempit periode untuk melihat sisanya.' : '';
    }

    /** @return array{label: string, type: Type} */
    private function column(string $label, Type $type): array
    {
        return ['label' => $label, 'type' => $type];
    }
}
