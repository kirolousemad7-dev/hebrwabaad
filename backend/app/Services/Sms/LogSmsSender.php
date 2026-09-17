<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('sms.send', [
            'to' => $to,
            'from' => config('sms.from'),
            'provider' => 'log',
            'message' => $message,
        ]);
    }
}
