<?php

namespace App\Services\Calendar;

use App\Models\CalendarItem;
use App\Models\CalendarItemActivity;
use App\Models\User;

class CalendarActivityLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(CalendarItem $item, ?User $actor, string $action, string $summary, array $meta = []): CalendarItemActivity
    {
        return CalendarItemActivity::query()->create([
            'calendar_item_id' => $item->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'summary' => $summary,
            'meta' => $meta === [] ? null : $meta,
            'created_at' => now(),
        ]);
    }
}
