<?php

namespace App\Services\Reports;

use App\Enums\BookingStatus;
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
        };
    }

    public function bookings(ReportPeriod $period): ReportTable
    {
        $query = Booking::query()->whereBetween('check_in', [$period->from->toDateString(), $period->to->toDateString()]);

        $bookings = (clone $query)->with('customer:id,name')->orderBy('check_in')->orderBy('id')->limit(self::MAX_ROWS + 1)->get();
        $truncated = $bookings->count() > self::MAX_ROWS;

        $rows = $bookings->take(self::MAX_ROWS)->map(fn (Booking $booking) => [
            $booking->code,
            $booking->customer?->name ?? '-',
            $booking->check_in->format('d/m/Y'),
            $booking->check_out->format('d/m/Y'),
            $booking->status->getLabel(),
            $booking->total,
            $booking->paid_amount,
        ])->all();

        $perStatus = (clone $query)->toBase()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $summary = [['label' => 'Jumlah booking', 'value' => (int) $perStatus->sum(), 'type' => Type::Count]];
        foreach (BookingStatus::cases() as $status) {
            if ($perStatus->has($status->value)) {
                $summary[] = ['label' => $status->getLabel(), 'value' => (int) $perStatus[$status->value], 'type' => Type::Count];
            }
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
                $this->column('Total (Rp)', Type::Money),
                $this->column('Dibayar (Rp)', Type::Money),
            ],
            $rows,
            $summary,
            'Booking dengan tanggal check-in pada periode. Nomor telepon dan email pelanggan tidak dimuat.'.$this->truncationNote($truncated),
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
            $refund->booking?->code ?? '-',
            $refund->booking?->customer?->name ?? '-',
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
