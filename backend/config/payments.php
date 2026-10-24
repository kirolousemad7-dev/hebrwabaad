<?php

return [
    /*
    | PayTabs card checkout. When false, card checkout is unavailable even if keys exist.
    | Without a server_key, CardPaymentGateway::isConfigured() remains false regardless.
    | merchant_id, public_key, and secret_key are stored only so operators can see
    | whether those variables are present. They are not sent to PayTabs.
    */
    'enabled' => (bool) env('PAYTABS_ENABLED', true),

    'paytabs' => [
        'profile_id' => env('PAYTABS_PROFILE_ID'),
        'server_key' => env('PAYTABS_SERVER_KEY'),
        'merchant_id' => env('PAYTABS_MERCHANT_ID'),
        'public_key' => env('PAYTABS_PUBLIC_KEY'),
        'secret_key' => env('PAYTABS_SECRET_KEY'),
        'region' => env('PAYTABS_REGION', 'SAU'),
        'currency' => env('PAYTABS_CURRENCY', 'SAR'),
        'base_url' => env('PAYTABS_BASE_URL') ?: match (strtoupper((string) env('PAYTABS_REGION', 'SAU'))) {
            'EGY' => 'https://secure-egypt.paytabs.com',
            'ARE' => 'https://secure.paytabs.com',
            'JOR' => 'https://secure-jordan.paytabs.com',
            'OMN' => 'https://secure-oman.paytabs.com',
            'KWT' => 'https://secure-kuwait.paytabs.com',
            'GLOBAL' => 'https://secure-global.paytabs.com',
            default => 'https://secure.paytabs.sa',
        },
        'environment' => env('PAYTABS_MODE', env('PAYTABS_ENVIRONMENT', 'test')),
        'callback_url' => env('PAYTABS_CALLBACK_URL'),
        'return_url' => env('PAYTABS_RETURN_URL'),
        'timeout' => (int) env('PAYTABS_TIMEOUT', 15),
    ],
];
