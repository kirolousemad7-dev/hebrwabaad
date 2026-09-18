<?php

namespace App\Console\Commands;

use App\Services\Quotes\CommercialQuotationService;
use Illuminate\Console\Command;

class ExpireCommercialQuotations extends Command
{
    protected $signature = 'quotations:expire-commercial';

    protected $description = 'Expire sent/viewed commercial quotations past their valid_until date';

    public function handle(CommercialQuotationService $quotations): int
    {
        $count = $quotations->expireDue();
        $this->info("Expired {$count} commercial quotation(s).");

        return self::SUCCESS;
    }
}
