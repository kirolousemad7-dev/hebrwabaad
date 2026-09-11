<?php

namespace App\Console\Commands;

use App\Services\Operations\EscalationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessOperationalEscalations extends Command
{
    protected $signature = 'operations:process-escalations';

    protected $description = 'Advance overdue operational escalation levels for tasks, projects, and printing';

    public function handle(EscalationService $escalations): int
    {
        $result = $escalations->processOverdue();

        Cache::put('operations.last_escalation_run', now()->toIso8601String(), now()->addDays(7));

        $this->info(sprintf(
            'Escalations processed — calendar:%d workspace:%d projects:%d printing:%d',
            $result['calendar'],
            $result['workspace'],
            $result['projects'],
            $result['printing'],
        ));

        return self::SUCCESS;
    }
}
