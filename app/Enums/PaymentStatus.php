<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';

    /**
     * Maps a Midtrans transaction_status (and fraud_status for card captures) to a payment status.
     */
    public static function fromGatewayStatus(string $transactionStatus, ?string $fraudStatus = null): self
    {
        return match ($transactionStatus) {
            'settlement' => self::Paid,
            'capture' => $fraudStatus === 'challenge' ? self::Pending : self::Paid,
            'expire' => self::Expired,
            'deny', 'cancel', 'failure' => self::Failed,
            default => self::Pending,
        };
    }
}
