<?php

namespace App\Models;

use App\Enums\CalendarReminderOffset;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'calendar_item_id',
    'offset',
    'remind_at',
    'sent_at',
])]
class CalendarReminder extends Model
{
    protected function casts(): array
    {
        return [
            'offset' => CalendarReminderOffset::class,
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CalendarItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(CalendarItem::class, 'calendar_item_id');
    }
}
