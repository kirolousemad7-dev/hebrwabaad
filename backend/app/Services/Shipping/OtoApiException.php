<?php

namespace App\Services\Shipping;

use RuntimeException;

class OtoApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 502,
        public readonly ?string $otoCode = null,
    ) {
        parent::__construct($message);
    }
}
