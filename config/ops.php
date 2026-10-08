<?php

return [
    /*
    | Thresholds read by php artisan app:health, meant for cron or an uptime monitor.
    */
    'heartbeat_cache_key' => 'ops:last_schedule_run',

    'heartbeat_max_age_seconds' => 180,

    'max_failed_jobs' => (int) env('HEALTH_MAX_FAILED_JOBS', 0),

    'max_queue_wait_seconds' => (int) env('HEALTH_MAX_QUEUE_WAIT_SECONDS', 300),
];
