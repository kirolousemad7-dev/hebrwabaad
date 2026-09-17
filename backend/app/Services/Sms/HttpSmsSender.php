<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        $endpoint = (string) config('sms.http.endpoint');
        if ($endpoint === '') {
            throw new RuntimeException('SMS_HTTP_ENDPOINT is not configured.');
        }

        $response = Http::timeout((int) config('sms.http.timeout', 10))
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Api-Key' => (string) config('sms.api_key'),
            ])
            ->post($endpoint, [
                'api_key' => config('sms.api_key'),
                'api_secret' => config('sms.api_secret'),
                'from' => config('sms.from'),
                'to' => $to,
                'message' => $message,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('SMS provider rejected the message (HTTP '.$response->status().').');
        }
    }
}
