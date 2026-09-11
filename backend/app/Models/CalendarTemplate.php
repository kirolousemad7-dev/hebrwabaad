<?php

namespace App\Models;

use App\Enums\CalendarItemPriority;
use App\Enums\CalendarItemType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'type',
    'priority',
    'title_pattern',
    'description',
    'default_duration_minutes',
    'default_reminders',
    'created_by',
    'is_active',
])]
class CalendarTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CalendarItemType::class,
            'priority' => CalendarItemPriority::class,
            'default_duration_minutes' => 'integer',
            'default_reminders' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
