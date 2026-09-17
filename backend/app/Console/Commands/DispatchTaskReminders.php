<?php

namespace App\Console\Commands;

use App\Services\GoogleCalendar\TaskReminderService;
use Illuminate\Console\Command;

class DispatchTaskReminders extends Command
{
    protected $signature = 'tasks:dispatch-reminders';

    protected $description = 'Send due task reminder notifications (in-app/email/SMS when configured)';

    public function handle(TaskReminderService $reminders): int
    {
        $sent = $reminders->dispatchDue();
        $this->info("Sent {$sent} task reminder notification(s).");

        return self::SUCCESS;
    }
}
