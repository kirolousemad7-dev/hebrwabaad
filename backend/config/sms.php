<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Provider Abstraction
    |--------------------------------------------------------------------------
    |
    | SMS_PROVIDER options: log | null | http
    | - log: writes messages to the application log (local/dev)
    | - null: no-op sender (tests)
    | - http: POST JSON to SMS_HTTP_ENDPOINT with key/secret/from/to/message
    |
    */
    'default' => env('SMS_PROVIDER', 'log'),

    'api_key' => env('SMS_API_KEY'),
    'api_secret' => env('SMS_API_SECRET'),
    'from' => env('SMS_FROM'),

    'http' => [
        'endpoint' => env('SMS_HTTP_ENDPOINT'),
        'timeout' => (int) env('SMS_HTTP_TIMEOUT', 10),
    ],

    'otp' => [
        'length' => 6,
        'ttl_minutes' => (int) env('SMS_OTP_TTL_MINUTES', 10),
        'max_attempts' => (int) env('SMS_OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('SMS_OTP_RESEND_COOLDOWN', 60),
    ],
];
