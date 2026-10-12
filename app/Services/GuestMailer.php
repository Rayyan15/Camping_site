<?php

namespace App\Services;

use App\Mail\BookingMail;
use App\Mail\BookingReceived;
use App\Mail\PaymentConfirmed;
use App\Mail\RescheduleConfirmed;
use App\Mail\TransferProofReceived;
use App\Mail\TransferProofRejected;
use App\Models\Booking;
use Illuminate\Support\Facades\Mail;

/**
 * The single place that decides whether a guest gets an email. Callers invoke it after their
 * transaction has committed; the mail itself is queued.
 */
class GuestMailer
{
    public function __construct(private readonly BookingBilling $billing) {}

    public function bookingReceived(Booking $booking): void
    {
        $this->send($booking, new BookingReceived($booking));
    }

    public function proofReceived(Booking $booking): void
    {
        $this->send($booking, new TransferProofReceived($booking));
    }

    public function paymentConfirmed(Booking $booking): void
    {
        $this->send($booking, new PaymentConfirmed($booking));
    }

    public function proofRejected(Booking $booking, string $reason): void
    {
        $this->send($booking, new TransferProofRejected($booking, $reason));
    }

    public function rescheduleConfirmed(Booking $booking): void
    {
        $this->send($booking, new RescheduleConfirmed($booking, $this->billing->outstanding($booking), (int) $booking->reschedule_credit));
    }

    private function send(Booking $booking, BookingMail $mail): void
    {
        $email = trim((string) $booking->customer?->email);

        if ($email === '') {
            return;
        }

        Mail::to($email)->queue($mail);
    }
}
