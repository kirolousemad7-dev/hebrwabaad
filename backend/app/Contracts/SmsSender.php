<?php

namespace App\Contracts;

interface SmsSender
{
    /**
     * @throws \RuntimeException when delivery fails for a hard provider error
     */
    public function send(string $to, string $message): void;
}
