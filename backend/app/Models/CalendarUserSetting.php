<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'default_view',
    'week_starts_on',
    'workday_start',
    'workday_end',
    'daily_digest',
    'end_of_day_digest',
    'show_completed',
    'default_scope',
    'default_reminders',
])]
class CalendarUserSetting extends Model
{
    protected function casts(): array
    {
        return [
            'week_starts_on' => 'integer',
            'daily_digest' => 'boolean',
            'end_of_day_digest' => 'boolean',
            'show_completed' => 'boolean',
            'default_reminders' => 'array',
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
