<?php

namespace App\Http\Requests\Calendar;

use App\Enums\CalendarItemPriority;
use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\CalendarReminderOffset;
use App\Enums\CalendarSource;
use App\Enums\CalendarVisibility;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreCalendarItemRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'type' => ['required', 'string', Rule::in(CalendarItemType::values())],
            'status' => ['nullable', 'string', Rule::in(CalendarItemStatus::values())],
            'priority' => ['nullable', 'string', Rule::in(CalendarItemPriority::values())],
            'visibility' => ['nullable', 'string', Rule::in(CalendarVisibility::values())],
            'source' => ['nullable', 'string', Rule::in(CalendarSource::values())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['sometimes', 'boolean'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'related_type' => ['nullable', 'string', 'max:60'],
            'related_id' => ['nullable', 'integer'],
            'reminders' => ['nullable', 'array'],
            'reminders.*' => ['string', Rule::in(CalendarReminderOffset::values())],
            'recurrence_rule' => ['nullable', 'string', 'max:255'],
            'recurrence_until' => ['nullable', 'date'],
            'recurrence_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'recurrence_exceptions' => ['nullable', 'array'],
            'recurrence_exceptions.*' => ['date'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'string', 'max:500'],
            'blocked_by_id' => ['nullable', 'integer', 'exists:calendar_items,id'],
            'checklist' => ['nullable', 'array'],
            'checklist.*.id' => ['nullable', 'string', 'max:64'],
            'checklist.*.text' => ['required_with:checklist', 'string', 'max:500'],
            'checklist.*.done' => ['nullable', 'boolean'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'scope' => ['nullable', 'in:this,future,all'],
            'occurrence_at' => ['nullable', 'date'],
        ];
    }
}
