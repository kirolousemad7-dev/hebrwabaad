<?php

namespace App\Services\Customer;

use App\Enums\UserRole;
use App\Mail\ApprovalRequiredMail;
use App\Mail\PaymentConfirmedMail;
use App\Mail\PortalMagicLinkMail;
use App\Mail\PrintingQuotationMail;
use App\Mail\ReadyForDeliveryMail;
use App\Models\CustomerCommunicationDelivery;
use App\Models\CustomerCommunicationLog;
use App\Models\CustomerPortalAccess;
use App\Models\Payment;
use App\Models\PrintingCustomerApproval;
use App\Models\PrintingQuotation;
use App\Models\PrintingRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CustomerCommunicationService
{
    public function mailEnabled(): bool
    {
        $mailer = config('mail.default');

        if ($mailer === null || in_array((string) $mailer, ['log', 'array', 'null', ''], true)) {
            return false;
        }

        return filled(config('mail.from.address'));
    }

    /**
     * @return array{status: string, public_url: string, log: CustomerCommunicationLog, delivery: CustomerCommunicationDelivery}
     */
    public function sendQuotationEmail(PrintingQuotation $quotation, string $rawPublicToken, ?User $actor = null, bool $force = false): array
    {
        $quotation->loadMissing('customer:id,name,email');

        $publicUrl = $this->frontendUrl('/q/'.$rawPublicToken);
        $customer = $quotation->customer;
        $dedupeKey = $this->quoteEmailDedupeKey($quotation, $force);

        $result = $this->deliver(
            type: 'quote_email',
            dedupeKey: $dedupeKey,
            customerId: $quotation->customer_id,
            recipient: $customer?->email,
            relatedType: 'printing_quotation',
            relatedId: $quotation->id,
            template: 'printing_quotation',
            force: $force,
            send: function () use ($quotation, $publicUrl, $customer): void {
                Mail::to($customer->email)->queue(new PrintingQuotationMail(
                    $quotation,
                    $publicUrl,
                    (string) $customer->name,
                ));
            },
            skipReason: fn (): string => $this->mailEnabled()
                ? 'Customer email missing; public URL available for copy.'
                : 'Mail transport not configured; public URL available for copy.',
            actor: $actor,
            requireRecipient: true,
        );

        return [
            'status' => $result['status'],
            'public_url' => $publicUrl,
            'log' => $result['log'],
            'delivery' => $result['delivery'],
        ];
    }

    /**
     * @return array{status: string, public_url: string, log: CustomerCommunicationLog, delivery: CustomerCommunicationDelivery}
     */
    public function emailQuotationForStaff(
        User $actor,
        PrintingQuotation $quotation,
        string $rawPublicToken,
        bool $force = false,
    ): array {
        $this->assertCanManagePrinting($actor);
        $this->assertEmailNotSpam($quotation, $force);

        return $this->sendQuotationEmail($quotation, $rawPublicToken, $actor, $force);
    }

    /**
     * Light payment confirmation email — skipped when mail disabled or already delivered.
     *
     * @return array{status: string, delivery: CustomerCommunicationDelivery|null}
     */
    public function notifyPaymentConfirmed(PrintingQuotation $quotation, Payment $payment): array
    {
        $quotation->loadMissing('customer:id,name,email');
        $customer = $quotation->customer;
        $dedupeKey = 'payment_confirmed:'.$payment->id;

        $result = $this->deliver(
            type: 'payment_confirmed',
            dedupeKey: $dedupeKey,
            customerId: $quotation->customer_id,
            recipient: $customer?->email,
            relatedType: 'payment',
            relatedId: $payment->id,
            template: 'payment_confirmed',
            force: false,
            send: function () use ($quotation, $payment, $customer): void {
                Mail::to($customer->email)->queue(new PaymentConfirmedMail(
                    $quotation,
                    $payment,
                    (string) $customer->name,
                ));
            },
            skipReason: fn (): string => 'Payment confirmed email skipped.',
            requireRecipient: true,
        );

        return ['status' => $result['status'], 'delivery' => $result['delivery']];
    }

    /**
     * @return array{status: string, delivery: CustomerCommunicationDelivery|null}
     */
    public function notifyApprovalRequired(PrintingCustomerApproval $approval, string $rawPublicToken): array
    {
        $approval->loadMissing('printingRequest.user:id,name,email');
        $customer = $approval->printingRequest?->user;
        $approvalUrl = $this->frontendUrl('/printing-approvals/'.$rawPublicToken);
        $dedupeKey = 'approval_required:'.$approval->id;

        $result = $this->deliver(
            type: 'approval_required',
            dedupeKey: $dedupeKey,
            customerId: $customer?->id,
            recipient: $customer?->email,
            relatedType: 'printing_customer_approval',
            relatedId: $approval->id,
            template: 'approval_required',
            force: false,
            send: function () use ($approval, $approvalUrl, $customer): void {
                Mail::to($customer->email)->queue(new ApprovalRequiredMail(
                    $approval,
                    $approvalUrl,
                    (string) $customer->name,
                ));
            },
            skipReason: fn (): string => 'Approval required email skipped.',
            requireRecipient: true,
        );

        return ['status' => $result['status'], 'delivery' => $result['delivery']];
    }

    /**
     * @return array{status: string, delivery: CustomerCommunicationDelivery|null}
     */
    public function notifyReadyForDelivery(PrintingRequest $request): array
    {
        $request->loadMissing('user:id,name,email');
        $customer = $request->user;
        $dedupeKey = 'ready_for_delivery:'.$request->id;

        $result = $this->deliver(
            type: 'ready_for_delivery',
            dedupeKey: $dedupeKey,
            customerId: $customer?->id,
            recipient: $customer?->email,
            relatedType: 'printing_request',
            relatedId: $request->id,
            template: 'ready_for_delivery',
            force: false,
            send: function () use ($request, $customer): void {
                Mail::to($customer->email)->queue(new ReadyForDeliveryMail(
                    $request,
                    (string) $customer->name,
                ));
            },
            skipReason: fn (): string => 'Ready for delivery email skipped.',
            requireRecipient: true,
        );

        return ['status' => $result['status'], 'delivery' => $result['delivery']];
    }

    /**
     * Always returns a generic success payload; never reveals whether the email exists.
     *
     * @return array{message: string}
     */
    public function requestPortalMagicLink(string $email, callable $createAccess): array
    {
        $generic = ['message' => 'إذا كان البريد مسجلاً لدينا فسيتم إرسال رابط الدخول إن أمكن.'];

        $normalized = strtolower(trim($email));
        if ($normalized === '' || ! $this->mailEnabled()) {
            return $generic;
        }

        $customer = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->where('role', 'CUSTOMER')
            ->first();

        if ($customer === null) {
            return $generic;
        }

        /** @var array{access: CustomerPortalAccess, raw_token: string} $created */
        $created = $createAccess($customer);
        $this->queuePortalMagicLink($customer, $created['access'], $created['raw_token']);

        return $generic;
    }

    /**
     * Staff resend of portal access email when a usable portal token already exists.
     *
     * @return array{status: string, portal_url: string|null, delivery: CustomerCommunicationDelivery|null}
     */
    public function resendPortalAccessEmail(User $actor, User $customer, callable $createAccess): array
    {
        $this->assertCanManagePrinting($actor);
        $this->assertCustomer($customer);

        $existing = CustomerPortalAccess::query()
            ->where('customer_id', $customer->id)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();

        if ($existing === null) {
            throw ValidationException::withMessages([
                'portal' => ['No active portal access exists for this customer.'],
            ]);
        }

        if (! $this->mailEnabled() || blank($customer->email)) {
            $delivery = $this->recordDelivery([
                'customer_id' => $customer->id,
                'channel' => 'email',
                'type' => 'portal_resend',
                'recipient' => $customer->email,
                'status' => 'skipped',
                'related_type' => 'customer_portal_access',
                'related_id' => $existing->id,
                'dedupe_key' => 'portal_resend:'.$customer->id.':'.now()->timestamp,
                'queued_at' => null,
                'sent_at' => null,
                'result_summary' => $this->mailEnabled()
                    ? 'Customer email missing.'
                    : 'Mail transport not configured.',
            ]);

            $this->log([
                'customer_id' => $customer->id,
                'channel' => 'email',
                'template' => 'portal_magic_link',
                'status' => 'skipped',
                'related_type' => 'customer_portal_access',
                'related_id' => $existing->id,
                'result_summary' => $delivery->result_summary,
                'sent_at' => null,
            ]);

            return [
                'status' => 'skipped',
                'portal_url' => null,
                'delivery' => $delivery,
            ];
        }

        $this->assertPortalResendNotSpam($customer);

        /** @var array{access: CustomerPortalAccess, raw_token: string} $created */
        $created = $createAccess($customer);
        $portalUrl = $this->frontendUrl('/portal/'.$created['raw_token']);
        $delivery = $this->queuePortalMagicLink($customer, $created['access'], $created['raw_token'], 'portal_resend');

        return [
            'status' => $delivery->status,
            'portal_url' => $portalUrl,
            'delivery' => $delivery,
        ];
    }

    public function frontendUrl(string $path): string
    {
        $base = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        $path = '/'.ltrim($path, '/');

        return $base.$path;
    }

    /**
     * @param  array{
     *     customer_id: int|null,
     *     channel: string,
     *     template: string,
     *     status: string,
     *     related_type?: string|null,
     *     related_id?: int|null,
     *     result_summary?: string|null,
     *     sent_at?: mixed
     * }  $data
     */
    public function log(array $data): CustomerCommunicationLog
    {
        $summary = $data['result_summary'] ?? null;
        if (is_string($summary) && preg_match('/[A-Za-z0-9]{32,}/', $summary) === 1) {
            $summary = preg_replace('/[A-Za-z0-9_\-]{32,}/', '[redacted]', $summary);
        }

        return CustomerCommunicationLog::query()->create([
            'customer_id' => $data['customer_id'],
            'channel' => $data['channel'],
            'template' => $data['template'],
            'status' => $data['status'],
            'related_type' => $data['related_type'] ?? null,
            'related_id' => $data['related_id'] ?? null,
            'result_summary' => is_string($summary) ? mb_substr($summary, 0, 500) : null,
            'sent_at' => $data['sent_at'] ?? null,
        ]);
    }

    public function quoteEmailDedupeKey(PrintingQuotation $quotation, bool $force = false): string
    {
        $base = 'quote_email:'.$quotation->id.':'.(int) $quotation->revision;

        if ($force) {
            return $base.':force:'.uniqid('', true);
        }

        return $base;
    }

    /**
     * Check whether a delivery with this dedupe key was already queued/sent.
     */
    public function alreadyDelivered(string $dedupeKey): bool
    {
        return CustomerCommunicationDelivery::query()
            ->where('dedupe_key', $dedupeKey)
            ->whereIn('status', ['queued', 'sent'])
            ->exists();
    }

    private function queuePortalMagicLink(
        User $customer,
        CustomerPortalAccess $access,
        string $rawToken,
        string $type = 'portal_magic_link',
    ): CustomerCommunicationDelivery {
        $portalUrl = $this->frontendUrl('/portal/'.$rawToken);
        $dedupeKey = $type.':'.$customer->id.':'.$access->id;

        Mail::to($customer->email)->queue(new PortalMagicLinkMail(
            (string) $customer->name,
            $portalUrl,
        ));

        $delivery = $this->recordDelivery([
            'customer_id' => $customer->id,
            'channel' => 'email',
            'type' => $type,
            'recipient' => $customer->email,
            'status' => 'queued',
            'related_type' => 'customer_portal_access',
            'related_id' => $access->id,
            'dedupe_key' => $dedupeKey,
            'queued_at' => now(),
            'sent_at' => now(),
            'result_summary' => 'Portal magic link queued.',
        ]);

        $this->log([
            'customer_id' => $customer->id,
            'channel' => 'email',
            'template' => 'portal_magic_link',
            'status' => 'queued',
            'related_type' => 'customer_portal_access',
            'related_id' => $access->id,
            'result_summary' => 'Portal magic link queued.',
            'sent_at' => now(),
        ]);

        return $delivery;
    }

    /**
     * Single orchestration path for customer email — records deliveries with dedupe.
     *
     * @param  callable(): void  $send
     * @param  callable(): string  $skipReason
     * @return array{status: string, log: CustomerCommunicationLog, delivery: CustomerCommunicationDelivery}
     */
    private function deliver(
        string $type,
        string $dedupeKey,
        ?int $customerId,
        ?string $recipient,
        ?string $relatedType,
        ?int $relatedId,
        string $template,
        bool $force,
        callable $send,
        callable $skipReason,
        ?User $actor = null,
        bool $requireRecipient = true,
    ): array {
        if (! $force && $this->alreadyDelivered($dedupeKey)) {
            $existing = CustomerCommunicationDelivery::query()
                ->where('dedupe_key', $dedupeKey)
                ->whereIn('status', ['queued', 'sent'])
                ->latest('id')
                ->first();

            $log = $this->log([
                'customer_id' => $customerId,
                'channel' => 'email',
                'template' => $template,
                'status' => 'skipped',
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'result_summary' => 'Deduped; delivery already recorded for '.$dedupeKey.'.',
                'sent_at' => null,
            ]);

            return [
                'status' => 'deduped',
                'log' => $log,
                'delivery' => $existing ?? $this->recordDelivery([
                    'customer_id' => $customerId,
                    'channel' => 'email',
                    'type' => $type,
                    'recipient' => $recipient,
                    'status' => 'skipped',
                    'related_type' => $relatedType,
                    'related_id' => $relatedId,
                    'dedupe_key' => $dedupeKey.':dup:'.uniqid('', true),
                    'result_summary' => 'Deduped.',
                ]),
            ];
        }

        $canSend = $this->mailEnabled()
            && (! $requireRecipient || ($recipient !== null && $recipient !== ''));

        if (! $canSend) {
            // Do not occupy the durable dedupe key on skips so a later real send can succeed.
            $delivery = $this->recordDelivery([
                'customer_id' => $customerId,
                'channel' => 'email',
                'type' => $type,
                'recipient' => $recipient,
                'status' => 'skipped',
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'dedupe_key' => null,
                'queued_at' => null,
                'sent_at' => null,
                'result_summary' => $skipReason(),
            ]);

            $log = $this->log([
                'customer_id' => $customerId,
                'channel' => 'email',
                'template' => $template,
                'status' => 'skipped',
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'result_summary' => $delivery->result_summary.($actor ? ' Actor #'.$actor->id.'.' : ''),
                'sent_at' => null,
            ]);

            return [
                'status' => 'skipped',
                'log' => $log,
                'delivery' => $delivery,
            ];
        }

        $send();

        $delivery = $this->recordDelivery([
            'customer_id' => $customerId,
            'channel' => 'email',
            'type' => $type,
            'recipient' => $recipient,
            'status' => 'queued',
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'dedupe_key' => $dedupeKey,
            'queued_at' => now(),
            'sent_at' => now(),
            'result_summary' => ucfirst(str_replace('_', ' ', $type)).' queued.'.($actor ? ' Actor #'.$actor->id.'.' : ''),
        ]);

        $log = $this->log([
            'customer_id' => $customerId,
            'channel' => 'email',
            'template' => $template,
            'status' => 'queued',
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'result_summary' => $delivery->result_summary,
            'sent_at' => now(),
        ]);

        return [
            'status' => 'queued',
            'log' => $log,
            'delivery' => $delivery,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordDelivery(array $data): CustomerCommunicationDelivery
    {
        $summary = $data['result_summary'] ?? null;
        if (is_string($summary) && preg_match('/[A-Za-z0-9]{32,}/', $summary) === 1) {
            $summary = preg_replace('/[A-Za-z0-9_\-]{32,}/', '[redacted]', $summary);
        }

        return CustomerCommunicationDelivery::query()->create([
            'customer_id' => $data['customer_id'] ?? null,
            'channel' => $data['channel'] ?? 'email',
            'type' => $data['type'],
            'recipient' => $data['recipient'] ?? null,
            'status' => $data['status'],
            'related_type' => $data['related_type'] ?? null,
            'related_id' => $data['related_id'] ?? null,
            'dedupe_key' => $data['dedupe_key'] ?? null,
            'queued_at' => $data['queued_at'] ?? null,
            'sent_at' => $data['sent_at'] ?? null,
            'failed_at' => $data['failed_at'] ?? null,
            'result_summary' => is_string($summary) ? mb_substr($summary, 0, 500) : null,
        ]);
    }

    private function assertEmailNotSpam(PrintingQuotation $quotation, bool $force): void
    {
        if ($force) {
            return;
        }

        $count = CustomerCommunicationDelivery::query()
            ->where('related_type', 'printing_quotation')
            ->where('related_id', $quotation->id)
            ->where('type', 'quote_email')
            ->where('channel', 'email')
            ->where('created_at', '>=', now()->subDay())
            ->whereIn('status', ['queued', 'sent', 'skipped'])
            ->count();

        if ($count === 0) {
            $count = CustomerCommunicationLog::query()
                ->where('related_type', 'printing_quotation')
                ->where('related_id', $quotation->id)
                ->where('template', 'printing_quotation')
                ->where('channel', 'email')
                ->where('created_at', '>=', now()->subDay())
                ->whereIn('status', ['queued', 'sent', 'skipped'])
                ->count();
        }

        if ($count >= 3) {
            throw ValidationException::withMessages([
                'email' => ['Daily email limit reached for this quotation. Pass force=true to override.'],
            ]);
        }
    }

    private function assertPortalResendNotSpam(User $customer): void
    {
        $count = CustomerCommunicationDelivery::query()
            ->where('customer_id', $customer->id)
            ->where('type', 'portal_resend')
            ->where('created_at', '>=', now()->subHour())
            ->whereIn('status', ['queued', 'sent'])
            ->count();

        if ($count >= 3) {
            throw ValidationException::withMessages([
                'portal' => ['Portal email resend limit reached. Try again later.'],
            ]);
        }
    }

    private function assertCanManagePrinting(User $actor): void
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canReviewPrintingRequests()) {
            throw ValidationException::withMessages([
                'email' => ['You cannot email printing quotations.'],
            ]);
        }
    }

    private function assertCustomer(User $customer): void
    {
        if (! ($customer->role instanceof UserRole) || $customer->role !== UserRole::Customer) {
            throw ValidationException::withMessages([
                'customer' => ['Portal access is only available for customers.'],
            ]);
        }
    }
}
