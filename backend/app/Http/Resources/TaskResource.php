<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority?->value ?? $this->priority,
            'status' => $this->status?->value ?? $this->status,
            'deadline' => $this->deadline?->toDateString(),
            'start_at' => $this->start_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'location' => $this->location,
            'supplier_id' => $this->supplier_id,
            'calendar_item_id' => $this->calendar_item_id,
            'is_overdue' => $this->isOverdue(),
            'assigned_to' => $this->assigned_to,
            'created_by' => $this->created_by,
            'google_sync' => [
                'enabled' => (bool) $this->google_sync_enabled,
                'status' => $this->google_sync_status?->value ?? $this->google_sync_status,
                'status_label_ar' => $this->google_sync_status?->labelAr(),
                'html_link' => $this->google_html_link,
                'synced_at' => $this->google_synced_at?->toIso8601String(),
                'error' => $this->google_sync_error,
                'meet_enabled' => (bool) $this->google_meet_enabled,
                'event_id' => $this->google_event_id,
            ],
            'project' => $this->whenLoaded('project', fn () => $this->project === null ? null : [
                'id' => $this->project->id,
                'title' => $this->project->title,
                'status' => $this->project->status?->value ?? $this->project->status,
                'customer_id' => $this->project->customer_id,
                'account_manager_id' => $this->project->account_manager_id,
            ]),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier === null ? null : [
                'id' => $this->supplier->id,
                'name' => $this->supplier->display_name ?: $this->supplier->name,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee?->id,
                'name' => $this->assignee?->name,
                'role' => $this->assignee?->role?->value,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
                'role' => $this->creator?->role?->value,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
