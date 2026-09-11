<?php

namespace App\Models;

use App\Enums\CrmFollowUpStatus;
use App\Enums\CrmFollowUpType;
use App\Enums\CrmLeadPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lead_id',
    'opportunity_id',
    'assigned_to',
    'type',
    'scheduled_at',
    'priority',
    'notes',
    'status',
    'completed_at',
])]
class CrmFollowUp extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CrmFollowUpType::class,
            'status' => CrmFollowUpStatus::class,
            'priority' => CrmLeadPriority::class,
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CrmLead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    /**
     * @return BelongsTo<CrmOpportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
