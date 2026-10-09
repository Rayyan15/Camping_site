<?php

namespace App\Contracts;

use App\Support\FingerprintLogEntry;
use Carbon\CarbonInterface;

/**
 * Where punch records come from. The file reader is the default; a vendor SDK driver
 * can replace it by rebinding this interface once the machine brand is decided.
 */
interface FingerprintLogSource
{
    /**
     * Punches whose time falls between the two instants, inclusive.
     *
     * @return iterable<FingerprintLogEntry>
     */
    public function entries(CarbonInterface $from, CarbonInterface $to): iterable;
}
