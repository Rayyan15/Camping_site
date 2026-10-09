<?php

namespace App\Jobs;

use App\Services\AttendanceSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads the configured fingerprint log file and upserts attendance rows. Safe to run repeatedly:
 * the sync is idempotent and re-reads a few days back to catch late uploads.
 */
class SyncFingerprintLogs implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function handle(AttendanceSyncService $sync): void
    {
        $path = config('attendance.source_path');

        if (blank($path)) {
            Log::info('SyncFingerprintLogs skipped: ATTENDANCE_LOG_PATH is not configured.');

            return;
        }

        $to = CarbonImmutable::now(config('app.timezone'));
        $from = $to->subDays((int) config('attendance.sync_days_back'))->startOfDay();

        try {
            $report = $sync->importFile($path, $from, $to);
        } catch (Throwable $e) {
            Log::error('SyncFingerprintLogs failed.', ['path' => $path, 'error' => $e->getMessage()]);

            throw $e;
        }

        Log::info('SyncFingerprintLogs finished.', $report->toArray());
    }
}
