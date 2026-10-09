<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppMessageType;
use App\Models\Booking;

class WhatsAppMessageComposer
{
    public function compose(Booking $booking, WhatsAppMessageType $type): string
    {
        $booking->loadMissing('customer', 'bookingUnits.unit.unitType');

        $lines = match ($type) {
            WhatsAppMessageType::BookingCreated => $this->bookingCreated($booking),
            WhatsAppMessageType::PaymentConfirmed => $this->paymentConfirmed($booking),
            WhatsAppMessageType::CheckInReminder => $this->checkInReminder($booking),
            WhatsAppMessageType::BookingCancelled => $this->bookingCancelled($booking),
            WhatsAppMessageType::RefundApproved => $this->refundApproved($booking),
        };

        return implode("\n", array_filter($lines, fn (?string $line) => $line !== null));
    }

    /**
     * @return array<int, string|null>
     */
    private function bookingCreated(Booking $booking): array
    {
        return [
            $this->greeting($booking),
            'Booking Anda di '.config('site.name').' sudah kami terima dengan kode '.$booking->code.'.',
            ...$this->summary($booking),
            $booking->hold_expires_at !== null
                ? 'Mohon selesaikan pembayaran sebelum '.$booking->hold_expires_at->timezone(config('app.timezone'))->format('d/m/Y H:i').' WIB, setelah itu unit dilepas kembali.'
                : null,
            'Bayar di sini: '.route('checkout.show', $booking->access_token),
        ];
    }

    /**
     * @return array<int, string|null>
     */
    private function paymentConfirmed(Booking $booking): array
    {
        return [
            $this->greeting($booking),
            'Pembayaran booking '.$booking->code.' di '.config('site.name').' sudah kami terima. Status booking: lunas.',
            ...$this->summary($booking),
            'Invoice: '.route('booking.invoice', $booking->access_token),
            'Status booking: '.route('booking.status', $booking->access_token),
        ];
    }

    /**
     * @return array<int, string|null>
     */
    private function checkInReminder(Booking $booking): array
    {
        return [
            $this->greeting($booking),
            'Pengingat: check-in booking '.$booking->code.' di '.config('site.name').' dijadwalkan besok, '.$booking->check_in->format('d/m/Y').'.',
            ...$this->summary($booking),
            'Status booking: '.route('booking.status', $booking->access_token),
        ];
    }

    /**
     * @return array<int, string|null>
     */
    private function bookingCancelled(Booking $booking): array
    {
        return [
            $this->greeting($booking),
            'Booking '.$booking->code.' di '.config('site.name').' sudah dibatalkan dan unitnya dilepas.',
            'Tanggal: '.$booking->check_in->format('d/m/Y').' sampai '.$booking->check_out->format('d/m/Y'),
            'Status booking: '.route('booking.status', $booking->access_token),
        ];
    }

    /**
     * @return array<int, string|null>
     */
    private function refundApproved(Booking $booking): array
    {
        $refund = $booking->refunds()->latest('id')->first();

        return [
            $this->greeting($booking),
            'Pengajuan refund booking '.$booking->code.' di '.config('site.name').' sudah disetujui.',
            $refund !== null ? 'Jumlah refund: Rp '.number_format($refund->amount, 0, ',', '.') : null,
            'Staf kami akan menghubungi Anda untuk proses transfer.',
            'Status booking: '.route('booking.status', $booking->access_token),
        ];
    }

    private function greeting(Booking $booking): string
    {
        $name = trim((string) $booking->customer?->name);

        return $name !== '' ? 'Halo '.$name.',' : 'Halo,';
    }

    /**
     * @return array<int, string|null>
     */
    private function summary(Booking $booking): array
    {
        $units = $booking->bookingUnits
            ->groupBy(fn ($row) => $row->unit?->unitType?->name ?? 'Unit')
            ->map(fn ($rows, $name) => $rows->count().' x '.$name)
            ->implode(', ');

        return [
            'Tanggal: '.$booking->check_in->format('d/m/Y').' sampai '.$booking->check_out->format('d/m/Y'),
            $units !== '' ? 'Unit: '.$units : null,
            'Total: Rp '.number_format($booking->total, 0, ',', '.'),
        ];
    }
}
