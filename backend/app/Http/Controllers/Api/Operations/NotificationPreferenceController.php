<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\UserNotificationPreference;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $prefs = UserNotificationPreference::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            UserNotificationPreference::defaults(),
        );

        return ApiResponse::success($this->serialize($prefs));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'calendar_assignments' => ['sometimes', 'boolean'],
            'task_reminders' => ['sometimes', 'boolean'],
            'overdue_alerts' => ['sometimes', 'boolean'],
            'mentions' => ['sometimes', 'boolean'],
            'automation_notifications' => ['sometimes', 'boolean'],
            'project_alerts' => ['sometimes', 'boolean'],
            'daily_digest' => ['sometimes', 'boolean'],
            'approval_requests' => ['sometimes', 'boolean'],
            'printing_alerts' => ['sometimes', 'boolean'],
            'crm_alerts' => ['sometimes', 'boolean'],
            'quiet_hours_enabled' => ['sometimes', 'boolean'],
            'quiet_hours_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'quiet_hours_timezone' => ['sometimes', 'nullable', 'timezone'],
        ]);

        $prefs = UserNotificationPreference::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            UserNotificationPreference::defaults(),
        );

        $prefs->fill($data)->save();

        return ApiResponse::success($this->serialize($prefs->fresh() ?? $prefs));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(UserNotificationPreference $prefs): array
    {
        return [
            'calendar_assignments' => (bool) $prefs->calendar_assignments,
            'task_reminders' => (bool) $prefs->task_reminders,
            'overdue_alerts' => (bool) $prefs->overdue_alerts,
            'mentions' => (bool) $prefs->mentions,
            'automation_notifications' => (bool) $prefs->automation_notifications,
            'project_alerts' => (bool) $prefs->project_alerts,
            'daily_digest' => (bool) $prefs->daily_digest,
            'approval_requests' => (bool) $prefs->approval_requests,
            'printing_alerts' => (bool) ($prefs->printing_alerts ?? true),
            'crm_alerts' => (bool) ($prefs->crm_alerts ?? true),
            'quiet_hours_enabled' => (bool) $prefs->quiet_hours_enabled,
            'quiet_hours_start' => $prefs->quiet_hours_start
                ? substr((string) $prefs->quiet_hours_start, 0, 5)
                : null,
            'quiet_hours_end' => $prefs->quiet_hours_end
                ? substr((string) $prefs->quiet_hours_end, 0, 5)
                : null,
            'quiet_hours_timezone' => $prefs->quiet_hours_timezone,
        ];
    }
}
