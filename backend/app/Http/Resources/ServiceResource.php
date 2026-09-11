<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'scope' => $this->scope,
            'deliverables' => $this->deliverables ?? [],
            'category' => $this->category->value,
            'base_price' => $this->base_price,
            'currency' => $this->currency,
            'pricing_mode' => $this->pricingMode()->value,
            'pricing_label' => $this->pricingMode()->label(),
            'is_chargeable' => $this->isChargeable(),
            'duration_days' => $this->duration_days,
            'revision_rounds' => $this->revision_rounds,
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            ...CatalogVisibility::managementFields($request, fn () => [
                'is_active' => $this->is_active,
                'is_public' => $this->is_public,
                'department_id' => $this->department_id,
                'task_title_template' => $this->task_title_template,
                'default_task_priority' => $this->default_task_priority,
                'requires_review' => (bool) $this->requires_review,
                'requires_customer_approval' => (bool) $this->requires_customer_approval,
                'checklist_template' => $this->checklist_template ?? [],
                'packages_count' => $this->whenCounted('packageItems'),
            ]),
        ];
    }
}
