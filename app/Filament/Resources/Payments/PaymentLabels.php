<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentDirection;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewReason;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;

/** Indonesian labels and colors for payment values, kept out of the domain enums. */
class PaymentLabels
{
    /** @return array<string, string> */
    public static function methods(): array
    {
        return collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $m) => [$m->value => self::method($m)])->all();
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $s) => [$s->value => self::status($s)])->all();
    }

    /** @return array<string, string> */
    public static function directions(): array
    {
        return collect(PaymentDirection::cases())->mapWithKeys(fn (PaymentDirection $d) => [$d->value => self::direction($d)])->all();
    }

    public static function method(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::Gateway => 'Pembayaran online',
            PaymentMethod::Transfer => 'Transfer bank',
            PaymentMethod::Cash => 'Tunai',
            PaymentMethod::Manual => 'Lainnya',
        };
    }

    public static function status(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Pending => 'Menunggu',
            PaymentStatus::Paid => 'Berhasil',
            PaymentStatus::Failed => 'Gagal',
            PaymentStatus::Expired => 'Kedaluwarsa',
        };
    }

    public static function statusColor(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Pending => 'warning',
            PaymentStatus::Paid => 'success',
            PaymentStatus::Failed => 'danger',
            PaymentStatus::Expired => 'gray',
        };
    }

    public static function direction(PaymentDirection $direction): string
    {
        return match ($direction) {
            PaymentDirection::In => 'Masuk',
            PaymentDirection::Out => 'Keluar',
        };
    }

    public static function directionColor(PaymentDirection $direction): string
    {
        return match ($direction) {
            PaymentDirection::In => 'success',
            PaymentDirection::Out => 'danger',
        };
    }

    public static function payableType(Payment $payment): string
    {
        return match ($payment->payable_type) {
            'booking' => 'Booking',
            'order' => 'Pesanan F&B',
            default => 'Lainnya',
        };
    }

    public static function payableCode(Payment $payment): string
    {
        return $payment->payable?->code ?? '-';
    }

    public static function payerName(Payment $payment): string
    {
        return match (true) {
            $payment->payable instanceof Booking => $payment->payable->customer?->name ?? 'Tanpa nama',
            $payment->payable instanceof Order => $payment->payable->displayName(),
            default => '-',
        };
    }

    /** Failure or review note shown to staff, empty when the payment needs no attention. */
    public static function attentionNote(Payment $payment): ?string
    {
        return match (true) {
            $payment->review_reason === PaymentReviewReason::Overpaid => 'Perlu ditinjau: dana masuk melebihi tagihan, kembalikan selisihnya',
            $payment->review_reason === PaymentReviewReason::BookingClosed => 'Perlu ditinjau: dana masuk setelah booking dibatalkan, kembalikan seluruhnya',
            $payment->failure_reason === PaymentFailureReason::GatewayUnreachable => 'Gagal: gateway tidak terjangkau, transaksi mungkin masih ada di sisi gateway',
            default => null,
        };
    }
}
