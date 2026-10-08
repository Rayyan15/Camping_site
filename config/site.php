<?php

return [
    'name' => 'Raynad Camping',

    /*
    | Contact details come from the environment so nothing is invented in code.
    | Sections that need a value are hidden while it is empty.
    | whatsapp_number: international format without plus or spaces, e.g. 628123456789.
    */
    'whatsapp_number' => env('SITE_WHATSAPP_NUMBER'),
    'address' => env('SITE_ADDRESS'),
    'maps_url' => env('SITE_MAPS_URL'),
    'instagram_url' => env('SITE_INSTAGRAM_URL'),
];
