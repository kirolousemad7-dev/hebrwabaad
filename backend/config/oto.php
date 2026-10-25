<?php

return [
    /*
    | OTO Shipping API V2. Disabled by default so the app never calls OTO,
    | and never creates a paid shipment, until an operator turns it on.
    | The default host is the sandbox. Production is https://api.tryoto.com/rest/v2
    | and must be set explicitly in the environment.
    */
    'enabled' => filter_var(env('OTO_ENABLED', false), FILTER_VALIDATE_BOOL),

    'base_url' => rtrim((string) env('OTO_API_BASE_URL', 'https://staging-api.tryoto.com/rest/v2'), '/'),

    'refresh_token' => env('OTO_REFRESH_TOKEN'),

    /*
    | OTO_ORDER_CODE is not an OTO V2 field. Official createOrder uses the
    | merchant's own order id, or lets OTO generate one. A sample value such
    | as 2001 must not be sent on every order. This app sends the local order
    | reference as orderId and ignores OTO_ORDER_CODE.
    */
    'order_code' => env('OTO_ORDER_CODE'),

    'pickup_location_code' => env('OTO_PICKUP_LOCATION_CODE'),

    'origin_city' => env('OTO_ORIGIN_CITY', 'Riyadh'),

    'origin_country' => env('OTO_ORIGIN_COUNTRY', 'SA'),

    'default_weight_kg' => (float) env('OTO_DEFAULT_WEIGHT_KG', 1),

    'timeout' => (int) env('OTO_TIMEOUT', 15),

    'webhook_secret' => env('OTO_WEBHOOK_SECRET'),

    'webhook_tolerance_seconds' => (int) env('OTO_WEBHOOK_TOLERANCE', 900),

    'retry_sleep_ms' => (int) env('OTO_RETRY_SLEEP_MS', 200),

    'currency' => 'SAR',
];
