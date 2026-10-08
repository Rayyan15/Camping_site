<?php

return [
    /*
    | Minutes a pending_payment booking blocks its units before it expires.
    */
    'hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 15),

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
    | Times a pre-order can be served at.
    */
    'serve_times' => [
        '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00',
        '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00',
    ],
];
