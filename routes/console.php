<?php

use App\Jobs\ReleaseExpiredHolds;
use App\Jobs\SyncFingerprintLogs;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ReleaseExpiredHolds)->everyMinute()->withoutOverlapping()->onOneServer();

// Always scheduled: the job logs a skip until ATTENDANCE_LOG_PATH is set, so the gap shows up in the logs.
Schedule::job(new SyncFingerprintLogs)->dailyAt(config('attendance.sync_at'))->withoutOverlapping()->onOneServer();

Schedule::command('app:schedule-heartbeat')->everyMinute();

Schedule::command('app:backup-database')
    ->dailyAt(config('backup.run_at'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('whatsapp:send-reminders')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();
