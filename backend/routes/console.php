<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('calendar:dispatch-reminders')->everyMinute()->withoutOverlapping(5);
Schedule::command('tasks:dispatch-reminders')->everyMinute()->withoutOverlapping(5);
Schedule::command('calendar:daily-digest')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('workflows:run-scheduled-triggers')->dailyAt('06:30')->withoutOverlapping();
Schedule::command('operations:process-escalations')->hourly()->withoutOverlapping();
Schedule::command('operations:deliver-delayed-notifications')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('webhooks:process-deliveries')->everyMinute()->withoutOverlapping();
Schedule::command('printing:expire-quotations')->hourly()->withoutOverlapping();
Schedule::command('quotations:expire-commercial')->hourly()->withoutOverlapping();
Schedule::command('quotations:expire-supplier-quotes')->hourly()->withoutOverlapping();
Schedule::command('payments:reconcile-pending')->hourly()->withoutOverlapping();
Schedule::command('blog:publish-scheduled')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('otp:prune-expired')->daily()->withoutOverlapping();
Schedule::command('invoices:mark-overdue')->daily()->withoutOverlapping();
