<?php

namespace App\Services\Operations;

use App\Enums\PrintingPricingType;
use App\Enums\PrintingQuotationStatus;
use App\Enums\PrintingRequestStatus;
use App\Enums\UserRole;
use App\Enums\WorkflowTrigger;
use App\Models\PrintingQuotation;
use App\Models\PrintingRequest;
use App\Models\PrintingStatusHistory;
use App\Models\User;
use App\Services\Customer\CustomerCommunicationService;
use App\Services\Printing\PrintingExecutionEligibilityService;
use App\Services\Printing\PrintingQuotationService;
use App\Services\Workflow\WorkflowAutomationEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrintingStatusTransitionService
{
    public function __construct(
        private readonly OperationsAuditLogger $audit,
        private readonly WorkflowAutomationEngine $workflows,
        private readonly PrintingExecutionEligibilityService $eligibility,
        private readonly PrintingQuotationService $quotations,
        private readonly CustomerCommunicationService $communications,
    ) {}

    public function canTransition(PrintingRequestStatus|string $from, PrintingRequestStatus|string $to): bool
    {
        $fromStatus = $from instanceof PrintingRequestStatus
            ? $from
            : PrintingRequestStatus::from((string) $from);
        $toStatus = $to instanceof PrintingRequestStatus
            ? $to
            : PrintingRequestStatus::from((string) $to);

        return $fromStatus->canTransitionTo($toStatus);
    }

    public function transition(User $actor, PrintingRequest $request, string $to, ?string $note = null): PrintingRequest
    {
        $this->assertCanManage($actor);

        $current = $request->status instanceof PrintingRequestStatus
            ? $request->status
            : PrintingRequestStatus::from((string) $request->status);

        try {
            $next = PrintingRequestStatus::from($to);
        } catch (\ValueError) {
            throw ValidationException::withMessages([
                'status' => ['Invalid printing request status.'],
            ]);
        }

        if (! $this->canTransition($current, $next)) {
            throw ValidationException::withMessages([
                'status' => ['This printing request cannot move to the requested status.'],
            ]);
        }

        $trimmedNote = $note !== null ? trim($note) : null;
        if ($trimmedNote === '') {
            $trimmedNote = null;
        }

        if ($current === PrintingRequestStatus::Pending && $next === PrintingRequestStatus::InProgress) {
            if ($this->eligibility->hasAcceptedQuotation($request)) {
                $check = $this->eligibility->eligible($request);
                if (! $check['eligible']) {
                    throw ValidationException::withMessages([
                        'status' => ['Printing cannot start until quotation payment requirements are met.'],
                        'eligibility' => $check['reasons'],
                    ]);
                }
            } elseif (! $this->pricingReadyForProduction($request) && $trimmedNote === null) {
                throw ValidationException::withMessages([
                    'note' => ['A note is required to start production before pricing is ready.'],
                ]);
            }
        }

        return DB::transaction(function () use ($actor, $request, $current, $next, $trimmedNote): PrintingRequest {
            $updates = [
                'status' => $next,
                'status_changed_at' => now(),
            ];

            if ($next === PrintingRequestStatus::Completed && $request->delivered_at === null) {
                $updates['delivered_at'] = now();
            }

            $request->update($updates);

            PrintingStatusHistory::query()->create([
                'printing_request_id' => $request->id,
                'from_status' => $current->value,
                'to_status' => $next->value,
                'actor_id' => $actor->id,
                'note' => $trimmedNote,
            ]);

            $this->audit->log($actor, 'printing.status_changed', $request, [
                'from_status' => $current->value,
                'to_status' => $next->value,
                'note' => $trimmedNote,
            ]);

            $acceptedQuotation = PrintingQuotation::query()
                ->where('printing_request_id', $request->id)
                ->where('status', PrintingQuotationStatus::Accepted->value)
                ->orderByDesc('revision')
                ->orderByDesc('id')
                ->first();

            if ($acceptedQuotation !== null) {
                $this->quotations->recordPublicSafeStatusChanged($acceptedQuotation, $current, $next, $actor);
            }

            $fresh = $request->fresh([
                'user:id,name,email',
                'quotedBy:id,name',
                'assignee:id,name,email',
                'assignedDepartment:id,name,slug',
            ]);

            $this->dispatchWorkflows($actor, $fresh, $current, $next, $trimmedNote);

            return $fresh;
        });
    }

    /**
     * @return list<string>
     */
    public function allowedTransitionValues(PrintingRequest $request): array
    {
        $current = $request->status instanceof PrintingRequestStatus
            ? $request->status
            : PrintingRequestStatus::from((string) $request->status);

        return array_map(
            static fn (PrintingRequestStatus $status): string => $status->value,
            $current->allowedTransitions(),
        );
    }

    private function pricingReadyForProduction(PrintingRequest $request): bool
    {
        $pricing = $request->pricing_type instanceof PrintingPricingType
            ? $request->pricing_type
            : ($request->pricing_type !== null
                ? PrintingPricingType::tryFrom((string) $request->pricing_type)
                : null);

        return in_array($pricing, [
            PrintingPricingType::Estimated,
            PrintingPricingType::QuoteReady,
        ], true);
    }

    private function dispatchWorkflows(
        User $actor,
        PrintingRequest $request,
        PrintingRequestStatus $from,
        PrintingRequestStatus $to,
        ?string $note,
    ): void {
        $payload = [
            'source_type' => 'printing_request',
            'source_id' => $request->id,
            'actor_id' => $actor->id,
            'title' => 'تحديث حالة طباعة: '.$request->product_name,
            'related_type' => 'printing_request',
            'related_id' => $request->id,
            'assignee_ids' => array_values(array_filter([
                $request->assigned_to,
                $request->quoted_by,
            ])),
            'old_status' => $from->value,
            'new_status' => $to->value,
            'printing_request_id' => $request->id,
            'payload' => [
                'printing_request_id' => $request->id,
                'old_status' => $from->value,
                'new_status' => $to->value,
                'note' => $note,
                'actor_id' => $actor->id,
            ],
        ];

        try {
            $this->workflows->dispatch(
                WorkflowTrigger::PrintingStatusChanged->value,
                $payload,
                $from->value.'->'.$to->value,
            );

            if ($to === PrintingRequestStatus::Completed) {
                $this->workflows->dispatch(
                    WorkflowTrigger::PrintingCompleted->value,
                    array_merge($payload, [
                        'title' => 'اكتمال طباعة: '.$request->product_name,
                    ]),
                    (string) $request->id,
                );
            }

            if ($to === PrintingRequestStatus::ReadyForDelivery) {
                $this->workflows->dispatch(
                    WorkflowTrigger::PrintingReadyForDelivery->value,
                    array_merge($payload, [
                        'title' => 'جاهز للتسليم: '.$request->product_name,
                    ]),
                    (string) $request->id,
                );

                try {
                    $this->communications->notifyReadyForDelivery($request);
                } catch (\Throwable) {
                    // Customer email must never break status transitions.
                }
            }

            if ($to === PrintingRequestStatus::Completed) {
                $this->workflows->dispatch(
                    WorkflowTrigger::PrintingDeliveryCompleted->value,
                    array_merge($payload, [
                        'title' => 'اكتمل التسليم: '.$request->product_name,
                    ]),
                    (string) $request->id,
                );
            }
        } catch (\Throwable) {
            // Workflow hooks must never break printing transitions.
        }
    }

    private function assertCanManage(User $actor): void
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canReviewPrintingRequests()) {
            throw ValidationException::withMessages([
                'printing' => ['You cannot update printing request status.'],
            ]);
        }
    }
}
