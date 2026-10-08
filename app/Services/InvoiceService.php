<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Order;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    private const NUMBER_PREFIX = 'INV';

    private const SEQUENCE_WIDTH = 4;

    public function render(Booking $booking): PDF
    {
        $invoiceNumber = $this->ensureInvoiceNumber($booking);

        return app('dompdf.wrapper')
            ->setPaper('a4')
            ->loadView('invoices.booking', [
                'booking' => $booking,
                'invoiceNumber' => $invoiceNumber,
                'business' => config('site'),
            ] + $this->invoiceData($booking));
    }

    /**
     * Lines and totals shown on the invoice.
     *
     * @return array{unitLines: array, addonLines: array, foodLines: array, subtotal: int, grandTotal: int, balance: int, isSettled: bool}
     */
    public function invoiceData(Booking $booking): array
    {
        $booking->loadMissing(['customer', 'bookingUnits.unit.unitType', 'addons.addon']);

        $foodLines = $this->foodLines($booking);
        // Pre-orders are priced into booking.total at checkout; only later room-billed orders are extra.
        $extraFoodTotal = (int) collect($foodLines)->where('in_booking_total', false)->sum('subtotal');
        $grandTotal = $booking->total + $extraFoodTotal;

        return [
            'unitLines' => $this->unitLines($booking),
            'addonLines' => $this->addonLines($booking),
            'foodLines' => $foodLines,
            'subtotal' => $booking->subtotal + $extraFoodTotal,
            'grandTotal' => $grandTotal,
            'balance' => max(0, $grandTotal - $booking->paid_amount),
            'isSettled' => $booking->paid_amount >= $grandTotal,
        ];
    }

    /**
     * Assigns INV-yymmdd-NNNN once; the sequence restarts every day.
     */
    public function ensureInvoiceNumber(Booking $booking): string
    {
        if ($booking->invoice_number) {
            return $booking->invoice_number;
        }

        return DB::transaction(function () use ($booking) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! $locked->invoice_number) {
                $locked->update(['invoice_number' => $this->nextNumber()]);
            }

            $booking->invoice_number = $locked->invoice_number;
            $booking->syncOriginalAttribute('invoice_number');

            return $locked->invoice_number;
        });
    }

    private function nextNumber(): string
    {
        $prefix = sprintf('%s-%s-', self::NUMBER_PREFIX, Date::now()->format('ymd'));

        $last = Booking::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->max('invoice_number');

        $sequence = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, self::SEQUENCE_WIDTH, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, array{description: string, qty: string, price: int, subtotal: int}>
     */
    private function unitLines(Booking $booking): array
    {
        return $booking->bookingUnits
            ->groupBy(fn ($row) => $row->unit?->unitType?->name.'|'.$row->price_per_night.'|'.$row->nights)
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'description' => ($first->unit?->unitType?->name ?? 'Unit').' ('.$first->nights.' malam)',
                    'qty' => (string) $rows->count(),
                    'price' => $first->price_per_night * $first->nights,
                    'subtotal' => (int) $rows->sum('subtotal'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{description: string, qty: string, price: int, subtotal: int}>
     */
    private function addonLines(Booking $booking): array
    {
        return $booking->addons->map(fn ($row) => [
            'description' => $row->addon?->name ?? 'Tambahan',
            'qty' => (string) $row->qty,
            'price' => $row->price,
            'subtotal' => $row->subtotal,
        ])->all();
    }

    /**
     * Pre-order and room-billed F&B orders that are charged to this booking.
     *
     * @return array<int, array{description: string, qty: string, price: int, subtotal: int}>
     */
    private function foodLines(Booking $booking): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->where('orders.booking_id', $booking->id)
            ->where('orders.bill_to_booking', true)
            ->select('menu_items.name', 'order_items.qty', 'order_items.price', 'orders.source')
            ->get()
            ->map(fn ($row) => [
                'description' => $row->name,
                'qty' => (string) $row->qty,
                'price' => (int) $row->price,
                'subtotal' => (int) $row->price * (int) $row->qty,
                'in_booking_total' => $row->source === Order::SOURCE_PREORDER,
            ])->all();
    }
}
