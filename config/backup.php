<?php

return [
    /*
    | Daily database dump (PRD 6.1): kept for retention_days, plus one off-server copy when
    | BACKUP_DISK names a configured filesystem disk (for example an s3 or sftp disk).
    */
    'directory' => storage_path('app/backups'),

    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    'disk' => env('BACKUP_DISK'),

    'disk_directory' => env('BACKUP_DISK_DIRECTORY', 'database-backups'),

    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),

    'timeout_seconds' => (int) env('BACKUP_TIMEOUT_SECONDS', 600),

    'run_at' => env('BACKUP_RUN_AT', '02:30'),
];
