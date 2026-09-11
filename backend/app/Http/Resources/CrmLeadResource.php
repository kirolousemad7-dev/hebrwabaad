<?php

namespace App\Http\Resources;

use App\Models\CrmLead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CrmLead
 */
class CrmLeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'full_name' => $this->full_name,
            'company_name' => $this->company_name,
            'job_title' => $this->job_title,
            'phone' => $this->phone,
            'alt_phone' => $this->alt_phone,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'country' => $this->country,
            'city' => $this->city,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'priority' => $this->priority instanceof \BackedEnum ? $this->priority->value : $this->priority,
            'estimated_budget' => $this->estimated_budget,
            'deal_value' => $this->deal_value,
            'score' => $this->score,
            'tags' => $this->tags,
            'notes' => $this->notes,
            'next_follow_up_at' => $this->next_follow_up_at?->toIso8601String(),
            'expected_close_at' => $this->expected_close_at?->toDateString(),
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'first_contacted_at' => $this->first_contacted_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'needs_attention' => (bool) $this->needs_attention,
            'age_days' => $this->ageDays(),
            'days_in_stage' => $this->daysInStage(),
            'days_since_contact' => $this->daysSinceContact(),
            'converted_at' => $this->converted_at?->toIso8601String(),
            'lost_notes' => $this->lost_notes,
            'competitor_name' => $this->competitor_name,
            'stage' => $this->whenLoaded('stage', fn () => [
                'id' => $this->stage?->id,
                'name' => $this->stage?->name,
                'slug' => $this->stage?->slug,
                'probability' => $this->stage?->probability,
                'is_won' => $this->stage?->is_won,
                'is_lost' => $this->stage?->is_lost,
            ]),
            'source' => $this->whenLoaded('source', fn () => [
                'id' => $this->source?->id,
                'name' => $this->source?->name,
                'slug' => $this->source?->slug,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ]),
            'lost_reason' => $this->whenLoaded('lostReason', fn () => $this->lostReason === null ? null : [
                'id' => $this->lostReason->id,
                'name' => $this->lostReason->name,
            ]),
            'customer_id' => $this->customer_id,
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => $this->company === null ? null : [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'status' => $this->company->status,
            ]),
            'won_order_id' => $this->won_order_id,
            'won_project_id' => $this->won_project_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
