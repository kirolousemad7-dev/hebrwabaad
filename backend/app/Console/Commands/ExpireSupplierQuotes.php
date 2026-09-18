<?php

namespace App\Console\Commands;

use App\Services\Quotes\QuotationSupplierSourcingService;
use Illuminate\Console\Command;

class ExpireSupplierQuotes extends Command
{
    protected $signature = 'quotations:expire-supplier-quotes';

    protected $description = 'Expire open supplier sourcing quotes past their valid_until date';

    public function handle(QuotationSupplierSourcingService $sourcing): int
    {
        $count = $sourcing->expireDue();
        $this->info("Expired {$count} supplier quote(s).");

        return self::SUCCESS;
    }
}
