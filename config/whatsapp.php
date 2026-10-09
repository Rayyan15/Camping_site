<?php

return [
    /*
    | Master switch. While false, no message leaves the system and every attempt is recorded as skipped.
    */
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),

    /*
    | Sending driver: 'log' (writes a masked log line, sends nothing) or 'fonnte'.
    */
    'driver' => env('WHATSAPP_DRIVER', 'log'),

    'fonnte' => [
        'url' => env('WHATSAPP_FONNTE_URL', 'https://api.fonnte.com/send'),
        'token' => env('WHATSAPP_FONNTE_TOKEN'),
    ],
];
