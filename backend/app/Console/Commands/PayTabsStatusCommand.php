<?php

namespace App\Console\Commands;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Services\Payments\PayTabsConfigurationValidator;
use Illuminate\Console\Command;

class PayTabsStatusCommand extends Command
{
    protected $signature = 'payments:paytabs-status';

    protected $description = 'Report PayTabs configuration and pending card payment health (read-only; no charges or refunds)';

    public function handle(PayTabsConfigurationValidator $validator): int
    {
        $status = $validator->status();

        $this->info('PayTabs configuration (secrets redacted)');
        $this->table(
            ['Field', 'Value'],
            [
                ['integration', (string) ($status['integration'] ?? 'hosted_payment_page')],
                ['configured', $status['configured'] ? 'yes' : 'no'],
                ['enabled', $status['enabled'] ? 'yes' : 'no'],
                ['mode', (string) $status['environment']],
                ['region', (string) ($status['region'] ?? '')],
                ['currency', (string) ($status['currency'] ?? '')],
                ['base_host', (string) ($status['base_host'] ?? '')],
                ['has_profile_id', ($status['has_profile_id'] ?? false) ? 'yes' : 'no'],
                ['profile_id_hint', (string) ($status['profile_id_hint'] ?? '')],
                ['has_server_key', $status['has_server_key'] ? 'yes' : 'no'],
                ['has_merchant_id', ($status['has_merchant_id'] ?? false) ? 'yes' : 'no'],
                ['has_public_key', ($status['has_public_key'] ?? false) ? 'yes' : 'no'],
                ['has_secret_key', ($status['has_secret_key'] ?? false) ? 'yes' : 'no'],
                ['callback_url_ok', $status['callback_url_ok'] ? 'yes' : 'no'],
                ['return_url_ok', $status['return_url_ok'] ? 'yes' : 'no'],
            ],
        );

        if ($status['issues'] !== []) {
            $this->warn('Issues:');
            foreach ($status['issues'] as $issue) {
                $this->line(' - '.$issue);
            }
        }

        $processing = Payment::query()
            ->where('payment_method', PaymentMethod::Card->value)
            ->where('status', PaymentStatus::Processing->value)
            ->count();

        $recentFailures = Payment::query()
            ->where('payment_method', PaymentMethod::Card->value)
            ->where('status', PaymentStatus::Failed->value)
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        $failedAttempts = PaymentAttempt::query()
            ->where('status', PaymentAttemptStatus::Failed->value)
            ->where('failed_at', '>=', now()->subDay())
            ->count();

        $this->newLine();
        $this->info('Payment health (last 24h where applicable)');
        $this->table(
            ['Metric', 'Count'],
            [
                ['processing_card_payments', (string) $processing],
                ['failed_card_payments_24h', (string) $recentFailures],
                ['failed_attempts_24h', (string) $failedAttempts],
            ],
        );

        $this->comment('Read-only: no PayTabs charge, refund, or mutate calls were made.');

        return self::SUCCESS;
    }
}
