<?php

return [
    /*
    | Minutes a pending_payment booking blocks its units before it expires.
    */
    'hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 15),

    /*
    | Minutes a pending_payment booking keeps its units after staff records a partial manual
    | payment (down payment), giving the guest time to settle the rest. Default: one day.
    */
    'manual_dp_hold_minutes' => (int) env('BOOKING_MANUAL_DP_HOLD_MINUTES', 1440),

    /*
    | Attempts for booking transactions. Laravel retries only on deadlock or lock wait, so business
    | errors such as UnitUnavailableException are never repeated.
    */
    'db_transaction_attempts' => max(1, (int) env('BOOKING_DB_TRANSACTION_ATTEMPTS', 3)),

    /*
    | Default tax rate (0.11 = 11%). The `tax_rate` row in the settings table overrides it.
    */
    'tax_rate' => (float) env('BOOKING_TAX_RATE', 0.11),

    /*
    | ISO weekday numbers (Mon=1 ... Sun=7) whose night is priced at the weekend rate.
    | A night is identified by the date it starts, so Friday and Saturday nights are weekend.
    */
    'weekend_night_weekdays' => [5, 6],

    /*
    | Upper bounds that keep a single booking form sane.
    */
    'max_units_per_booking' => 5,
    'max_addon_quantity' => 10,
    'max_preorder_quantity' => 20,

    /*
    | Longest stay in nights and furthest check-in date ahead, so a request cannot ask
    | for an unbounded number of nights.
    */
    'max_nights' => (int) env('BOOKING_MAX_NIGHTS', 14),
    'max_advance_days' => (int) env('BOOKING_MAX_ADVANCE_DAYS', 365),

    /*
    | Per-minute limits on public routes. Lookups by booking code or QR token are limited per
    | IP and code (stops guessing one code) and per IP overall (stops enumerating many codes).
    */
    'throttle' => [
        'lookup_per_code' => (int) env('THROTTLE_LOOKUP_PER_CODE', 30),
        'lookup_per_ip' => (int) env('THROTTLE_LOOKUP_PER_IP', 60),
        'find_per_ip' => (int) env('THROTTLE_FIND_PER_IP', 5),
        'availability_per_ip' => (int) env('THROTTLE_AVAILABILITY_PER_IP', 30),
    ],

    /*
    | Times a pre-order can be served at.
    */
    'serve_times' => [
        '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00',
        '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00',
    ],
];
