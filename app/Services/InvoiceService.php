<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Order;
use Barryvdh\DomPDF\PDF;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    private const NUMBER_PREFIX = 'INV';

    private const SEQUENCE_WIDTH = 4;

    private const NUMBER_ATTEMPTS = 5;

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
     * @return array{unitLines: array, addonLines: array, foodLines: array, subtotal: int, tax: int, grandTotal: int, balance: int, isSettled: bool}
     */
    public function invoiceData(Booking $booking): array
    {
        $booking->loadMissing(['customer', 'bookingUnits.unit.unitType', 'addons.addon']);

        $billing = app(BookingBilling::class)->breakdown($booking);
        $grandTotal = $billing['total'];

        return [
            'unitLines' => $this->unitLines($booking),
            'addonLines' => $this->addonLines($booking),
            'foodLines' => $this->foodLines($booking),
            'subtotal' => $billing['subtotal'],
            'tax' => $billing['tax'],
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

        // The sequence is shared across bookings but only this booking row is locked, so two
        // invoices created at once can pick the same number; the unique index rejects the loser.
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->assignNumber($booking);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::NUMBER_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    private function assignNumber(Booking $booking): string
    {
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

    protected function nextNumber(): string
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
        // Grouping by subtotal keeps price x qty equal to the line subtotal even when special
        // prices make the average nightly rate a fraction.
        return $booking->bookingUnits
            ->groupBy(fn ($row) => $row->unit?->unitType?->name.'|'.$row->subtotal.'|'.$row->nights)
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'description' => ($first->unit?->unitType?->name ?? 'Unit').' ('.$first->nights.' malam)',
                    'qty' => (string) $rows->count(),
                    'price' => (int) $first->subtotal,
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
