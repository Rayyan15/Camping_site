<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final readonly class FingerprintLogEntry
{
    public function __construct(
        public string $fingerprintId,
        public CarbonImmutable $punchedAt,
    ) {}
}
