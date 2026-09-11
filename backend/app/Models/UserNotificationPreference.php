<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'calendar_assignments',
    'task_reminders',
    'overdue_alerts',
    'mentions',
    'automation_notifications',
    'project_alerts',
    'daily_digest',
    'approval_requests',
    'printing_alerts',
    'crm_alerts',
    'quiet_hours_enabled',
    'quiet_hours_start',
    'quiet_hours_end',
    'quiet_hours_timezone',
])]
class UserNotificationPreference extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calendar_assignments' => 'boolean',
            'task_reminders' => 'boolean',
            'overdue_alerts' => 'boolean',
            'mentions' => 'boolean',
            'automation_notifications' => 'boolean',
            'project_alerts' => 'boolean',
            'daily_digest' => 'boolean',
            'approval_requests' => 'boolean',
            'printing_alerts' => 'boolean',
            'crm_alerts' => 'boolean',
            'quiet_hours_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'calendar_assignments' => true,
            'task_reminders' => true,
            'overdue_alerts' => true,
            'mentions' => true,
            'automation_notifications' => true,
            'project_alerts' => true,
            'daily_digest' => false,
            'approval_requests' => true,
            'printing_alerts' => true,
            'crm_alerts' => true,
            'quiet_hours_enabled' => false,
            'quiet_hours_start' => null,
            'quiet_hours_end' => null,
            'quiet_hours_timezone' => null,
        ];
    }
}
