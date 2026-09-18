<?php

namespace App\Console\Commands;

use App\Services\Invoices\InvoiceService;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark issued/sent invoices past due_date as OVERDUE';

    public function handle(InvoiceService $invoices): int
    {
        $count = $invoices->markOverdue();
        $this->info("Marked {$count} invoice(s) overdue.");

        return self::SUCCESS;
    }
}
