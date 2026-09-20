<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Services\Catalog\CustomPackageProjectOverviewService;
use App\Services\Operations\ProjectWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-safe project payload. No other customers, employee emails, or task assignees.
 *
 * @mixin Project
 */
class CustomerProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $serviceLines = app(CustomPackageProjectOverviewService::class)
            ->serviceLines($this->resource, forCustomer: true);
        $clientProgress = app(ProjectWorkspaceService::class)->clientProgress($this->resource);
        $progress = $clientProgress['progress'];

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'started_at' => $this->started_at?->toDateString(),
            'deadline' => $this->deadline?->toDateString(),
            'account_manager' => $this->whenLoaded('accountManager', fn () => $this->accountManager === null ? null : [
                'id' => $this->accountManager->id,
                'name' => $this->accountManager->name,
            ]),
            'progress' => [
                'total' => $progress['total'],
                'completed' => $progress['completed'],
                'in_progress' => $progress['in_progress'],
                'review' => $progress['review'],
                'percent' => $progress['percent'],
            ],
            'service_progress' => collect($serviceLines)->map(fn (array $line): array => [
                'service_name' => $line['service_name'],
                'quantity' => $line['quantity'],
                'status_key' => $line['status_key'],
                'status_label' => $line['status_label'],
            ])->values()->all(),
            'current_phase' => $clientProgress['current_phase'],
            'phases' => $clientProgress['phases'],
            'completed_milestones' => $clientProgress['completed_milestones'],
            'upcoming_milestones' => $clientProgress['upcoming_milestones'],
            'next_milestone' => $clientProgress['next_milestone'],
            'client_action_items' => $clientProgress['client_action_items'],
            'references' => $clientProgress['references'],
            'deliverables' => $clientProgress['deliverables'] ?? [],
            'client_profile' => $clientProgress['client_profile'],
            'brief' => $clientProgress['brief'],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
