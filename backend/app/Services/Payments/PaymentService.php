<?php

namespace App\Services\Payments;

use App\Enums\CommercialQuotationStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingQuotationStatus;
use App\Enums\UserRole;
use App\Models\CommercialQuotation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentSetting;
use App\Models\PrintingQuotation;
use App\Models\User;
use App\Services\PlatformNotifier;
use App\Services\Printing\PrintingQuotationService;
use App\Services\Quotes\CommercialQuotationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentService
{
    public function __construct(
        private readonly OrderPayableResolver $payable,
        private readonly CardPaymentGateway $cards,
        private readonly PayTabsClient $paytabs,
        private readonly PlatformNotifier $notifier,
        private readonly PrintingQuotationService $printingQuotations,
        private readonly CommercialQuotationService $commercialQuotations,
        private readonly PaymentStatusTransitionService $statusTransitions,
        private readonly PayTabsCallbackMetrics $callbackMetrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payablePayload(Order $order): array
    {
        $quote = $this->payable->resolve($order);
        $latest = $order->latestPayment;

        return [
            'available' => $quote !== null && ($latest === null || ! $latest->status->countsAsRevenue()),
            'amount' => $quote?->amount,
            'currency' => $quote?->currency,
            'reason' => $quote === null ? $this->payable->unavailableReason($order) : null,
        ];
    }

    /**
     * Payment instructions the customer may read while settling their own order.
     * Owner-entered values only; gateway credentials never appear here.
     *
     * @return array<string, mixed>
     */
    public function customerSettings(): array
    {
        $settings = PaymentSetting::current();
        $instapayVisible = $settings->instapayReady();
        $bankVisible = $settings->bankTransferReady();

        return [
            'card' => [
                'enabled' => $settings->card_enabled,
                'configured' => $this->cards->isConfigured(),
            ],
            'instapay' => [
                'enabled' => $settings->instapay_enabled,
                'ready' => $instapayVisible,
                'account_name' => $instapayVisible ? $settings->instapay_account_name : null,
                'bank_name' => $instapayVisible ? $settings->instapay_bank_name : null,
                'account_number' => $instapayVisible ? $settings->instapay_account_number : null,
                'handle' => $instapayVisible ? $settings->instapay_handle : null,
                'phone' => $instapayVisible ? $settings->instapay_phone : null,
                'instructions' => $instapayVisible ? $settings->instapay_instructions : null,
                'notes' => $instapayVisible ? $settings->instapay_notes : null,
            ],
            'bank_transfer' => [
                'enabled' => $settings->bank_transfer_enabled,
                'ready' => $bankVisible,
                'bank_name' => $bankVisible ? $settings->bank_name : null,
                'account_name' => $bankVisible ? $settings->bank_account_name : null,
                'account_number' => $bankVisible ? $settings->bank_account_number : null,
                'iban' => $bankVisible ? $settings->bank_iban : null,
                'swift' => $bankVisible ? $settings->bank_swift : null,
                'branch' => $bankVisible ? $settings->bank_branch : null,
                'instructions' => $bankVisible ? $settings->bank_instructions : null,
                'notes' => $bankVisible ? $settings->bank_notes : null,
            ],
        ];
    }

    /**
     * Owner-only configuration payload. The card gateway is reported as a status
     * only; PayTabs credentials stay in server environment configuration.
     *
     * @return array<string, mixed>
     */
    public function ownerSettings(): array
    {
        $settings = PaymentSetting::current();

        return [
            'card_enabled' => $settings->card_enabled,
            'card_configured' => $this->cards->isConfigured(),
            'card_provider' => 'PayTabs',
            'card_environment' => strtoupper((string) config('payments.paytabs.environment', 'test')),
            'instapay_enabled' => $settings->instapay_enabled,
            'instapay_ready' => $settings->instapayReady(),
            'instapay_account_name' => $settings->instapay_account_name,
            'instapay_bank_name' => $settings->instapay_bank_name,
            'instapay_account_number' => $settings->instapay_account_number,
            'instapay_handle' => $settings->instapay_handle,
            'instapay_phone' => $settings->instapay_phone,
            'instapay_instructions' => $settings->instapay_instructions,
            'instapay_notes' => $settings->instapay_notes,
            'bank_transfer_enabled' => $settings->bank_transfer_enabled,
            'bank_transfer_ready' => $settings->bankTransferReady(),
            'bank_name' => $settings->bank_name,
            'bank_account_name' => $settings->bank_account_name,
            'bank_account_number' => $settings->bank_account_number,
            'bank_iban' => $settings->bank_iban,
            'bank_swift' => $settings->bank_swift,
            'bank_branch' => $settings->bank_branch,
            'bank_instructions' => $settings->bank_instructions,
            'bank_notes' => $settings->bank_notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function updateSettings(array $attributes): array
    {
        $settings = PaymentSetting::current();
        $settings->fill($attributes);
        $settings->save();

        return $this->ownerSettings();
    }

    /**
     * Create a payment linked to a printing quotation (manual or card).
     *
     * @param  array<string, mixed>  $data
     * @return array{payment: Payment, checkout_url: string|null}
     */
    public function createForPrintingQuotation(User $actor, PrintingQuotation $quotation, array $data): array
    {
        return DB::transaction(function () use ($actor, $quotation, $data) {
            /** @var PrintingQuotation $locked */
            $locked = PrintingQuotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            $method = $data['method'] instanceof PaymentMethod
                ? $data['method']
                : PaymentMethod::from((string) $data['method']);

            if ($method === PaymentMethod::Card) {
                if (! $this->cards->isConfigured()) {
                    Log::warning('paytabs.not_configured', ['printing_quotation_id' => $locked->id]);
                    throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
                }

                $this->assertMethodEnabled($method);

                $amount = $this->resolvePrintingPaymentAmount($locked, $data);

                $payment = Payment::query()->create([
                    'customer_id' => $locked->customer_id,
                    'order_id' => null,
                    'printing_quotation_id' => $locked->id,
                    'amount' => $amount,
                    'currency' => $locked->currency,
                    'payment_method' => PaymentMethod::Card,
                    'status' => PaymentStatus::Processing,
                    'provider' => PaymentMethod::Card->provider(),
                    'notes' => $data['notes'] ?? null,
                    'reference_number' => $data['reference_number'] ?? null,
                    'payer_name' => $data['payer_name'] ?? null,
                ]);

                return $this->startCardCheckout($payment);
            }

            if (! $method->isManual()) {
                throw ValidationException::withMessages([
                    'method' => ['Unsupported payment method.'],
                ]);
            }

            $this->assertMethodEnabled($method);

            $amount = $this->resolvePrintingPaymentAmount($locked, $data);

            $payment = Payment::query()->create([
                'customer_id' => $locked->customer_id,
                'order_id' => null,
                'printing_quotation_id' => $locked->id,
                'amount' => $amount,
                'currency' => $locked->currency,
                'payment_method' => $method,
                'status' => ! empty($data['mark_paid']) ? PaymentStatus::Paid : PaymentStatus::Pending,
                'provider' => $method->provider(),
                'notes' => $data['notes'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'payer_name' => $data['payer_name'] ?? null,
                'paid_at' => ! empty($data['mark_paid']) ? now() : null,
                'verified_at' => ! empty($data['mark_paid']) ? now() : null,
                'verified_by' => ! empty($data['mark_paid']) ? $actor->id : null,
            ]);

            if (! empty($data['mark_paid'])) {
                $fresh = $this->load($payment->fresh() ?? $payment);
                $this->notifier->paymentPaid($fresh);
                $this->afterPrintingPaymentPaid($locked, $actor);

                return ['payment' => $fresh, 'checkout_url' => null];
            }

            return ['payment' => $this->load($payment), 'checkout_url' => null];
        });
    }

    /**
     * Public PayTabs checkout for an accepted printing quotation.
     * Amount is always computed server-side; client amount fields are ignored.
     *
     * @return array{payment_id: int, checkout_url: string, amount: string, currency: string}
     */
    public function createPublicPrintingCheckout(PrintingQuotation $quotation): array
    {
        if (! $this->cards->isConfigured()) {
            Log::warning('paytabs.not_configured', ['printing_quotation_id' => $quotation->id]);
            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }

        return DB::transaction(function () use ($quotation) {
            /** @var PrintingQuotation $locked */
            $locked = PrintingQuotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            $status = $locked->status instanceof PrintingQuotationStatus
                ? $locked->status
                : PrintingQuotationStatus::from((string) $locked->status);

            if ($status !== PrintingQuotationStatus::Accepted) {
                throw ValidationException::withMessages([
                    'token' => ['Only accepted quotations can be paid online.'],
                ]);
            }

            $this->assertMethodEnabled(PaymentMethod::Card);

            $amount = $this->printingCheckoutAmount($locked);

            if (bccomp($amount, '0', 2) < 1) {
                throw ValidationException::withMessages([
                    'amount' => ['No outstanding balance to pay.'],
                ]);
            }

            $payment = Payment::query()->create([
                'customer_id' => $locked->customer_id,
                'order_id' => null,
                'printing_quotation_id' => $locked->id,
                'amount' => $amount,
                'currency' => $locked->currency,
                'payment_method' => PaymentMethod::Card,
                'status' => PaymentStatus::Processing,
                'provider' => PaymentMethod::Card->provider(),
            ]);

            $result = $this->startCardCheckout($payment);

            $this->printingQuotations->recordEvent($locked, 'checkout_created', null, 'customer', [
                'payment_id' => (int) $result['payment']->id,
                'amount' => number_format((float) $result['payment']->amount, 2, '.', ''),
                'currency' => (string) $result['payment']->currency,
            ]);

            return [
                'payment_id' => (int) $result['payment']->id,
                'checkout_url' => (string) $result['checkout_url'],
                'amount' => number_format((float) $result['payment']->amount, 2, '.', ''),
                'currency' => (string) $result['payment']->currency,
            ];
        });
    }

    /**
     * Outstanding card checkout amount for a printing quotation (deposit or remaining).
     */
    public function printingCheckoutAmount(PrintingQuotation $quotation): string
    {
        $summary = $this->printingQuotations->paymentSummary($quotation);
        $policy = $quotation->payment_policy instanceof PrintingPaymentPolicy
            ? $quotation->payment_policy
            : PrintingPaymentPolicy::from((string) ($summary['payment_policy'] ?? PrintingPaymentPolicy::Full->value));

        if ($policy === PrintingPaymentPolicy::Deposit && $summary['deposit_required'] !== null) {
            if (bccomp($summary['paid'], $summary['deposit_required'], 2) >= 0) {
                return '0.00';
            }

            $needed = bcsub($summary['deposit_required'], $summary['paid'], 2);

            return bccomp($needed, $summary['remaining'], 2) === 1
                ? $summary['remaining']
                : $needed;
        }

        return $summary['remaining'];
    }

    /**
     * Public status poll — status only; never marks PAID.
     *
     * @return array{status: string, payment_id: int}
     */
    public function publicPrintingPaymentStatus(Payment $payment, string $rawQuoteToken): array
    {
        if ($payment->printing_quotation_id === null) {
            throw ValidationException::withMessages([
                'token' => ['Payment not found.'],
            ]);
        }

        $quotation = PrintingQuotation::findByRawToken($rawQuoteToken);

        if ($quotation === null || (int) $quotation->id !== (int) $payment->printing_quotation_id) {
            throw ValidationException::withMessages([
                'token' => ['Payment not found.'],
            ]);
        }

        $status = $payment->status instanceof PaymentStatus
            ? $payment->status
            : PaymentStatus::from((string) $payment->status);

        return [
            'payment_id' => (int) $payment->id,
            'status' => $status->value,
        ];
    }

    /**
     * @return array{payment_id: int, checkout_url: string, amount: string, currency: string}
     */
    public function createPublicCommercialCheckout(CommercialQuotation $quotation): array
    {
        if (! $this->cards->isConfigured()) {
            Log::warning('paytabs.not_configured', ['commercial_quotation_id' => $quotation->id]);
            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }

        return DB::transaction(function () use ($quotation) {
            /** @var CommercialQuotation $locked */
            $locked = CommercialQuotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            $status = $locked->status instanceof CommercialQuotationStatus
                ? $locked->status
                : CommercialQuotationStatus::from((string) $locked->status);

            if ($status !== CommercialQuotationStatus::Accepted) {
                throw ValidationException::withMessages([
                    'token' => ['Only accepted quotations can be paid online.'],
                ]);
            }

            $this->assertMethodEnabled(PaymentMethod::Card);

            $amount = $this->commercialCheckoutAmount($locked);

            if (bccomp($amount, '0', 2) < 1) {
                throw ValidationException::withMessages([
                    'amount' => ['No outstanding balance to pay.'],
                ]);
            }

            $payment = Payment::query()->create([
                'customer_id' => $locked->customer_id,
                'order_id' => $locked->order_id,
                'commercial_quotation_id' => $locked->id,
                'amount' => $amount,
                'currency' => $locked->currency,
                'payment_method' => PaymentMethod::Card,
                'status' => PaymentStatus::Processing,
                'provider' => PaymentMethod::Card->provider(),
            ]);

            $result = $this->startCardCheckout($payment);

            $this->commercialQuotations->recordEvent($locked, 'checkout_created', null, 'customer', [
                'payment_id' => (int) $result['payment']->id,
                'amount' => number_format((float) $result['payment']->amount, 2, '.', ''),
                'currency' => (string) $result['payment']->currency,
            ]);

            return [
                'payment_id' => (int) $result['payment']->id,
                'checkout_url' => (string) $result['checkout_url'],
                'amount' => number_format((float) $result['payment']->amount, 2, '.', ''),
                'currency' => (string) $result['payment']->currency,
            ];
        });
    }

    public function commercialCheckoutAmount(CommercialQuotation $quotation): string
    {
        $summary = $this->commercialQuotations->paymentSummary($quotation);
        $policy = $quotation->payment_policy instanceof PrintingPaymentPolicy
            ? $quotation->payment_policy
            : PrintingPaymentPolicy::from((string) ($summary['payment_policy'] ?? PrintingPaymentPolicy::Full->value));

        if ($policy === PrintingPaymentPolicy::Deposit && $summary['deposit_required'] !== null) {
            if (bccomp($summary['paid'], $summary['deposit_required'], 2) >= 0) {
                return '0.00';
            }

            $needed = bcsub($summary['deposit_required'], $summary['paid'], 2);

            return bccomp($needed, $summary['remaining'], 2) === 1
                ? $summary['remaining']
                : $needed;
        }

        if ($policy === PrintingPaymentPolicy::None) {
            return '0.00';
        }

        return $summary['amount_due_now'] ?? $summary['remaining'];
    }

    public function publicCommercialPaymentStatus(Payment $payment, string $rawQuoteToken): array
    {
        if ($payment->commercial_quotation_id === null) {
            throw ValidationException::withMessages([
                'token' => ['Payment not found.'],
            ]);
        }

        $quotation = CommercialQuotation::findByRawToken($rawQuoteToken);

        if ($quotation === null || (int) $quotation->id !== (int) $payment->commercial_quotation_id) {
            throw ValidationException::withMessages([
                'token' => ['Payment not found.'],
            ]);
        }

        $status = $payment->status instanceof PaymentStatus
            ? $payment->status
            : PaymentStatus::from((string) $payment->status);

        return [
            'payment_id' => (int) $payment->id,
            'status' => $status->value,
        ];
    }

    public function reconcileWithProvider(User $actor, Payment $payment): Payment
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canManagePayments()) {
            throw ValidationException::withMessages([
                'payment' => ['You cannot reconcile payments.'],
            ]);
        }

        return $this->reconcilePayment($payment);
    }

    /**
     * Reconcile pending/processing card payments older than $minutes. Bounded to $limit rows.
     */
    public function reconcilePendingCardPayments(int $minutes = 30, int $limit = 50): int
    {
        $cutoff = now()->subMinutes(max(1, $minutes));
        $limit = max(1, min($limit, 50));

        $payments = Payment::query()
            ->where('payment_method', PaymentMethod::Card)
            ->whereIn('status', [PaymentStatus::Processing->value, PaymentStatus::Pending->value])
            ->whereNotNull('provider_transaction_id')
            ->where('provider_transaction_id', '!=', '')
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $reconciled = 0;

        foreach ($payments as $payment) {
            try {
                $this->reconcilePayment($payment);
                $reconciled++;
            } catch (\Throwable $exception) {
                Log::warning('paytabs.reconcile_failed', [
                    'payment_id' => $payment->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $reconciled;
    }

    /**
     * @return array{payment: Payment, checkout_url: string|null}
     */
    public function createForCustomer(User $customer, Order $order, PaymentMethod $method): array
    {
        return DB::transaction(function () use ($customer, $order, $method) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->customer_id !== $customer->id) {
                throw ValidationException::withMessages([
                    'order_id' => ['You cannot pay this order.'],
                ]);
            }

            $this->assertOrderNotPaid($locked);
            $existing = $this->openPaymentFor($locked, $method);

            if ($existing !== null) {
                return $this->resumeOpenPayment($existing, $method);
            }

            $quote = $this->payable->resolve($locked);

            if ($quote === null) {
                throw ValidationException::withMessages([
                    'order_id' => ['This order has no payable catalog amount.'],
                ]);
            }

            $this->assertMethodEnabled($method);

            $payment = Payment::query()->create([
                'customer_id' => $customer->id,
                'order_id' => $locked->id,
                'amount' => $quote->amount,
                'currency' => $quote->currency,
                'payment_method' => $method,
                'status' => $method === PaymentMethod::Card
                    ? PaymentStatus::Processing
                    : PaymentStatus::Pending,
                'provider' => $method->provider(),
            ]);

            if ($method === PaymentMethod::Card) {
                return $this->startCardCheckout($payment);
            }

            return ['payment' => $this->load($payment), 'checkout_url' => null];
        });
    }

    /**
     * @return array{payment: Payment, checkout_url: string}
     */
    public function startCardForCustomer(User $customer, Payment $payment): array
    {
        if ($payment->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'payment' => ['You cannot pay this order.'],
            ]);
        }

        if ($payment->payment_method !== PaymentMethod::Card) {
            throw ValidationException::withMessages([
                'payment' => ['This payment is not a card payment.'],
            ]);
        }

        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Processing, PaymentStatus::Failed, PaymentStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'payment' => ['This card payment cannot be started.'],
            ]);
        }

        $this->assertOrderNotPaid($payment->order ?? $payment->order()->firstOrFail(), $payment->id);

        return $this->startCardCheckout($payment);
    }

    /**
     * Customer-declared transfer details for a manual method (InstaPay / bank transfer).
     */
    public function submitManualTransfer(User $customer, Payment $payment, string $reference, ?string $payerName = null, ?string $notes = null): Payment
    {
        if ($payment->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'payment' => ['You cannot pay this order.'],
            ]);
        }

        $method = $payment->payment_method;

        if (! $method instanceof PaymentMethod || ! $method->isManual()) {
            throw ValidationException::withMessages([
                'payment' => ['This payment is not a manual transfer.'],
            ]);
        }

        if (! in_array($payment->status, [PaymentStatus::Pending], true)) {
            throw ValidationException::withMessages([
                'payment' => ['Transfer details can only be submitted while pending.'],
            ]);
        }

        $this->assertMethodEnabled($method);
        $this->transition($payment, PaymentStatus::PendingVerification);

        $payment->update([
            'status' => PaymentStatus::PendingVerification,
            'reference_number' => $reference,
            'payer_name' => $payerName,
            'notes' => $notes,
            'failure_reason' => null,
        ]);

        $fresh = $this->load($payment->fresh());
        $this->notifier->manualTransferSubmitted($fresh);

        return $fresh;
    }

    public function approve(User $owner, Payment $payment): Payment
    {
        if (! $this->isManualPayment($payment)) {
            throw ValidationException::withMessages([
                'payment' => ['Only manual transfers can be verified manually.'],
            ]);
        }

        $this->transition($payment, PaymentStatus::Paid);

        $payment->update([
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'verified_at' => now(),
            'verified_by' => $owner->id,
            'failure_reason' => null,
        ]);

        $fresh = $this->load($payment->fresh());
        $this->notifier->paymentPaid($fresh);
        $this->afterPrintingPaymentPaidFromPayment($fresh, $owner);

        return $fresh;
    }

    /**
     * System confirm for signed inbound webhooks (Phase 6N).
     * Only PENDING/PROCESSING payments linked to a printing quotation.
     */
    public function confirmFromInboundWebhook(int $paymentId, ?string $providerTransactionId = null): Payment
    {
        return DB::transaction(function () use ($paymentId, $providerTransactionId) {
            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->firstOrFail();

            if ($payment->printing_quotation_id === null) {
                throw ValidationException::withMessages([
                    'payment_id' => ['Payment must belong to a printing quotation.'],
                ]);
            }

            $status = $payment->status instanceof PaymentStatus
                ? $payment->status
                : PaymentStatus::from((string) $payment->status);

            if ($status === PaymentStatus::Paid) {
                return $this->load($payment);
            }

            if (! in_array($status, [PaymentStatus::Pending, PaymentStatus::Processing], true)) {
                throw ValidationException::withMessages([
                    'payment_id' => ['Payment must be PENDING or PROCESSING.'],
                ]);
            }

            if ($status === PaymentStatus::Pending) {
                $this->transition($payment, PaymentStatus::Processing);
                $payment->status = PaymentStatus::Processing;
            }

            $this->transition($payment, PaymentStatus::Paid);

            $payment->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'verified_at' => now(),
                'verified_by' => null,
                'provider_transaction_id' => $providerTransactionId ?: $payment->provider_transaction_id,
                'failure_reason' => null,
            ]);

            $fresh = $this->load($payment->fresh());
            $this->notifier->paymentPaid($fresh);
            $this->afterPrintingPaymentPaidFromPayment($fresh, null);

            return $fresh;
        });
    }

    public function reject(User $owner, Payment $payment, string $reason): Payment
    {
        if (! $this->isManualPayment($payment)) {
            throw ValidationException::withMessages([
                'payment' => ['Only manual transfers can be rejected manually.'],
            ]);
        }

        $this->transition($payment, PaymentStatus::Rejected);

        $payment->update([
            'status' => PaymentStatus::Rejected,
            'verified_at' => now(),
            'verified_by' => $owner->id,
            'failure_reason' => $reason,
        ]);

        $fresh = $this->load($payment->fresh());
        $this->notifier->manualTransferRejected($fresh);

        return $fresh;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyPayTabsCallback(array $payload): void
    {
        $this->callbackMetrics->increment('callback_received');

        $tranRef = trim((string) ($payload['tran_ref'] ?? ''));

        if ($tranRef === '') {
            $this->callbackMetrics->increment('rejected');

            return;
        }

        $verifiedPayload = $this->paytabs->queryTransaction($tranRef);
        $verified = PayTabsVerifiedTransaction::fromPayTabsPayload($verifiedPayload);

        if ($verified === null) {
            Log::warning('paytabs.verification_incomplete');
            $this->callbackMetrics->increment('rejected');

            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }

        $payment = $this->findPaymentFromPayTabs($verified, $payload);

        if ($payment === null) {
            Log::warning('paytabs.payment_not_found');
            $this->callbackMetrics->increment('rejected');

            return;
        }

        if ($payment->payment_method !== PaymentMethod::Card) {
            $this->callbackMetrics->increment('rejected');

            return;
        }

        $this->applyVerifiedPayTabsTransaction($payment, $verified);
    }

    private function reconcilePayment(Payment $payment): Payment
    {
        if ($payment->payment_method !== PaymentMethod::Card) {
            throw ValidationException::withMessages([
                'payment' => ['Only card payments can be reconciled with PayTabs.'],
            ]);
        }

        $tranRef = trim((string) ($payment->provider_transaction_id ?? ''));

        if ($tranRef === '') {
            throw ValidationException::withMessages([
                'payment' => ['Payment has no provider transaction reference.'],
            ]);
        }

        if (! $this->cards->isConfigured()) {
            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }

        $verifiedPayload = $this->paytabs->queryTransaction($tranRef);
        $verified = PayTabsVerifiedTransaction::fromPayTabsPayload($verifiedPayload);

        if ($verified === null) {
            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }

        $this->applyVerifiedPayTabsTransaction($payment, $verified, true);

        return $this->load($payment->fresh() ?? $payment);
    }

    private function applyVerifiedPayTabsTransaction(
        Payment $payment,
        PayTabsVerifiedTransaction $verified,
        bool $fromReconcile = false,
    ): void {
        DB::transaction(function () use ($payment, $verified, $fromReconcile): void {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Paid) {
                if (! $fromReconcile) {
                    $this->callbackMetrics->increment('duplicate');
                }

                if ($fromReconcile) {
                    $locked->update([
                        'last_reconciled_at' => now(),
                        'reconciliation_note' => 'already_paid',
                        'provider_status' => $verified->responseStatus,
                    ]);
                }

                return;
            }

            $mismatch = $verified->mismatchReason($locked, $this->paytabs->profileId());

            if ($mismatch !== null) {
                Log::warning('paytabs.verification_mismatch', [
                    'payment_id' => $locked->id,
                    'reason' => $mismatch,
                ]);
                if (! $fromReconcile) {
                    $this->callbackMetrics->increment('mismatch');
                }
                $this->failOrCancelCard($locked, PaymentStatus::Failed, 'Payment verification failed.');
                $locked->refresh();
                $locked->update([
                    'last_reconciled_at' => $fromReconcile ? now() : $locked->last_reconciled_at,
                    'reconciliation_note' => 'mismatch:'.$mismatch,
                    'provider_status' => $verified->responseStatus,
                ]);

                return;
            }

            $next = $verified->mappedStatus();

            if ($next === PaymentStatus::Paid) {
                $this->completeCardPayment($locked, $verified->tranRef);
                if (! $fromReconcile) {
                    $this->callbackMetrics->increment('verified');
                }
                $locked->refresh();
                if ($fromReconcile) {
                    $locked->update([
                        'last_reconciled_at' => now(),
                        'reconciliation_note' => 'reconciled_paid',
                        'provider_status' => $verified->responseStatus,
                    ]);
                }

                return;
            }

            if ($next === PaymentStatus::Failed || $next === PaymentStatus::Cancelled) {
                $this->failOrCancelCard($locked, $next, $next === PaymentStatus::Cancelled
                    ? 'Card payment cancelled.'
                    : 'Card payment failed.');
                if (! $fromReconcile) {
                    $this->callbackMetrics->increment('rejected');
                }
                $locked->refresh();
                if ($fromReconcile) {
                    $locked->update([
                        'last_reconciled_at' => now(),
                        'reconciliation_note' => 'reconciled_'.$next->value,
                        'provider_status' => $verified->responseStatus,
                    ]);
                }

                return;
            }

            if ($fromReconcile) {
                $locked->update([
                    'last_reconciled_at' => now(),
                    'reconciliation_note' => 'pending_provider_status',
                    'provider_status' => $verified->responseStatus,
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePrintingPaymentAmount(PrintingQuotation $quotation, array $data): string
    {
        $summary = $this->printingQuotations->paymentSummary($quotation);
        $amount = isset($data['amount'])
            ? number_format((float) $data['amount'], 2, '.', '')
            : $summary['remaining'];

        if (bccomp($amount, '0', 2) < 1) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount must be greater than zero.'],
            ]);
        }

        if (bccomp($amount, $summary['remaining'], 2) === 1) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount exceeds the remaining balance.'],
            ]);
        }

        return $amount;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Payment>
     */
    public function paginateForOwner(array $filters): LengthAwarePaginator
    {
        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'amount', 'status', 'paid_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';

        $query = Payment::query()->with($this->eagerLoad());

        $search = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('reference_number', 'like', $term)
                    ->orWhere('provider_transaction_id', 'like', $term)
                    ->orWhere('checkout_session_id', 'like', $term)
                    ->orWhereHas('customer', function (Builder $customer) use ($term): void {
                        $customer->where('name', 'like', $term)->orWhere('email', 'like', $term);
                    })
                    ->orWhereHas('order', function (Builder $order) use ($term): void {
                        $order->where('reference', 'like', $term)->orWhere('title', 'like', $term);
                    });
            });
        }

        $status = $filters['status'] ?? null;
        if (is_string($status) && in_array($status, PaymentStatus::values(), true)) {
            $query->where('status', $status);
        }

        $method = $filters['payment_method'] ?? null;
        if (is_string($method) && in_array($method, PaymentMethod::values(), true)) {
            $query->where('payment_method', $method);
        }

        return $query
            ->orderBy($sort, $direction)
            ->paginate($this->perPage($filters));
    }

    /**
     * @return array<string, mixed>
     */
    public function revenueSummary(): array
    {
        $paid = Payment::query()->where('status', PaymentStatus::Paid);
        $count = (clone $paid)->count();

        if ($count === 0) {
            return [
                'available' => false,
                'value' => null,
                'reason' => 'no_recorded_revenue',
                'currency' => null,
                'paid_count' => 0,
                'pending_count' => Payment::query()->where('status', PaymentStatus::Pending)->count(),
                'pending_verification_count' => Payment::query()->where('status', PaymentStatus::PendingVerification)->count(),
                'failed_count' => Payment::query()->where('status', PaymentStatus::Failed)->count(),
                'rejected_count' => Payment::query()->where('status', PaymentStatus::Rejected)->count(),
            ];
        }

        $amount = (clone $paid)->sum('amount');
        $currency = (string) (clone $paid)->orderBy('id')->value('currency');

        return [
            'available' => true,
            'value' => round((float) $amount, 2),
            'reason' => null,
            'currency' => $currency !== '' ? $currency : 'SAR',
            'paid_count' => $count,
            'pending_count' => Payment::query()->where('status', PaymentStatus::Pending)->count(),
            'pending_verification_count' => Payment::query()->where('status', PaymentStatus::PendingVerification)->count(),
            'failed_count' => Payment::query()->where('status', PaymentStatus::Failed)->count(),
            'rejected_count' => Payment::query()->where('status', PaymentStatus::Rejected)->count(),
        ];
    }

    public function load(Payment $payment): Payment
    {
        return $payment->load($this->eagerLoad());
    }

    /**
     * @return list<string>
     */
    public function eagerLoad(): array
    {
        return ['customer', 'order.project', 'order.package', 'order.packageTier', 'order.service', 'verifier', 'printingQuotation'];
    }

    /**
     * @return array{payment: Payment, checkout_url: string}
     */
    private function startCardCheckout(Payment $payment): array
    {
        $this->assertMethodEnabled(PaymentMethod::Card);

        if (! $this->cards->isConfigured()) {
            Log::warning('paytabs.not_configured', ['payment_id' => $payment->id]);
            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }

        $returnUrl = $this->paytabs->returnUrl();
        $session = $this->cards->createCheckoutSession($payment, $returnUrl, $returnUrl);

        if (in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Failed, PaymentStatus::Cancelled], true)) {
            $this->transition($payment, PaymentStatus::Processing);
        }

        try {
            $payment->update([
                'status' => PaymentStatus::Processing,
                'provider' => PaymentMethod::Card->provider(),
                'checkout_session_id' => $session->sessionId,
                'provider_transaction_id' => $session->providerTransactionId ?: $session->sessionId,
                'failure_reason' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            Log::warning('paytabs.duplicate_provider_reference', ['payment_id' => $payment->id]);
            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }

        $this->recordCheckoutAttempt($payment->fresh() ?? $payment, $session);

        return [
            'payment' => $this->load($payment->fresh()),
            'checkout_url' => $session->url,
        ];
    }

    private function recordCheckoutAttempt(Payment $payment, CardCheckoutSession $session): void
    {
        PaymentAttempt::query()->create([
            'payment_id' => $payment->id,
            'provider' => $payment->provider ?: PaymentMethod::Card->provider(),
            'provider_reference' => filled($session->providerTransactionId)
                ? $session->providerTransactionId
                : null,
            'checkout_reference' => $session->sessionId,
            'amount' => $payment->amount,
            'currency' => strtoupper((string) $payment->currency),
            'status' => PaymentAttemptStatus::Redirected,
            'started_at' => now(),
            'metadata' => [
                'from' => 'STARTED',
                'to' => 'REDIRECTED',
            ],
        ]);
    }

    private function markLatestAttempt(Payment $payment, PaymentAttemptStatus $status, ?string $failureMessage = null): void
    {
        $attempt = PaymentAttempt::query()
            ->where('payment_id', $payment->id)
            ->where(function ($query) use ($payment): void {
                $tranRef = trim((string) ($payment->provider_transaction_id ?? ''));
                if ($tranRef !== '') {
                    $query->where('provider_reference', $tranRef);
                }
            })
            ->latest('id')
            ->first();

        if ($attempt === null) {
            $attempt = PaymentAttempt::query()
                ->where('payment_id', $payment->id)
                ->latest('id')
                ->first();
        }

        if ($attempt === null) {
            return;
        }

        $payload = ['status' => $status];
        if ($status === PaymentAttemptStatus::Verified) {
            $payload['verified_at'] = now();
            $payload['failure_code'] = null;
            $payload['failure_message'] = null;
        }
        if ($status === PaymentAttemptStatus::Failed) {
            $payload['failed_at'] = now();
            $payload['failure_message'] = $failureMessage;
        }

        $attempt->update($payload);
    }

    /**
     * @return array{payment: Payment, checkout_url: string|null}
     */
    private function resumeOpenPayment(Payment $payment, PaymentMethod $method): array
    {
        if ($method === PaymentMethod::Card) {
            return $this->startCardCheckout($payment);
        }

        return ['payment' => $this->load($payment), 'checkout_url' => null];
    }

    private function openPaymentFor(Order $order, PaymentMethod $method): ?Payment
    {
        return Payment::query()
            ->where('order_id', $order->id)
            ->where('payment_method', $method)
            ->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Processing->value,
                PaymentStatus::PendingVerification->value,
            ])
            ->latest('id')
            ->first();
    }

    private function assertOrderNotPaid(Order $order, ?int $exceptPaymentId = null): void
    {
        $query = Payment::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::Paid);

        if ($exceptPaymentId !== null) {
            $query->whereKeyNot($exceptPaymentId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'order_id' => ['This order is already paid.'],
            ]);
        }
    }

    private function assertMethodEnabled(PaymentMethod $method): void
    {
        $settings = PaymentSetting::current();

        if ($method === PaymentMethod::Card && ! $settings->card_enabled) {
            throw ValidationException::withMessages([
                'method' => ['Card payments are currently disabled.'],
            ]);
        }

        if ($method === PaymentMethod::Instapay && ! $settings->instapayReady()) {
            throw ValidationException::withMessages([
                'method' => ['InstaPay is not available yet.'],
            ]);
        }

        if ($method === PaymentMethod::BankTransfer && ! $settings->bankTransferReady()) {
            throw ValidationException::withMessages([
                'method' => ['Bank transfer is not available yet.'],
            ]);
        }
    }

    private function isManualPayment(Payment $payment): bool
    {
        $method = $payment->payment_method;

        return $method instanceof PaymentMethod && $method->isManual();
    }

    private function transition(Payment $payment, PaymentStatus $next): void
    {
        $this->statusTransitions->apply($payment, $next);
    }

    private function completeCardPayment(Payment $payment, string $tranRef): void
    {
        if ($payment->status === PaymentStatus::Paid) {
            return;
        }

        if ($payment->status !== PaymentStatus::Processing) {
            return;
        }

        $this->transition($payment, PaymentStatus::Paid);

        $payment->update([
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'provider_transaction_id' => $tranRef !== '' ? $tranRef : $payment->provider_transaction_id,
            'failure_reason' => null,
        ]);

        $this->markLatestAttempt($payment->fresh() ?? $payment, PaymentAttemptStatus::Verified);
        $this->notifier->paymentPaid($this->load($payment->fresh()));
        $this->afterPrintingPaymentPaidFromPayment($payment->fresh() ?? $payment, null);
    }

    private function afterPrintingPaymentPaidFromPayment(Payment $payment, ?User $actor): void
    {
        if ($payment->printing_quotation_id === null) {
            return;
        }

        $quotation = $payment->printingQuotation ?? PrintingQuotation::query()->find($payment->printing_quotation_id);
        if ($quotation === null) {
            return;
        }

        $this->afterPrintingPaymentPaid($quotation, $actor);
    }

    private function afterPrintingPaymentPaid(PrintingQuotation $quotation, ?User $actor): void
    {
        $fresh = $quotation->fresh() ?? $quotation;
        $paid = $fresh->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->latest('id')
            ->first();

        if ($paid !== null) {
            $this->printingQuotations->recordPaymentRecorded($fresh, [
                'amount' => $paid->amount,
                'currency' => $paid->currency,
                'method' => $paid->payment_method instanceof PaymentMethod
                    ? $paid->payment_method->value
                    : (string) $paid->payment_method,
                'paid_at' => $paid->paid_at?->toIso8601String() ?? now()->toIso8601String(),
            ], $actor);
            $this->printingQuotations->notifyPaymentConfirmed($fresh, $actor, $paid);
        }

        $this->printingQuotations->notifyPaymentRequirementMet($fresh, $actor);
    }

    private function failOrCancelCard(Payment $payment, PaymentStatus $next, string $reason): void
    {
        if ($payment->status === PaymentStatus::Paid) {
            return;
        }

        if ($payment->status !== PaymentStatus::Processing) {
            return;
        }

        $this->transition($payment, $next);

        $payment->update([
            'status' => $next,
            'failure_reason' => $reason,
        ]);

        if ($next === PaymentStatus::Failed) {
            $this->markLatestAttempt($payment->fresh() ?? $payment, PaymentAttemptStatus::Failed, $reason);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findPaymentFromPayTabs(PayTabsVerifiedTransaction $verified, array $payload): ?Payment
    {
        $byRef = Payment::query()->where('provider_transaction_id', $verified->tranRef)->first();
        if ($byRef !== null) {
            return $byRef;
        }

        $byCart = Payment::query()->where('checkout_session_id', $verified->cartId)->first();
        if ($byCart !== null) {
            return $byCart;
        }

        $callbackCartId = trim((string) ($payload['cart_id'] ?? ''));
        if ($callbackCartId !== '') {
            $byCallbackCart = Payment::query()->where('checkout_session_id', $callbackCartId)->first();
            if ($byCallbackCart !== null) {
                return $byCallbackCart;
            }
        }

        $paymentId = $this->paytabs->paymentIdFromCartId($verified->cartId);

        return $paymentId !== null ? Payment::query()->find($paymentId) : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return max(1, min($perPage, 50));
    }
}
