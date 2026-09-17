<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;

class NullSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        // Intentionally no-op for tests and disabled environments.
    }
}
