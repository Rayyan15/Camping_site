<?php

namespace App\Enums;

enum PaymentReviewReason: string
{
    /** Money arrived for a booking that was already covered; staff must refund the excess. */
    case Overpaid = 'overpaid';

    /** Money arrived after the booking was cancelled or refunded; it is not applied and must be returned. */
    case BookingClosed = 'booking_closed';

    /** The booking was cancelled while this transfer proof was still unchecked; staff check the bank and return any money. */
    case ProofPendingOnCancel = 'proof_pending_on_cancel';
}
