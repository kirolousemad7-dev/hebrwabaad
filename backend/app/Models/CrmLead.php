<?php

namespace App\Models;

use App\Enums\CrmLeadPriority;
use App\Enums\CrmLeadStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference',
    'full_name',
    'company_name',
    'job_title',
    'phone',
    'alt_phone',
    'whatsapp',
    'email',
    'country',
    'city',
    'source_id',
    'service_id',
    'package_id',
    'estimated_budget',
    'deal_value',
    'status',
    'stage_id',
    'priority',
    'assigned_to',
    'next_follow_up_at',
    'expected_close_at',
    'score',
    'tags',
    'notes',
    'lost_reason_id',
    'lost_notes',
    'competitor_name',
    'customer_id',
    'contact_inquiry_id',
    'consultation_lead_id',
    'company_id',
    'last_contacted_at',
    'first_contacted_at',
    'converted_at',
    'won_order_id',
    'won_project_id',
    'created_by',
    'archived_at',
    'needs_attention',
])]
class CrmLead extends Model
{
    protected function casts(): array
    {
        return [
            'status' => CrmLeadStatus::class,
            'priority' => CrmLeadPriority::class,
            'estimated_budget' => 'decimal:2',
            'deal_value' => 'decimal:2',
            'score' => 'integer',
            'tags' => 'array',
            'next_follow_up_at' => 'datetime',
            'expected_close_at' => 'date',
            'last_contacted_at' => 'datetime',
            'first_contacted_at' => 'datetime',
            'converted_at' => 'datetime',
            'archived_at' => 'datetime',
            'needs_attention' => 'boolean',
        ];
    }

    public function ageDays(): int
    {
        return (int) ($this->created_at?->diffInDays(now()) ?? 0);
    }

    public function daysInStage(): int
    {
        return (int) ($this->updated_at?->diffInDays(now()) ?? 0);
    }

    public function daysSinceContact(): ?int
    {
        if ($this->last_contacted_at === null) {
            return null;
        }

        return (int) $this->last_contacted_at->diffInDays(now());
    }

    /**
     * @return BelongsTo<CrmPipelineStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmPipelineStage::class, 'stage_id');
    }

    /**
     * @return BelongsTo<CrmLeadSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(CrmLeadSource::class, 'source_id');
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<CrmCompany, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    /**
     * @return BelongsTo<CrmLostReason, $this>
     */
    public function lostReason(): BelongsTo
    {
        return $this->belongsTo(CrmLostReason::class, 'lost_reason_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * @return BelongsTo<ContactInquiry, $this>
     */
    public function contactInquiry(): BelongsTo
    {
        return $this->belongsTo(ContactInquiry::class, 'contact_inquiry_id');
    }

    /**
     * @return BelongsTo<ConsultationLead, $this>
     */
    public function consultationLead(): BelongsTo
    {
        return $this->belongsTo(ConsultationLead::class, 'consultation_lead_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function wonOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'won_order_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function wonProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'won_project_id');
    }

    /**
     * @return HasMany<CrmActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'lead_id');
    }

    /**
     * @return HasMany<CrmFollowUp, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(CrmFollowUp::class, 'lead_id');
    }

    /**
     * @return HasMany<CrmQuotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(CrmQuotation::class, 'lead_id');
    }

    /**
     * @return HasMany<CrmOpportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'lead_id');
    }

    /**
     * @return BelongsToMany<CrmTag, $this>
     */
    public function tagModels(): BelongsToMany
    {
        return $this->belongsToMany(CrmTag::class, 'crm_lead_tag');
    }
}
