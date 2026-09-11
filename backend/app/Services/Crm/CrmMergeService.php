<?php

namespace App\Services\Crm;

use App\Enums\CrmLeadStatus;
use App\Enums\UserRole;
use App\Models\CrmActivity;
use App\Models\CrmAuditLog;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Models\CrmOpportunity;
use App\Models\CrmQuotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmMergeService
{
    public function __construct(private readonly CrmLeadService $leads) {}

    public function merge(User $actor, int $primaryId, int $secondaryId): CrmLead
    {
        if ($primaryId === $secondaryId) {
            throw ValidationException::withMessages([
                'secondary_id' => ['Cannot merge a lead into itself.'],
            ]);
        }

        $primary = CrmLead::query()->findOrFail($primaryId);
        $secondary = CrmLead::query()->findOrFail($secondaryId);
        $this->leads->assertVisible($actor, $primary);
        $this->leads->assertVisible($actor, $secondary);

        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            throw ValidationException::withMessages([
                'lead' => ['Only CRM managers can merge leads.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $primary, $secondary): CrmLead {
            $oldPrimary = $primary->toArray();
            $fields = [
                'full_name', 'company_name', 'job_title', 'phone', 'alt_phone', 'whatsapp',
                'email', 'country', 'city', 'source_id', 'service_id', 'package_id',
                'estimated_budget', 'deal_value', 'priority', 'assigned_to', 'notes',
                'company_id', 'customer_id', 'expected_close_at', 'next_follow_up_at',
                'first_contacted_at', 'last_contacted_at',
            ];

            $updates = [];
            foreach ($fields as $field) {
                $primaryValue = $primary->{$field};
                $secondaryValue = $secondary->{$field};
                if ($this->isEmpty($primaryValue) && ! $this->isEmpty($secondaryValue)) {
                    $updates[$field] = $secondaryValue;
                }
            }

            if ($this->isEmpty($primary->tags) && ! $this->isEmpty($secondary->tags)) {
                $updates['tags'] = $secondary->tags;
            } elseif (is_array($primary->tags) && is_array($secondary->tags)) {
                $updates['tags'] = array_values(array_unique(array_merge($primary->tags, $secondary->tags)));
            }

            if ($updates !== []) {
                $primary->update($updates);
            }

            $tagIds = $secondary->tagModels()->pluck('crm_tags.id')->all();
            if ($tagIds !== []) {
                $primary->tagModels()->syncWithoutDetaching($tagIds);
            }

            CrmActivity::query()->where('lead_id', $secondary->id)->update(['lead_id' => $primary->id]);
            CrmFollowUp::query()->where('lead_id', $secondary->id)->update(['lead_id' => $primary->id]);
            CrmOpportunity::query()->where('lead_id', $secondary->id)->update(['lead_id' => $primary->id]);
            CrmQuotation::query()->where('lead_id', $secondary->id)->update(['lead_id' => $primary->id]);

            $secondary->tagModels()->detach();
            $secondary->update([
                'status' => CrmLeadStatus::Lost->value,
                'archived_at' => now(),
                'notes' => trim(($secondary->notes ?? '')."\nMerged into ".$primary->reference),
                'lost_notes' => 'Merged into lead '.$primary->reference,
            ]);

            CrmAuditLog::query()->create([
                'user_id' => $actor->id,
                'auditable_type' => $primary->getMorphClass(),
                'auditable_id' => $primary->id,
                'action' => 'merged',
                'old_values' => $oldPrimary,
                'new_values' => [
                    'merged_from' => $secondary->id,
                    'merged_reference' => $secondary->reference,
                ],
                'created_at' => now(),
            ]);

            return $this->leads->load($primary->fresh() ?? $primary);
        });
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }
}
