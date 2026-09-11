<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'source_type',
    'source_id',
    'rule_key',
    'level',
    'last_notified_at',
    'next_eligible_at',
    'meta',
])]
class OperationalEscalationState extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'level' => 'integer',
            'last_notified_at' => 'datetime',
            'next_eligible_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
