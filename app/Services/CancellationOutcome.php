<?php

namespace App\Services;

enum CancellationOutcome: string
{
    /** Pending booking, nothing was paid: cancelled outright. */
    case Cancelled = 'cancelled';

    /** Paid booking with a refund due: waiting for the owner to decide. */
    case RefundRequested = 'refund_requested';

    /** Paid booking whose policy tier is 0%: cancelled, no refund row exists. */
    case CancelledWithoutRefund = 'cancelled_without_refund';
}
