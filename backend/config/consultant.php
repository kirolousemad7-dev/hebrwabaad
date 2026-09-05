<?php

return [
    'enabled' => (bool) env('CONSULTANT_ENABLED', true),
    'provider' => env('CONSULTANT_AI_PROVIDER', 'rules'),
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('CONSULTANT_OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('CONSULTANT_OPENAI_TIMEOUT', 12),
        'base_url' => env('CONSULTANT_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],
];
