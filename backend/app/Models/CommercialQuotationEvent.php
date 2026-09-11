<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'commercial_quotation_id',
    'actor_id',
    'actor_type',
    'event_type',
    'meta',
])]
class CommercialQuotationEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CommercialQuotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(CommercialQuotation::class, 'commercial_quotation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
