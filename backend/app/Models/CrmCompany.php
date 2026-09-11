<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'industry',
    'website',
    'country',
    'city',
    'address',
    'phone',
    'email',
    'company_size',
    'source_id',
    'assigned_to',
    'notes',
    'status',
    'archived_at',
])]
class CrmCompany extends Model
{
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
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
     * @return HasMany<CrmContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class, 'company_id');
    }

    /**
     * @return HasMany<CrmLead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'company_id');
    }

    /**
     * @return HasMany<CrmOpportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'company_id');
    }
}
