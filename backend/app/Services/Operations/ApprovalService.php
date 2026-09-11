<?php

namespace App\Services\Operations;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\CrmQuotationStatus;
use App\Enums\UserRole;
use App\Models\ApprovalRequest;
use App\Models\CrmQuotation;
use App\Models\User;
use App\Services\Workflow\WorkflowAutomationEngine;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function request(User $actor, array $attributes): ApprovalRequest
    {
        $type = (string) $attributes['type'];
        if (! in_array($type, ApprovalRequestType::values(), true)) {
            throw ValidationException::withMessages([
                'type' => ['Invalid approval type.'],
            ]);
        }

        $assignee = User::query()->find((int) $attributes['assigned_to']);
        if ($assignee === null || ! $assignee->is_active) {
            throw ValidationException::withMessages([
                'assigned_to' => ['Assignee is not available.'],
            ]);
        }

        return ApprovalRequest::query()->create([
            'type' => $type,
            'related_type' => $attributes['related_type'],
            'related_id' => (int) $attributes['related_id'],
            'title' => $attributes['title'],
            'notes' => $attributes['notes'] ?? null,
            'requested_by' => $actor->id,
            'assigned_to' => $assignee->id,
            'status' => ApprovalRequestStatus::Pending->value,
        ])->load(['requester:id,name', 'assignee:id,name']);
    }

    public function approve(User $actor, ApprovalRequest $request, ?string $notes = null): ApprovalRequest
    {
        $this->assertCanDecide($actor, $request);

        if ($request->status !== ApprovalRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['This approval is already decided.'],
            ]);
        }

        $request->update([
            'status' => ApprovalRequestStatus::Approved->value,
            'decision_notes' => $notes,
            'approved_at' => now(),
            'rejected_at' => null,
        ]);

        $fresh = $request->fresh(['requester:id,name', 'assignee:id,name']) ?? $request;
        $this->dispatchDecisionHook($actor, $fresh, 'approval.approved');

        return $fresh;
    }

    public function reject(User $actor, ApprovalRequest $request, ?string $notes = null): ApprovalRequest
    {
        $this->assertCanDecide($actor, $request);

        if ($request->status !== ApprovalRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['This approval is already decided.'],
            ]);
        }

        $request->update([
            'status' => ApprovalRequestStatus::Rejected->value,
            'decision_notes' => $notes,
            'rejected_at' => now(),
            'approved_at' => null,
        ]);

        $fresh = $request->fresh(['requester:id,name', 'assignee:id,name']) ?? $request;
        $this->dispatchDecisionHook($actor, $fresh, 'approval.rejected');

        return $fresh;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inbox(User $actor, ?string $status = null): array
    {
        $query = ApprovalRequest::query()
            ->with(['requester:id,name', 'assignee:id,name'])
            ->where('assigned_to', $actor->id)
            ->latest();

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $items = $query->limit(100)->get()->map(fn (ApprovalRequest $row) => $this->serialize($row))->all();

        $wantsPending = $status === null || $status === '' || $status === ApprovalRequestStatus::Pending->value;
        if ($wantsPending && $actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) {
            $items = array_merge($items, $this->syntheticCrmQuotationApprovals($actor));
        }

        return $items;
    }

    /**
     * Read-only synthetic inbox rows — approve/reject still via CRM endpoints.
     * Content/WorkSubmission review already has WorkReviewController; not duplicated here.
     *
     * @return list<array<string, mixed>>
     */
    private function syntheticCrmQuotationApprovals(User $actor): array
    {
        return CrmQuotation::query()
            ->with(['creator:id,name', 'lead:id,full_name'])
            ->where('status', CrmQuotationStatus::PendingApproval->value)
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(function (CrmQuotation $quotation) use ($actor): array {
                $label = $quotation->number ?: ('عرض سعر #'.$quotation->id);

                return [
                    'id' => 'crm_quotation_'.$quotation->id,
                    'synthetic' => true,
                    'type' => 'crm_quotation_approval',
                    'related_type' => 'crm_quotation',
                    'related_id' => $quotation->id,
                    'title' => 'موافقة عرض سعر: '.$label,
                    'notes' => $quotation->lead?->full_name,
                    'status' => ApprovalRequestStatus::Pending->value,
                    'decision_notes' => null,
                    'requested_by' => $quotation->created_by,
                    'assigned_to' => $actor->id,
                    'requester' => $quotation->creator ? [
                        'id' => $quotation->creator->id,
                        'name' => $quotation->creator->name,
                    ] : null,
                    'assignee' => [
                        'id' => $actor->id,
                        'name' => $actor->name,
                    ],
                    'approved_at' => null,
                    'rejected_at' => null,
                    'created_at' => $quotation->created_at?->toIso8601String(),
                    'action_href' => '/crm/quotations/'.$quotation->id,
                    'href' => '/crm/quotations/'.$quotation->id,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mine(User $actor): array
    {
        return ApprovalRequest::query()
            ->with(['requester:id,name', 'assignee:id,name'])
            ->where('requested_by', $actor->id)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (ApprovalRequest $row) => $this->serialize($row))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ApprovalRequest $request): array
    {
        return [
            'id' => $request->id,
            'type' => $request->type,
            'related_type' => $request->related_type,
            'related_id' => $request->related_id,
            'title' => $request->title,
            'notes' => $request->notes,
            'status' => $request->status instanceof ApprovalRequestStatus
                ? $request->status->value
                : $request->status,
            'decision_notes' => $request->decision_notes,
            'requested_by' => $request->requested_by,
            'assigned_to' => $request->assigned_to,
            'requester' => $request->requester ? [
                'id' => $request->requester->id,
                'name' => $request->requester->name,
            ] : null,
            'assignee' => $request->assignee ? [
                'id' => $request->assignee->id,
                'name' => $request->assignee->name,
            ] : null,
            'approved_at' => $request->approved_at?->toIso8601String(),
            'rejected_at' => $request->rejected_at?->toIso8601String(),
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }

    private function assertCanDecide(User $actor, ApprovalRequest $request): void
    {
        $canManage = $actor->role instanceof UserRole && $actor->role->canManageApprovals();

        if ((int) $request->assigned_to !== (int) $actor->id && ! ($canManage && $actor->isOwner())) {
            throw ValidationException::withMessages([
                'approval' => ['You cannot decide this approval request.'],
            ]);
        }
    }

    private function dispatchDecisionHook(User $actor, ApprovalRequest $request, string $trigger): void
    {
        try {
            app(WorkflowAutomationEngine::class)->dispatch($trigger, [
                'source_type' => 'approval_request',
                'source_id' => $request->id,
                'actor_id' => $actor->id,
                'title' => $request->title,
                'related_type' => $request->related_type,
                'related_id' => $request->related_id,
                'payload' => [
                    'approval_id' => $request->id,
                    'type' => $request->type instanceof ApprovalRequestType
                        ? $request->type->value
                        : (string) $request->type,
                    'status' => $request->status instanceof ApprovalRequestStatus
                        ? $request->status->value
                        : (string) $request->status,
                ],
            ]);
        } catch (\Throwable) {
            // Workflow hooks must never break approval decisions.
        }
    }
}
