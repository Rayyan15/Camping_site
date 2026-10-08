<?php

namespace App\Console\Commands;

use App\Ops\HealthChecker;
use Illuminate\Console\Command;

class HealthCheck extends Command
{
    protected $signature = 'app:health';

    protected $description = 'Check database, cache, storage, scheduler heartbeat and queue. Exits non-zero on failure.';

    public function handle(HealthChecker $checker): int
    {
        $results = $checker->run();

        foreach ($results as $name => $result) {
            $this->line(sprintf('%-22s %s  %s', $name, $result['ok'] ? 'OK  ' : 'FAIL', $result['detail']));
        }

        return $checker->passes($results) ? self::SUCCESS : self::FAILURE;
    }
}
