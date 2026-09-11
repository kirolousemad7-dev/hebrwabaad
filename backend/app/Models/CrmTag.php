<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name',
    'slug',
    'color',
])]
class CrmTag extends Model
{
    /**
     * @return BelongsToMany<CrmLead, $this>
     */
    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(CrmLead::class, 'crm_lead_tag');
    }
}
