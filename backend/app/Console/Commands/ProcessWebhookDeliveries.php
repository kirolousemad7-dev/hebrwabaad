<?php

namespace App\Console\Commands;

use App\Services\Operations\WebhookDispatcher;
use Illuminate\Console\Command;

class ProcessWebhookDeliveries extends Command
{
    protected $signature = 'webhooks:process-deliveries {--limit=50 : Max deliveries to process}';

    protected $description = 'Process pending outbound webhook deliveries that are due for attempt or retry';

    public function handle(WebhookDispatcher $dispatcher): int
    {
        $processed = $dispatcher->processDue((int) $this->option('limit'));
        $this->info("Processed {$processed} webhook delivery attempt(s).");

        return self::SUCCESS;
    }
}
