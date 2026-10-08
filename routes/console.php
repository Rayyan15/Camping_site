<?php

use App\Jobs\ReleaseExpiredHolds;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ReleaseExpiredHolds)->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::command('app:schedule-heartbeat')->everyMinute();

Schedule::command('app:backup-database')
    ->dailyAt(config('backup.run_at'))
    ->withoutOverlapping()
    ->onOneServer();
