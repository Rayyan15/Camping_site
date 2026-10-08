<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ScheduleHeartbeat extends Command
{
    protected $signature = 'app:schedule-heartbeat';

    protected $description = 'Record that the scheduler is running so app:health can verify it.';

    public function handle(): int
    {
        Cache::forever(config('ops.heartbeat_cache_key'), now()->getTimestamp());

        return self::SUCCESS;
    }
}
