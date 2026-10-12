<?php

return [
    'name' => 'Halimun Highland',

    /* Round badge supplied by the owner; 160 px so it stays sharp at 80 px on retina screens. */
    'logo' => 'images/brand/halimun-highland-160.png',

    /*
    | Contact details come from the environment so nothing is invented in code.
    | Sections that need a value are hidden while it is empty.
    | whatsapp_number: international format without plus or spaces, e.g. 628123456789.
    */
    'whatsapp_number' => env('SITE_WHATSAPP_NUMBER'),
    'address' => env('SITE_ADDRESS'),
    'maps_url' => env('SITE_MAPS_URL'),
    'instagram_url' => env('SITE_INSTAGRAM_URL'),

    /*
    | Account guests transfer to. Never invented in code: while any part is empty the payment page
    | shows that the account is not set and hides the proof upload.
    */
    'bank' => [
        'name' => env('SITE_BANK_NAME'),
        'account_number' => env('SITE_BANK_ACCOUNT_NUMBER'),
        'account_holder' => env('SITE_BANK_ACCOUNT_HOLDER'),
    ],
    'tiktok_url' => env('SITE_TIKTOK_URL'),
];
