<?php

namespace App\Services;

use App\Models\Refund;

final readonly class CancellationResult
{
    public function __construct(
        public CancellationOutcome $outcome,
        public ?Refund $refund = null,
    ) {}
}
