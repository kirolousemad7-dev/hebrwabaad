<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentService;
use Illuminate\Console\Command;

class ReconcilePendingPayments extends Command
{
    protected $signature = 'payments:reconcile-pending {--minutes=30 : Only payments idle at least this many minutes}';

    protected $description = 'Reconcile pending/processing PayTabs card payments with the provider (max 50 per run)';

    public function handle(PaymentService $payments): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $count = $payments->reconcilePendingCardPayments($minutes, 50);
        $this->info("Reconciled {$count} payment(s).");

        return self::SUCCESS;
    }
}
