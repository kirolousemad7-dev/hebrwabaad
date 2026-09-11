<?php

namespace App\Console\Commands;

use App\Services\Printing\PrintingQuotationService;
use Illuminate\Console\Command;

class ExpirePrintingQuotations extends Command
{
    protected $signature = 'printing:expire-quotations';

    protected $description = 'Expire sent/viewed printing quotations past their valid_until date';

    public function handle(PrintingQuotationService $quotations): int
    {
        $count = $quotations->expireDue();
        $this->info("Expired {$count} printing quotation(s).");

        return self::SUCCESS;
    }
}
