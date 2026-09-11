<?php

namespace App\Jobs;

use App\Services\Operations\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWebhookDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $deliveryId) {}

    public function handle(WebhookDispatcher $dispatcher): void
    {
        $dispatcher->attempt($this->deliveryId);
    }
}
