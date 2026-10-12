<?php

return [
    /*
    | Active payment mode. 'manual' (default): the guest transfers to the bank account in config/site.php
    | and uploads a proof that staff confirm. 'midtrans' (Snap) and 'fake' (development and tests only)
    | keep the hosted-gateway code available but unused.
    */
    'gateway' => env('PAYMENT_GATEWAY', 'manual'),
];
