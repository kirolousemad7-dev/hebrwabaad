<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'is_won',
    'is_lost',
    'probability',
    'sort_order',
    'is_active',
])]
class CrmPipelineStage extends Model
{
    protected function casts(): array
    {
        return [
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
            'is_active' => 'boolean',
            'probability' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<CrmLead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'stage_id');
    }

    /**
     * @return HasMany<CrmOpportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'stage_id');
    }
}
