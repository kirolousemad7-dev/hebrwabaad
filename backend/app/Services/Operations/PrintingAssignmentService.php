<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Enums\WorkflowTrigger;
use App\Models\Department;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Services\Workflow\WorkflowAutomationEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrintingAssignmentService
{
    public function __construct(
        private readonly OperationsAuditLogger $audit,
        private readonly WorkflowAutomationEngine $workflows,
    ) {}

    public function assign(
        User $actor,
        PrintingRequest $request,
        ?int $assignedTo = null,
        ?int $departmentId = null,
        bool $hasAssignee = true,
        bool $hasDepartment = true,
    ): PrintingRequest {
        $this->assertCanManage($actor);

        if ($hasAssignee && $assignedTo !== null) {
            $assignee = User::query()->find($assignedTo);
            if ($assignee === null) {
                throw ValidationException::withMessages([
                    'assigned_to' => ['The selected assignee is invalid.'],
                ]);
            }
        }

        if ($hasDepartment && $departmentId !== null) {
            $department = Department::query()->find($departmentId);
            if ($department === null) {
                throw ValidationException::withMessages([
                    'assigned_department_id' => ['The selected department is invalid.'],
                ]);
            }
        }

        return DB::transaction(function () use ($actor, $request, $assignedTo, $departmentId, $hasAssignee, $hasDepartment): PrintingRequest {
            $previousAssignee = $request->assigned_to;
            $previousDepartment = $request->assigned_department_id;

            $attributes = [];
            if ($hasAssignee) {
                $attributes['assigned_to'] = $assignedTo;
            }
            if ($hasDepartment) {
                $attributes['assigned_department_id'] = $departmentId;
            }

            if ($attributes !== []) {
                $request->update($attributes);
            }

            $this->audit->log($actor, 'printing.assigned', $request, [
                'assigned_to' => $request->assigned_to,
                'assigned_department_id' => $request->assigned_department_id,
                'previous_assigned_to' => $previousAssignee,
                'previous_assigned_department_id' => $previousDepartment,
            ]);

            $fresh = $request->fresh([
                'user:id,name,email',
                'quotedBy:id,name',
                'assignee:id,name,email',
                'assignedDepartment:id,name,slug',
            ]);

            try {
                $this->workflows->dispatch(WorkflowTrigger::PrintingAssigned->value, [
                    'source_type' => 'printing_request',
                    'source_id' => $fresh->id,
                    'actor_id' => $actor->id,
                    'title' => 'تعيين طلب طباعة: '.$fresh->product_name,
                    'related_type' => 'printing_request',
                    'related_id' => $fresh->id,
                    'assignee_ids' => array_values(array_filter([$fresh->assigned_to])),
                    'printing_request_id' => $fresh->id,
                    'payload' => [
                        'printing_request_id' => $fresh->id,
                        'assigned_to' => $fresh->assigned_to,
                        'assigned_department_id' => $fresh->assigned_department_id,
                        'actor_id' => $actor->id,
                    ],
                ], (string) $fresh->id);
            } catch (\Throwable) {
                // Workflow hooks must never break assignment.
            }

            return $fresh;
        });
    }

    private function assertCanManage(User $actor): void
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canReviewPrintingRequests()) {
            throw ValidationException::withMessages([
                'printing' => ['You cannot assign printing requests.'],
            ]);
        }
    }
}
