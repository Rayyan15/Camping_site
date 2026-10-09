<?php

namespace App\Enums;

/**
 * Keys of the settings table. Only cases with an owner-facing form field are listed in editable().
 */
enum SettingKey: string
{
    case TaxRate = 'tax_rate';
    case CheckInTime = 'check_in_time';
    case CheckOutTime = 'check_out_time';
    case DownPaymentPercent = 'down_payment_percent';
    case PendingOwnerConfirmation = 'pending_owner_confirmation';

    public const DEFAULT_CHECK_IN_TIME = '14:00';

    public const DEFAULT_CHECK_OUT_TIME = '12:00';

    public const DEFAULT_DOWN_PAYMENT_PERCENT = 0;

    public function label(): string
    {
        return match ($this) {
            self::TaxRate => 'Pajak dan biaya layanan',
            self::CheckInTime => 'Jam check-in standar',
            self::CheckOutTime => 'Jam check-out standar',
            self::DownPaymentPercent => 'Persentase uang muka (DP)',
            self::PendingOwnerConfirmation => 'Pengaturan menunggu konfirmasi pemilik',
        };
    }
}
