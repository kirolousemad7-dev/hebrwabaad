<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\PlatformNotifier;
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

        return $fresh;
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
        $tranRef = trim((string) ($payload['tran_ref'] ?? ''));

        if ($tranRef === '') {
            return;
        }

        $verifiedPayload = $this->paytabs->queryTransaction($tranRef);
        $verified = PayTabsVerifiedTransaction::fromPayTabsPayload($verifiedPayload);

        if ($verified === null) {
            Log::warning('paytabs.verification_incomplete');

            throw new HttpException(503, 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');
        }

        $payment = $this->findPaymentFromPayTabs($verified, $payload);

        if ($payment === null) {
            Log::warning('paytabs.payment_not_found');

            return;
        }

        if ($payment->payment_method !== PaymentMethod::Card) {
            return;
        }

        DB::transaction(function () use ($payment, $verified): void {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Paid) {
                return;
            }

            $mismatch = $verified->mismatchReason($locked, $this->paytabs->profileId());

            if ($mismatch !== null) {
                Log::warning('paytabs.verification_mismatch', [
                    'payment_id' => $locked->id,
                    'reason' => $mismatch,
                ]);
                $this->failOrCancelCard($locked, PaymentStatus::Failed, 'Payment verification failed.');

                return;
            }

            $next = $verified->mappedStatus();

            if ($next === PaymentStatus::Paid) {
                $this->completeCardPayment($locked, $verified->tranRef);

                return;
            }

            if ($next === PaymentStatus::Failed || $next === PaymentStatus::Cancelled) {
                $this->failOrCancelCard($locked, $next, $next === PaymentStatus::Cancelled
                    ? 'Card payment cancelled.'
                    : 'Card payment failed.');
            }
        });
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
        return ['customer', 'order.project', 'order.package', 'order.packageTier', 'order.service', 'verifier'];
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

        return [
            'payment' => $this->load($payment->fresh()),
            'checkout_url' => $session->url,
        ];
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
        $current = $payment->status instanceof PaymentStatus
            ? $payment->status
            : PaymentStatus::from((string) $payment->status);

        if (! $current->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => ['This payment cannot move to the requested status.'],
            ]);
        }

        $payment->status = $next;
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

        $this->notifier->paymentPaid($this->load($payment->fresh()));
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
