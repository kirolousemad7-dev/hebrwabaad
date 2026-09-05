<?php

namespace App\Models;

use App\Enums\ConsultationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'public_token',
    'user_id',
    'status',
    'state',
    'diagnosis',
    'readiness',
    'recommendations',
    'completed_at',
])]
class Consultation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConsultationStatus::class,
            'state' => 'array',
            'diagnosis' => 'array',
            'readiness' => 'array',
            'recommendations' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<ConsultationLead, $this>
     */
    public function lead(): HasOne
    {
        return $this->hasOne(ConsultationLead::class);
    }

    /**
     * @return HasMany<ConsultationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ConsultationEvent::class);
    }
}
