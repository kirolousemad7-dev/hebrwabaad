<?php

namespace App\Services\Printing;

use App\Enums\PrintingCustomerApprovalStatus;
use App\Enums\PrintingCustomerApprovalType;
use App\Enums\UserRole;
use App\Models\PrintingCustomerApproval;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Services\Customer\CustomerCommunicationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PrintingCustomerApprovalService
{
    public function __construct(
        private readonly CustomerCommunicationService $communications,
    ) {}

    /**
     * @return array{approval: PrintingCustomerApproval, public_token: string}
     */
    public function create(User $actor, PrintingRequest $request, array $data): array
    {
        $this->assertCanManage($actor);

        $type = PrintingCustomerApprovalType::from((string) $data['type']);
        $rawToken = Str::random(64);

        $approval = PrintingCustomerApproval::query()->create([
            'printing_request_id' => $request->id,
            'type' => $type,
            'status' => PrintingCustomerApprovalStatus::Pending,
            'public_token_hash' => PrintingCustomerApproval::hashToken($rawToken),
            'title' => (string) $data['title'],
            'notes' => $data['notes'] ?? null,
        ]);

        $fresh = $approval->fresh() ?? $approval;

        try {
            $this->communications->notifyApprovalRequired($fresh, $rawToken);
        } catch (\Throwable) {
            // Customer email must never break approval creation.
        }

        return ['approval' => $fresh, 'public_token' => $rawToken];
    }

    /**
     * @return Collection<int, PrintingCustomerApproval>
     */
    public function listForRequest(User $actor, PrintingRequest $request): Collection
    {
        $this->assertCanManage($actor);

        return PrintingCustomerApproval::query()
            ->where('printing_request_id', $request->id)
            ->orderByDesc('id')
            ->get();
    }

    public function findByToken(string $rawToken): PrintingCustomerApproval
    {
        $approval = PrintingCustomerApproval::findByRawToken($rawToken);

        if ($approval === null) {
            throw ValidationException::withMessages([
                'token' => ['Approval not found.'],
            ]);
        }

        return $approval->load('printingRequest:id,product_name,status');
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(PrintingCustomerApproval $approval): array
    {
        $status = $approval->status instanceof PrintingCustomerApprovalStatus
            ? $approval->status
            : PrintingCustomerApprovalStatus::from((string) $approval->status);

        $type = $approval->type instanceof PrintingCustomerApprovalType
            ? $approval->type
            : PrintingCustomerApprovalType::from((string) $approval->type);

        return [
            'title' => $approval->title,
            'type' => $type->value,
            'status' => $status->value,
            'product_name' => $approval->printingRequest?->product_name,
            'decided_at' => $approval->decided_at?->toIso8601String(),
            'notes' => $approval->notes,
        ];
    }

    public function approveByToken(string $rawToken, ?string $notes = null): PrintingCustomerApproval
    {
        return $this->decideByToken($rawToken, PrintingCustomerApprovalStatus::Approved, $notes);
    }

    public function rejectByToken(string $rawToken, ?string $notes = null): PrintingCustomerApproval
    {
        return $this->decideByToken($rawToken, PrintingCustomerApprovalStatus::Rejected, $notes);
    }

    public function hasPendingCheckpoints(PrintingRequest $request): bool
    {
        return PrintingCustomerApproval::query()
            ->where('printing_request_id', $request->id)
            ->where('status', PrintingCustomerApprovalStatus::Pending->value)
            ->exists();
    }

    public function allApprovedOrNone(PrintingRequest $request): bool
    {
        $total = PrintingCustomerApproval::query()
            ->where('printing_request_id', $request->id)
            ->count();

        if ($total === 0) {
            return true;
        }

        if ($this->hasPendingCheckpoints($request)) {
            return false;
        }

        $rejected = PrintingCustomerApproval::query()
            ->where('printing_request_id', $request->id)
            ->where('status', PrintingCustomerApprovalStatus::Rejected->value)
            ->exists();

        return ! $rejected;
    }

    private function decideByToken(
        string $rawToken,
        PrintingCustomerApprovalStatus $decision,
        ?string $notes,
    ): PrintingCustomerApproval {
        $approval = $this->findByToken($rawToken);

        $status = $approval->status instanceof PrintingCustomerApprovalStatus
            ? $approval->status
            : PrintingCustomerApprovalStatus::from((string) $approval->status);

        if ($status === $decision) {
            return $approval;
        }

        if ($status !== PrintingCustomerApprovalStatus::Pending) {
            throw ValidationException::withMessages([
                'approval' => ['This approval has already been decided.'],
            ]);
        }

        $approval->update([
            'status' => $decision,
            'decided_at' => now(),
            'notes' => $notes ?? $approval->notes,
        ]);

        return $approval->fresh(['printingRequest:id,product_name,status']) ?? $approval;
    }

    private function assertCanManage(User $actor): void
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canReviewPrintingRequests()) {
            throw ValidationException::withMessages([
                'approval' => ['You cannot manage printing customer approvals.'],
            ]);
        }
    }
}
