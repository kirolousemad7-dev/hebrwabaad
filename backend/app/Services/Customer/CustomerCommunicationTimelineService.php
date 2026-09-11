<?php

namespace App\Services\Customer;

use App\Enums\UserRole;
use App\Models\CustomerCommunicationLog;
use App\Models\Payment;
use App\Models\PrintingCustomerApproval;
use App\Models\PrintingDelivery;
use App\Models\PrintingQuotation;
use App\Models\PrintingQuotationEvent;
use App\Models\PrintingRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CustomerCommunicationTimelineService
{
    /**
     * Aggregate quotation events, communication logs, payments, and delivery for staff.
     *
     * @return list<array<string, mixed>>
     */
    public function forPrintingRequest(User $actor, PrintingRequest $request): array
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canReviewPrintingRequests()) {
            throw ValidationException::withMessages([
                'communications' => ['You cannot view printing communications.'],
            ]);
        }

        $quotationIds = PrintingQuotation::query()
            ->where('printing_request_id', $request->id)
            ->pluck('id');

        /** @var Collection<int, array<string, mixed>> $items */
        $items = collect();

        PrintingQuotationEvent::query()
            ->whereIn('printing_quotation_id', $quotationIds)
            ->orderBy('id')
            ->get()
            ->each(function (PrintingQuotationEvent $event) use ($items): void {
                $items->push([
                    'source' => 'quotation_event',
                    'type' => $event->event,
                    'at' => $event->created_at?->toIso8601String(),
                    'meta' => is_array($event->meta) ? $event->meta : null,
                    'actor_type' => $event->actor_type,
                    'related_id' => $event->printing_quotation_id,
                ]);
            });

        CustomerCommunicationLog::query()
            ->where(function ($query) use ($request, $quotationIds): void {
                $query->where(function ($q) use ($quotationIds): void {
                    $q->where('related_type', 'printing_quotation')
                        ->whereIn('related_id', $quotationIds);
                })->orWhere(function ($q) use ($request): void {
                    $q->where('related_type', 'printing_request')
                        ->where('related_id', $request->id);
                });
            })
            ->orderBy('id')
            ->get()
            ->each(function (CustomerCommunicationLog $log) use ($items): void {
                $items->push([
                    'source' => 'communication_log',
                    'type' => $log->template,
                    'at' => ($log->sent_at ?? $log->created_at)?->toIso8601String(),
                    'meta' => [
                        'channel' => $log->channel,
                        'status' => $log->status,
                        'result_summary' => $log->result_summary,
                    ],
                    'actor_type' => 'system',
                    'related_id' => $log->related_id,
                ]);
            });

        Payment::query()
            ->whereIn('printing_quotation_id', $quotationIds)
            ->orderBy('id')
            ->get()
            ->each(function (Payment $payment) use ($items): void {
                $items->push([
                    'source' => 'payment',
                    'type' => 'payment_'.strtolower($payment->status instanceof \BackedEnum
                        ? $payment->status->value
                        : (string) $payment->status),
                    'at' => ($payment->paid_at ?? $payment->created_at)?->toIso8601String(),
                    'meta' => [
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'method' => $payment->payment_method instanceof \BackedEnum
                            ? $payment->payment_method->value
                            : (string) $payment->payment_method,
                        'status' => $payment->status instanceof \BackedEnum
                            ? $payment->status->value
                            : (string) $payment->status,
                    ],
                    'actor_type' => 'system',
                    'related_id' => $payment->id,
                ]);
            });

        PrintingDelivery::query()
            ->where('printing_request_id', $request->id)
            ->orderBy('id')
            ->get()
            ->each(function (PrintingDelivery $delivery) use ($items): void {
                $items->push([
                    'source' => 'delivery',
                    'type' => 'delivery_'.strtolower($delivery->status instanceof \BackedEnum
                        ? $delivery->status->value
                        : (string) $delivery->status),
                    'at' => ($delivery->delivered_at ?? $delivery->created_at)?->toIso8601String(),
                    'meta' => [
                        'method' => $delivery->method instanceof \BackedEnum
                            ? $delivery->method->value
                            : (string) $delivery->method,
                        'status' => $delivery->status instanceof \BackedEnum
                            ? $delivery->status->value
                            : (string) $delivery->status,
                        'provider' => $delivery->provider,
                    ],
                    'actor_type' => 'staff',
                    'related_id' => $delivery->id,
                ]);
            });

        PrintingCustomerApproval::query()
            ->where('printing_request_id', $request->id)
            ->orderBy('id')
            ->get()
            ->each(function (PrintingCustomerApproval $approval) use ($items): void {
                $items->push([
                    'source' => 'approval',
                    'type' => 'approval_'.strtolower($approval->status instanceof \BackedEnum
                        ? $approval->status->value
                        : (string) $approval->status),
                    'at' => ($approval->decided_at ?? $approval->created_at)?->toIso8601String(),
                    'meta' => [
                        'title' => $approval->title,
                        'type' => $approval->type instanceof \BackedEnum
                            ? $approval->type->value
                            : (string) $approval->type,
                        'status' => $approval->status instanceof \BackedEnum
                            ? $approval->status->value
                            : (string) $approval->status,
                    ],
                    'actor_type' => $approval->decided_at ? 'customer' : 'staff',
                    'related_id' => $approval->id,
                ]);
            });

        return $items
            ->sortBy('at')
            ->values()
            ->all();
    }
}
