<?php

namespace App\Http\Resources;

use App\Enums\RequirementStatus;
use App\Models\Requirement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Requirement
 */
class RequirementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'company' => $this->company,
            'service' => $this->service,
            'category' => $this->category,
            'budget' => $this->budget,
            'deadline' => $this->deadline,
            'description' => $this->description,
            'attachments' => collect($this->attachments ?? [])->map(fn ($file, $index) => [
                'index' => $index,
                'original_name' => $file['original_name'] ?? 'file',
                'mime_type' => $file['mime_type'] ?? null,
                'size' => $file['size'] ?? null,
            ])->values(),
            'source' => $this->source,
            'answers' => $this->answers ?? [],
            'summary' => $this->summary,
            'recommended_services' => $this->recommended_services ?? [],
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof RequirementStatus
                ? $this->status->labelAr()
                : (string) $this->status,
            'notes' => $this->notes,
            'qualified' => (bool) $this->qualified,
            'qualified_at' => $this->qualified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'customer' => [
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email,
                'company' => $this->company,
            ],
            'assigned_team_member' => $this->whenLoaded('assignee', fn () => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ]),
            'crm_lead' => $this->whenLoaded('crmLead', fn () => $this->crmLead === null ? null : [
                'id' => $this->crmLead->id,
                'reference' => $this->crmLead->reference,
                'status' => $this->crmLead->status instanceof \BackedEnum
                    ? $this->crmLead->status->value
                    : $this->crmLead->status,
            ]),
            'task' => $this->whenLoaded('task', fn () => $this->task === null ? null : [
                'id' => $this->task->id,
                'title' => $this->task->title,
                'status' => $this->task->status instanceof \BackedEnum
                    ? $this->task->status->value
                    : $this->task->status,
            ]),
            'catalog_service' => $this->whenLoaded('catalogService', fn () => $this->catalogService === null ? null : [
                'id' => $this->catalogService->id,
                'name' => $this->catalogService->name,
                'slug' => $this->catalogService->slug,
            ]),
        ];
    }
}
