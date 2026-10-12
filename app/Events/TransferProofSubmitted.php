<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when a guest uploads a transfer proof. Dispatched after commit so a failing listener
 * cannot roll back the upload.
 */
class TransferProofSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
