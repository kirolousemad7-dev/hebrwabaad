<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'attention_key',
    'snoozed_until',
])]
class OperationalAttentionSnooze extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snoozed_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
