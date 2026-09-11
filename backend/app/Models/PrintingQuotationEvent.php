<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'printing_quotation_id',
    'event',
    'actor_id',
    'actor_type',
    'meta',
])]
class PrintingQuotationEvent extends Model
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
     * @return BelongsTo<PrintingQuotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(PrintingQuotation::class, 'printing_quotation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
