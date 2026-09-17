<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use InvalidArgumentException;

class SmsManager
{
    public function driver(?string $name = null): SmsSender
    {
        $name ??= (string) config('sms.default', 'log');

        return match ($name) {
            'log' => new LogSmsSender,
            'null', 'none' => new NullSmsSender,
            'http' => new HttpSmsSender,
            default => throw new InvalidArgumentException('Unsupported SMS_PROVIDER ['.$name.'].'),
        };
    }

    public function send(string $to, string $message): void
    {
        $this->driver()->send($to, $message);
    }
}
