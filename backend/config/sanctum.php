<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [
    'stateful' => explode(',', env(
        'SANCTUM_STATEFUL_DOMAINS',
        '',
    )),

    'guard' => ['web'],

    'expiration' => env('SANCTUM_TOKEN_EXPIRATION_MINUTES') !== null && env('SANCTUM_TOKEN_EXPIRATION_MINUTES') !== ''
        ? (int) env('SANCTUM_TOKEN_EXPIRATION_MINUTES')
        : null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
