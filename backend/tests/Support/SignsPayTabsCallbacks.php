<?php

namespace Tests\Support;

use Illuminate\Testing\TestResponse;

trait SignsPayTabsCallbacks
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postPayTabsCallback(string $uri, array $payload, ?string $signature = null): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $serverKey = (string) config('payments.paytabs.server_key');

        return $this->call('POST', $uri, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, $serverKey),
        ], $body);
    }
}
