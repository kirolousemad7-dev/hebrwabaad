<?php

namespace App\Models;

use App\Enums\CalendarItemPriority;
use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\CalendarSource;
use App\Enums\CalendarVisibility;
use Database\Factories\CalendarItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Calendar times are stored in UTC (app timezone).
 */
#[Fillable([
    'title',
    'description',
    'type',
    'status',
    'priority',
    'visibility',
    'source',
    'starts_at',
    'ends_at',
    'all_day',
    'created_by',
    'related_type',
    'related_id',
    'related_label',
    'related_href',
    'completed_at',
    'recurrence_rule',
    'recurrence_until',
    'recurrence_count',
    'recurrence_parent_id',
    'recurrence_exceptions',
    'recurrence_instance_at',
    'location',
    'meeting_url',
    'blocked_by_id',
    'checklist',
    'department_id',
])]
class CalendarItem extends Model
{
    /** @use HasFactory<CalendarItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Runtime-only hint from virtual route ids (never persisted).
     */
    public ?string $resolved_occurrence_at = null;

    protected function casts(): array
    {
        return [
            'type' => CalendarItemType::class,
            'status' => CalendarItemStatus::class,
            'priority' => CalendarItemPriority::class,
            'visibility' => CalendarVisibility::class,
            'source' => CalendarSource::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'completed_at' => 'datetime',
            'recurrence_until' => 'datetime',
            'recurrence_count' => 'integer',
            'recurrence_exceptions' => 'array',
            'recurrence_instance_at' => 'datetime',
            'checklist' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'calendar_item_assignees')
            ->withTimestamps();
    }

    /**
     * @return HasMany<CalendarReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(CalendarReminder::class);
    }

    /**
     * @return BelongsTo<CalendarItem, $this>
     */
    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurrence_parent_id');
    }

    /**
     * @return HasMany<CalendarItem, $this>
     */
    public function recurrenceChildren(): HasMany
    {
        return $this->hasMany(self::class, 'recurrence_parent_id');
    }

    /**
     * @return BelongsTo<CalendarItem, $this>
     */
    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'blocked_by_id');
    }

    /**
     * @return HasMany<CalendarItemComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(CalendarItemComment::class);
    }

    /**
     * @return HasMany<CalendarItemActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(CalendarItemActivity::class);
    }

    /**
     * @return HasMany<ManagedFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ManagedFile::class, 'calendar_item_id');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [CalendarItemStatus::Completed, CalendarItemStatus::Cancelled], true);
    }

    public function isRecurringMaster(): bool
    {
        return filled($this->recurrence_rule) && $this->recurrence_parent_id === null;
    }
}
