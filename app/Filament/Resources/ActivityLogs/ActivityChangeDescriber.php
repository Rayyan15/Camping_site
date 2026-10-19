<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\SettingKey;
use App\Filament\Resources\Payments\PaymentLabels;
use App\Models\ActivityLog;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Turns raw before/after columns into sentences the owner can read: Indonesian field names,
 * status labels instead of codes, rupiah and dates, and internal ids and secrets left out.
 */
class ActivityChangeDescriber
{
    private const SUBJECTS = [
        'booking' => 'Booking',
        'payment' => 'Pembayaran',
        'refund' => 'Refund',
        'order' => 'Pesanan',
        'attendance' => 'Absensi',
        'evaluation' => 'Penilaian',
        'user' => 'Akun',
        'setting' => 'Pengaturan',
        'menu_item' => 'Menu',
    ];

    private const FIELDS = [
        'code' => 'Kode',
        'check_in' => 'Check-in',
        'check_out' => 'Check-out',
        'guests' => 'Jumlah tamu',
        'status' => 'Status',
        'hold_expires_at' => 'Batas bayar',
        'subtotal' => 'Subtotal',
        'tax' => 'Pajak',
        'total' => 'Total',
        'notes' => 'Catatan',
        'note' => 'Catatan',
        'paid_amount' => 'Sudah dibayar',
        'cancelled_at' => 'Dibatalkan pada',
        'direction' => 'Arah',
        'method' => 'Metode',
        'amount' => 'Nominal',
        'paid_at' => 'Dibayar pada',
        'reviewed_at' => 'Diperiksa pada',
        'reason' => 'Alasan',
        'source' => 'Sumber',
        'customer_name' => 'Nama pemesan',
        'customer_phone' => 'Nomor pemesan',
        'scheduled_at' => 'Waktu saji',
        'payment_status' => 'Pembayaran',
        'bill_to_booking' => 'Tagih ke booking',
        'date' => 'Tanggal',
        'clock_in' => 'Jam masuk',
        'clock_out' => 'Jam pulang',
        'period' => 'Periode',
        'total_score' => 'Nilai',
        'value' => 'Nilai',
        'is_available' => 'Tersedia',
        'name' => 'Nama',
        'price' => 'Harga',
    ];

    /** Foreign keys, tokens, file paths and bookkeeping columns mean nothing to a reader. */
    private const HIDDEN = [
        'id', 'customer_id', 'booking_id', 'employee_id', 'dining_spot_id', 'payable_id', 'payable_type',
        'recorded_by', 'reviewed_by', 'approved_by', 'requested_by', 'evaluated_by', 'user_id',
        'access_token', 'remember_token', 'password', 'proof_path', 'gateway_ref', 'key',
        'created_at', 'updated_at',
    ];

    private const MONEY = ['subtotal', 'tax', 'total', 'paid_amount', 'amount', 'price'];

    private const DATES = ['check_in', 'check_out', 'date'];

    private const DATETIMES = ['hold_expires_at', 'cancelled_at', 'paid_at', 'reviewed_at', 'scheduled_at', 'clock_in', 'clock_out'];

    public static function subjectLabel(string $alias): string
    {
        return self::SUBJECTS[$alias] ?? (string) str($alias)->replace('_', ' ')->headline();
    }

    public static function subject(ActivityLog $record): string
    {
        return self::subjectLabel($record->subject_type).' #'.$record->subject_id;
    }

    public static function describe(ActivityLog $record): string
    {
        $old = $record->changes['old'] ?? [];
        $new = $record->changes['new'] ?? [];
        $fields = array_keys($new ?: $old);

        $lines = collect($fields)
            ->reject(fn (string $field) => in_array($field, self::HIDDEN, true))
            ->map(function (string $field) use ($record, $old, $new): string {
                $label = self::label($record, $field);
                $hasBefore = array_key_exists($field, $old) && $new !== [];
                $after = self::value($record, $field, $new[$field] ?? $old[$field] ?? null);

                return $hasBefore
                    ? $label.' diubah dari '.self::value($record, $field, $old[$field]).' menjadi '.$after
                    : $label.': '.$after;
            });

        return $lines->isEmpty() ? 'Tidak ada detail yang perlu ditampilkan.' : $lines->implode('. ').'.';
    }

    private static function label(ActivityLog $record, string $field): string
    {
        if ($record->subject_type === 'setting' && $field === 'value') {
            $key = SettingKey::tryFrom((string) ($record->changes['new']['key'] ?? $record->changes['old']['key'] ?? ''));

            return $key?->label() ?? 'Nilai';
        }

        return self::FIELDS[$field] ?? (string) str($field)->replace('_', ' ')->ucfirst();
    }

    private static function value(ActivityLog $record, string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'kosong';
        }

        if (is_bool($value) || in_array($field, ['bill_to_booking', 'is_available'], true)) {
            return $value ? 'ya' : 'tidak';
        }

        if (in_array($field, self::MONEY, true) && is_numeric($value)) {
            return 'Rp '.number_format((int) $value, 0, ',', '.');
        }

        if (in_array($field, self::DATES, true) || in_array($field, self::DATETIMES, true)) {
            return self::date((string) $value, in_array($field, self::DATETIMES, true));
        }

        return self::enumLabel($record->subject_type, $field, (string) $value)
            ?? (is_scalar($value) ? (string) $value : 'diperbarui');
    }

    private static function enumLabel(string $subject, string $field, string $value): ?string
    {
        return match (true) {
            $subject === 'booking' && $field === 'status' => BookingStatus::tryFrom($value)?->getLabel(),
            $subject === 'payment' && $field === 'status' => ($s = PaymentStatus::tryFrom($value)) ? PaymentLabels::status($s) : null,
            $field === 'method' => ($m = PaymentMethod::tryFrom($value)) ? PaymentLabels::method($m) : null,
            $field === 'direction' => ($d = PaymentDirection::tryFrom($value)) ? PaymentLabels::direction($d) : null,
            $subject === 'refund' && $field === 'status' => RefundStatus::tryFrom($value)?->getLabel(),
            $subject === 'order' && $field === 'status' => OrderStatus::tryFrom($value)?->label(),
            $subject === 'order' && $field === 'source' => match ($value) {
                Order::SOURCE_PREORDER => 'Pre-order',
                Order::SOURCE_QR => 'QR meja',
                default => 'Walk-in',
            },
            $subject === 'order' && $field === 'payment_status' => $value === Order::PAYMENT_PAID ? 'Lunas' : 'Belum lunas',
            $subject === 'attendance' && $field === 'status' => AttendanceStatus::tryFrom($value)?->getLabel(),
            $subject === 'attendance' && $field === 'source' => AttendanceSource::tryFrom($value)?->getLabel(),
            default => null,
        };
    }

    private static function date(string $value, bool $withTime): string
    {
        try {
            $date = CarbonImmutable::parse($value)->timezone(config('app.timezone'))->locale('id');
        } catch (Throwable) {
            return $value;
        }

        return $withTime ? $date->translatedFormat('j M Y, H:i') : $date->translatedFormat('j M Y');
    }
}
