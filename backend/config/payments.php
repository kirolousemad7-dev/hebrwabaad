<?php

return [
    'paytabs' => [
        'profile_id' => env('PAYTABS_PROFILE_ID', '154601'),
        'server_key' => env('PAYTABS_SERVER_KEY'),
        'base_url' => env('PAYTABS_BASE_URL', 'https://secure-egypt.paytabs.com'),
        'environment' => env('PAYTABS_ENVIRONMENT', 'test'),
        'timeout' => (int) env('PAYTABS_TIMEOUT', 15),
    ],
];
