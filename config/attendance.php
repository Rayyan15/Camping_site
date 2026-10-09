<?php

return [
    /*
    | Fingerprint log file read by the scheduled SyncFingerprintLogs job (CSV or ZKTeco attlog.dat).
    | Empty means the job does nothing. Absolute path, or relative to the private "local" disk root.
    */
    'source_path' => env('ATTENDANCE_LOG_PATH'),

    /*
    | The scheduled sync re-reads this many days back. Safe because the sync is idempotent.
    */
    'sync_days_back' => 3,

    /*
    | Time of day (Asia/Jakarta) the scheduled sync runs.
    */
    'sync_at' => '23:30',

    'upload' => [
        'disk' => 'local',
        'directory' => 'attendance-imports',
        'max_kilobytes' => 5120,
    ],

    /*
    | Two punches closer than this are one tap counted twice, so they do not produce a clock out.
    */
    'min_clock_out_gap_minutes' => 5,

    /*
    | For shifts that cross midnight, punches earlier than the shift end plus this grace
    | belong to the previous work date.
    */
    'overnight_checkout_grace_minutes' => 240,

    /*
    | ISO weekdays (1 = Monday ... 7 = Sunday) that are never working days in the recap.
    | The system has no day-off roster, so by default every calendar day counts.
    */
    'off_weekdays' => [],
];
