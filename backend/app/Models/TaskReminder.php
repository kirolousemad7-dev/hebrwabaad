<?php

namespace App\Models;

use App\Enums\CalendarReminderOffset;
use Database\Factories\TaskReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'task_id',
    'offset',
    'custom_minutes',
    'remind_at',
    'sent_at',
])]
class TaskReminder extends Model
{
    /** @use HasFactory<TaskReminderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_minutes' => 'integer',
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function minutesBefore(): int
    {
        if ($this->custom_minutes !== null) {
            return (int) $this->custom_minutes;
        }

        if (is_string($this->offset) && $this->offset !== '') {
            $enum = CalendarReminderOffset::tryFrom($this->offset);

            return $enum?->minutesBefore() ?? 0;
        }

        return 0;
    }
}
