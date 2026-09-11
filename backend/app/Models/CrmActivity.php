<?php

namespace App\Models;

use App\Enums\CrmActivityType;
use App\Enums\CrmCallResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lead_id',
    'opportunity_id',
    'user_id',
    'type',
    'occurred_at',
    'duration_minutes',
    'call_result',
    'result',
    'notes',
    'next_action',
])]
class CrmActivity extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CrmActivityType::class,
            'call_result' => CrmCallResult::class,
            'occurred_at' => 'datetime',
            'duration_minutes' => 'integer',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
