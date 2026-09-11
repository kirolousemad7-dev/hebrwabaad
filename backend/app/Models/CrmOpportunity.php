<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference',
    'name',
    'lead_id',
    'company_id',
    'customer_id',
    'service_id',
    'package_id',
    'deal_value',
    'probability',
    'stage_id',
    'assigned_to',
    'expected_close_at',
    'competitor',
    'decision_maker',
    'notes',
    'lost_reason_id',
    'lost_notes',
    'won_at',
    'lost_at',
    'order_id',
    'project_id',
    'archived_at',
])]
class CrmOpportunity extends Model
{
    protected function casts(): array
    {
        return [
            'deal_value' => 'decimal:2',
            'probability' => 'integer',
            'expected_close_at' => 'date',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
            'archived_at' => 'datetime',
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
     * @return BelongsTo<CrmPipelineStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmPipelineStage::class, 'stage_id');
    }

    /**
     * @return BelongsTo<CrmCompany, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return HasMany<CrmActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'opportunity_id');
    }

    public function weightedRevenue(): float
    {
        return (float) $this->deal_value * ((int) $this->probability / 100);
    }
}
