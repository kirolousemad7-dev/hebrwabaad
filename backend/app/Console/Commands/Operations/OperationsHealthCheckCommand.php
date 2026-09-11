<?php

namespace App\Console\Commands\Operations;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\DelayedNotification;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\WebhookDelivery;
use App\Models\WorkflowAutomationRun;
use App\Services\Payments\PaymentProviderManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('operations:health-check {--strict : Exit non-zero when failures or backlog exist}')]
#[Description('Report operations health: delayed notifications, failed webhooks/automations, escalation, scheduler')]
class OperationsHealthCheckCommand extends Command
{
    public function handle(PaymentProviderManager $paymentProviders): int
    {
        $since = now()->subDay();

        $delayedPending = DelayedNotification::query()
            ->whereNull('delivered_at')
            ->count();

        $failedWebhooks24h = WebhookDelivery::query()
            ->where('status', WebhookDelivery::STATUS_FAILED)
            ->where(function ($query) use ($since): void {
                $query->where('failed_at', '>=', $since)
                    ->orWhere(function ($inner) use ($since): void {
                        $inner->whereNull('failed_at')->where('created_at', '>=', $since);
                    });
            })
            ->count();

        $failedAutomations24h = WorkflowAutomationRun::query()
            ->where('status', WorkflowRunStatus::Failed->value)
            ->where('created_at', '>=', $since)
            ->count();

        $paytabsConfigured = $paymentProviders->paytabsAvailable();
        $paytabsConfig = $paymentProviders->paytabsConfiguration();

        $pendingProcessing = Payment::query()
            ->where('payment_method', PaymentMethod::Card->value)
            ->where('status', PaymentStatus::Processing->value)
            ->count();

        $refundsPending = PaymentRefund::query()
            ->whereIn('status', [
                PaymentRefundStatus::Pending->value,
                PaymentRefundStatus::Processing->value,
            ])
            ->count();

        $mailSkipped24h = 0;
        $mailFailed24h = 0;
        if (Schema::hasTable('customer_communication_logs')) {
            $mailSkipped24h = (int) DB::table('customer_communication_logs')
                ->where('channel', 'email')
                ->where('status', 'skipped')
                ->where('created_at', '>=', $since)
                ->count();
            $mailFailed24h = (int) DB::table('customer_communication_logs')
                ->where('channel', 'email')
                ->where('status', 'failed')
                ->where('created_at', '>=', $since)
                ->count();
        }

        $lastEscalation = Cache::get('operations.last_escalation_run');
        $lastEscalationLabel = is_string($lastEscalation) && $lastEscalation !== ''
            ? $lastEscalation
            : (is_object($lastEscalation) && method_exists($lastEscalation, 'toIso8601String')
                ? $lastEscalation->toIso8601String()
                : 'unknown (scheduled hourly via operations:process-escalations)');

        $this->table(
            ['Metric', 'Value'],
            [
                ['delayed_notifications_pending', (string) $delayedPending],
                ['failed_webhooks_24h', (string) $failedWebhooks24h],
                ['failed_automations_24h', (string) $failedAutomations24h],
                ['paytabs_configured', $paytabsConfigured ? 'yes' : 'no'],
                ['paytabs_environment', (string) ($paytabsConfig['environment'] ?? '')],
                ['paytabs_profile_hint', (string) ($paytabsConfig['profile_id_hint'] ?? '')],
                ['paytabs_issues', $paytabsConfig['issues'] === [] ? 'none' : implode('; ', $paytabsConfig['issues'])],
                ['pending_processing_payments', (string) $pendingProcessing],
                ['refunds_pending', (string) $refundsPending],
                ['mail_skipped_24h', (string) $mailSkipped24h],
                ['mail_failed_24h', (string) $mailFailed24h],
                ['last_escalation_run', (string) $lastEscalationLabel],
                ['scheduler_note', 'Ensure schedule:work/cron runs calendar, escalations, delayed notifications, webhooks:process-deliveries, payments:reconcile-pending'],
            ],
        );

        $hasIssues = $failedWebhooks24h > 0
            || $failedAutomations24h > 0
            || $delayedPending > 100
            || $mailFailed24h > 0
            || $pendingProcessing > 50;

        if ($this->option('strict') && $hasIssues) {
            $this->error('Health check failed (--strict).');

            return self::FAILURE;
        }

        $this->info('Health check complete.');

        return self::SUCCESS;
    }
}
