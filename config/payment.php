<?php

return [
    /*
    | Active payment gateway: 'midtrans' (Snap) or 'fake' (development and tests only).
    */
    'gateway' => env('PAYMENT_GATEWAY', 'fake'),
];
