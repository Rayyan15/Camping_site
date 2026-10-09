<?php

namespace App\Enums;

enum WhatsAppMessageType: string
{
    case BookingCreated = 'booking_created';
    case PaymentConfirmed = 'payment_confirmed';
    case CheckInReminder = 'check_in_reminder';
    case BookingCancelled = 'booking_cancelled';
    case RefundApproved = 'refund_approved';
}
