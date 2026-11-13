<?php

namespace App\Services\Reports;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReportType;
use App\Enums\ReportValueType as Type;
use App\Models\Booking;
use App\Models\DiningSpot;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Unit;
use App\Services\BookingBilling;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /** Listing reports stop here so a huge period cannot exhaust memory in the PDF or spreadsheet. */
    public const MAX_ROWS = 2000;

    public const TOP_MENU_LIMIT = 50;

    /** Booking statuses whose nights count as sold. */
    private const SOLD_STATUSES = [BookingStatus::Paid, BookingStatus::CheckedIn, BookingStatus::CheckedOut];

    /** Booking statuses that can still owe money: sold stays, plus paid stays held for review. */
    private const OWING_STATUSES = [...self::SOLD_STATUSES, BookingStatus::NeedsReview];

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
        $billing = app(BookingBilling::class);
        $rows = $bookings->take(self::MAX_ROWS)->map(function (Booking $booking) use ($billing) {
            ['tent' => $tent, 'addon' => $addon, 'food' => $food] = $this->billParts($booking);

            return [
                $booking->code,
                $booking->customer->name ?? '-',
                $booking->check_in->format('d/m/Y'),
                $booking->check_out->format('d/m/Y'),
                $booking->status->getLabel(),
                $tent,
                $addon,
                $food,
                $tent + $addon + $food,
                $booking->paid_amount,
                // Only a stay that is sold or held for review can owe money; an unpaid cancelled booking owes nothing.
                in_array($booking->status, self::OWING_STATUSES, true) ? $billing->outstanding($booking) : 0,
            ];
        })->all();

        $perStatus = (clone $query)->toBase()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $expired = (int) ($perStatus[BookingStatus::Expired->value] ?? 0);
        $summary = [['label' => 'Jumlah booking', 'value' => (int) $perStatus->sum() - $expired, 'type' => Type::Count]];
        foreach (BookingStatus::cases() as $status) {
            if ($status !== BookingStatus::Expired && $perStatus->has($status->value)) {
                $summary[] = ['label' => $status->getLabel(), 'value' => (int) $perStatus[$status->value], 'type' => Type::Count, 'group' => 'Status'];
            }
        }
        // Totals cover sold stays only, so a cancelled or unpaid booking never inflates the tent figure.
        $soldRows = array_values(array_filter($rows, fn (array $row, int $index) => in_array($bookings[$index]->status, self::SOLD_STATUSES, true), ARRAY_FILTER_USE_BOTH));
        foreach ([5 => 'Sewa tenda', 6 => 'Tambahan', 7 => 'Makanan'] as $index => $label) {
            $summary[] = ['label' => $label, 'value' => (int) array_sum(array_column($soldRows, $index)), 'type' => Type::Money, 'group' => 'Nilai booking terjual'];
        }
        $summary[] = ['label' => 'Sudah dibayar', 'value' => (int) (clone $query)->where('status', '!=', BookingStatus::Expired->value)->sum('paid_amount'), 'type' => Type::Money, 'group' => 'Pembayaran tamu'];
        $summary[] = ['label' => 'Belum dibayar', 'value' => (int) array_sum(array_column($soldRows, 10)), 'type' => Type::Money, 'group' => 'Pembayaran tamu'];
        if ($expired > 0) {
            $summary[] = ['label' => 'Kedaluwarsa', 'value' => $expired, 'type' => Type::Count, 'group' => 'Tidak masuk tabel'];
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
                $this->column('Sisa (Rp)', Type::Money),
            ],
            $rows,
            $summary,
            'Berisi booking dengan tanggal check-in di periode ini, ditambah booking yang refund-nya diajukan di periode ini. Booking terjual adalah yang berstatus Lunas, Check-in, atau Check-out. Kolom Sisa adalah tagihan yang belum dibayar tamu, biasanya makanan yang ditagih ke booking. Booking kedaluwarsa (tidak dibayar sampai batas waktu) hanya dihitung jumlahnya, tidak masuk tabel. '
            .'Laporan ini memakai tanggal check-in, sedangkan Pendapatan tenda dan F&B serta Pembayaran per metode memakai tanggal bayar, jadi angkanya bisa berbeda. Nomor telepon dan email tamu tidak ditampilkan.'.$this->truncationNote($truncated),
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
                ['label' => 'Okupansi', 'value' => $this->percent($totalOccupied, $totalAvailable), 'type' => Type::Percent],
                ['label' => 'Malam terisi', 'value' => $totalOccupied, 'type' => Type::Count, 'group' => 'Malam menginap'],
                ['label' => 'Malam tersedia', 'value' => $totalAvailable, 'type' => Type::Count, 'group' => 'Malam menginap'],
            ],
            'Okupansi adalah malam terisi dibagi malam tersedia. Malam terisi dihitung dari booking berstatus Lunas, Check-in, atau Check-out di unit aktif. '
            .'Malam tersedia adalah jumlah hari di periode ini dikali jumlah unit aktif, dikurangi hari unit diblokir. Unit nonaktif tidak dihitung.',
        );
    }

    public function topMenu(ReportPeriod $period): ReportTable
    {
        $items = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->leftJoin('dining_spots', 'dining_spots.id', '=', 'orders.dining_spot_id')
            // Food billed to a booking counts once that booking is paid; the order itself stays unpaid.
            ->where(fn ($sold) => $sold
                ->where('orders.payment_status', Order::PAYMENT_PAID)
                ->orWhere(fn ($billed) => $billed
                    ->where('orders.bill_to_booking', true)
                    ->where('orders.status', '!=', OrderStatus::Dibatalkan->value)
                    ->whereIn('orders.booking_id', fn ($paid) => $paid->select('id')->from('bookings')
                        ->whereIn('status', array_map(fn (BookingStatus $status) => $status->value, [...BookingStatus::occupying(), BookingStatus::CheckedOut])))))
            ->where('orders.created_at', '>=', $period->from->toDateTimeString())
            ->where('orders.created_at', '<', $period->untilExclusive()->toDateTimeString())
            ->groupBy('menu_items.id', 'menu_items.name')
            // The owner reads booking food apart from QR scans in a tent or at the cafe, and the cashier's walk-ins.
            ->selectRaw(
                'menu_items.id as id, menu_items.name as name, SUM(order_items.qty) as sold, SUM(order_items.qty * order_items.price) as revenue,'
                .' SUM(CASE WHEN orders.source = ? THEN order_items.qty ELSE 0 END) as via_booking,'
                .' SUM(CASE WHEN orders.source = ? AND dining_spots.type = ? THEN order_items.qty ELSE 0 END) as via_tent,'
                .' SUM(CASE WHEN orders.source = ? AND dining_spots.type = ? THEN order_items.qty ELSE 0 END) as via_cafe,'
                .' SUM(CASE WHEN orders.source = ? THEN order_items.qty ELSE 0 END) as via_walkin',
                [Order::SOURCE_PREORDER, Order::SOURCE_QR, DiningSpot::TYPE_TENT, Order::SOURCE_QR, DiningSpot::TYPE_TABLE, Order::SOURCE_WALKIN],
            )
            ->orderByDesc('sold')
            ->orderBy('menu_items.name')
            ->get();
        $stillOwed = $this->unpaidBookingFood($period);

        // Totals count every dish sold; only the table is cut to the top rows.
        $all = $items;
        $items = $items->take(self::TOP_MENU_LIMIT);

        return new ReportTable(
            ReportType::TopMenu,
            $period,
            [
                $this->column('Menu', Type::Text),
                $this->column('Ikut booking', Type::Count),
                $this->column('QR tenda', Type::Count),
                $this->column('QR kafe', Type::Count),
                $this->column('Walk-in kasir', Type::Count),
                $this->column('Total terjual', Type::Count),
                $this->column('Omzet (Rp)', Type::Money),
            ],
            $items->map(fn ($item) => [
                $item->name, (int) $item->via_booking, (int) $item->via_tent, (int) $item->via_cafe, (int) $item->via_walkin,
                (int) $item->sold, (int) $item->revenue,
            ])->all(),
            [
                ['label' => 'Porsi terjual', 'value' => (int) $all->sum('sold'), 'type' => Type::Count],
                ['label' => 'Ikut booking', 'value' => (int) $all->sum('via_booking'), 'type' => Type::Count, 'group' => 'Asal pesanan'],
                ['label' => 'QR tenda', 'value' => (int) $all->sum('via_tent'), 'type' => Type::Count, 'group' => 'Asal pesanan'],
                ['label' => 'QR kafe', 'value' => (int) $all->sum('via_cafe'), 'type' => Type::Count, 'group' => 'Asal pesanan'],
                ['label' => 'Walk-in kasir', 'value' => (int) $all->sum('via_walkin'), 'type' => Type::Count, 'group' => 'Asal pesanan'],
                ['label' => 'Omzet', 'value' => (int) $all->sum('revenue'), 'type' => Type::Money, 'group' => 'Uang'],
                ['label' => 'Ditagih ke booking, belum dibayar', 'value' => $stillOwed, 'type' => Type::Money, 'group' => 'Uang'],
            ],
            'Dihitung dari pesanan yang dibuat di periode ini dan sudah dibayar. Makanan yang ditagih ke booking ikut dihitung begitu booking-nya lunas, walau tamu belum membayar makanannya; nilainya tampil di Ditagih ke booking, belum dibayar. '
            .'Asal pesanan: Ikut booking (dipesan bersama booking), QR tenda, QR kafe (scan di meja kafe), dan Walk-in kasir. Tabel menampilkan '.self::TOP_MENU_LIMIT.' menu terlaris; angka ringkasan menghitung semua menu.',
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
        $summary = [[
            'label' => 'Total dikembalikan',
            'value' => (int) ($perStatus[RefundStatus::Paid->value]->amount ?? 0),
            'type' => Type::Money,
        ]];
        foreach (RefundStatus::cases() as $status) {
            if ($perStatus->has($status->value)) {
                $summary[] = ['label' => $status->getLabel(), 'value' => (int) $perStatus[$status->value]->total, 'type' => Type::Count, 'group' => 'Pengajuan per status'];
            }
        }

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
            'Berisi refund yang diajukan di periode ini. Total dikembalikan hanya menghitung refund berstatus Sudah Ditransfer.'.$this->truncationNote($truncated),
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
                ['label' => 'Neto', 'value' => $in - $out, 'type' => Type::Money],
                ['label' => 'Uang masuk', 'value' => $in, 'type' => Type::Money, 'group' => 'Arus uang'],
                ['label' => 'Refund keluar', 'value' => $out, 'type' => Type::Money, 'group' => 'Arus uang'],
            ],
            'Berisi pembayaran berhasil dengan tanggal bayar di periode ini, dipisah per metode dan arah uang (masuk atau refund keluar).',
        );
    }

    /**
     * Same settled cash as the payment method report, split into tent rent, add-ons and food. A
     * booking payment covers several things at once, so it fills that booking's tent first, then
     * add-ons, then food billed to the stay.
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
                Booking::class => ['addons', 'orders', 'payments'],
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

        $net = fn (string $key): int => $in[$key] - $out[$key];
        $tentNet = $net('tent');
        $addonNet = $net('addon');
        $foodNet = $net('food_booking') + $net('food_direct');

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
                ['label' => 'Total neto', 'value' => $tentNet + $addonNet + $foodNet, 'type' => Type::Money],
                ['label' => 'Sewa tenda', 'value' => $tentNet, 'type' => Type::Money, 'group' => 'Neto per kategori'],
                ['label' => 'Tambahan (add-on)', 'value' => $addonNet, 'type' => Type::Money, 'group' => 'Neto per kategori'],
                ['label' => 'F&B', 'value' => $foodNet, 'type' => Type::Money, 'group' => 'Neto per kategori'],
            ],
            'Berisi pembayaran berhasil dengan tanggal bayar di periode ini, sama dengan Pembayaran per metode. Pembayaran booking dibagi ke sewa tenda dulu, lalu add-on, lalu makanan yang ditagih ke booking. Makanan yang belum dibayar tamu belum masuk ke sini; lihat kolom Sisa di laporan Booking.',
        );
    }

    /**
     * Food counted as sold in the top menu report that the guest has not paid yet. Capped per booking
     * by that booking's food ordered in the period, so a balance never counts in two periods and an
     * unpaid non-food amount never shows up as food.
     */
    private function unpaidBookingFood(ReportPeriod $period): int
    {
        $foodInPeriod = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.bill_to_booking', true)
            ->where('orders.status', '!=', OrderStatus::Dibatalkan->value)
            ->whereNotNull('orders.booking_id')
            ->where('orders.created_at', '>=', $period->from->toDateTimeString())
            ->where('orders.created_at', '<', $period->untilExclusive()->toDateTimeString())
            ->groupBy('orders.booking_id')
            ->selectRaw('orders.booking_id, SUM(order_items.qty * order_items.price) as food')
            ->pluck('food', 'booking_id');

        $billing = app(BookingBilling::class);

        return (int) Booking::query()
            ->whereKey($foodInPeriod->keys())
            ->whereIn('status', array_map(fn (BookingStatus $status) => $status->value, [...BookingStatus::occupying(), BookingStatus::CheckedOut]))
            ->get()
            ->sum(fn (Booking $booking): int => min($billing->outstanding($booking), (int) $foodInPeriod[$booking->id]));
    }

    /**
     * The bill split the booking report and the revenue split share. booking.total keeps every
     * pre-order priced at checkout, cancelled or not, so all of them count as food; otherwise a
     * pre-order cancelled after the guest paid for it would show up as tent rent.
     *
     * @return array{tent: int, addon: int, food: int}
     */
    private function billParts(Booking $booking): array
    {
        $grandTotal = app(BookingBilling::class)->grandTotal($booking);
        $addon = (int) $booking->addons->sum('subtotal');
        $preorders = (int) $booking->orders
            ->where('source', Order::SOURCE_PREORDER)
            ->sum(fn (Order $order): int => (int) $order->total + (int) $order->tax);
        $food = $preorders + $grandTotal - $booking->total;

        return [
            'tent' => max(0, $grandTotal - $addon - $food),
            'addon' => $addon,
            'food' => $food,
        ];
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

        // Money on a booking pays the tent first, then add-ons, then food billed to the stay, so
        // anything still owed shows up as unpaid food: the owner can match the gap to the bill.
        $bill = $this->billParts($payable);
        $parts = ['tent' => $bill['tent'], 'addon' => $bill['addon'], 'food_booking' => $bill['food']];

        $signed = fn (Payment $p): int => $p->direction === PaymentDirection::In ? (int) $p->amount : -(int) $p->amount;
        $before = (int) $payable->payments
            ->filter(fn (Payment $p): bool => $p->status === PaymentStatus::Paid
                && ($p->paid_at < $payment->paid_at || ($p->paid_at == $payment->paid_at && $p->id < $payment->id)))
            ->sum($signed);
        $after = $before + $signed($payment);

        $from = $this->fill($parts, min($before, $after));
        $to = $this->fill($parts, max($before, $after));
        $split = array_map(fn (string $key): int => $to[$key] - $from[$key], array_combine(array_keys($parts), array_keys($parts)));

        // Money beyond the bill (an overpayment) has no line to fill, so it stays with the tent.
        $split['tent'] += (int) $payment->amount - array_sum($split);

        return $split;
    }

    /**
     * @param  array<string, int>  $parts  in payment order
     * @return array<string, int> how much of each part a running total covers
     */
    private function fill(array $parts, int $paid): array
    {
        $covered = [];
        foreach ($parts as $key => $amount) {
            $covered[$key] = min($amount, max(0, $paid));
            $paid -= $covered[$key];
        }

        return $covered;
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
        return $truncated ? ' Hanya '.self::MAX_ROWS.' baris pertama yang ditampilkan; persempit periode untuk melihat sisanya.' : '';
    }

    /** @return array{label: string, type: Type} */
    private function column(string $label, Type $type): array
    {
        return ['label' => $label, 'type' => $type];
    }
}
