<?php

namespace App\Http\Controllers\Api\GoogleCalendar;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\GoogleCalendar\GoogleCalendarTaskSyncService;
use App\Services\GoogleCalendar\TaskReminderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskGoogleCalendarController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarTaskSyncService $sync,
        private readonly TaskReminderService $reminders,
    ) {}

    public function status(Request $request, Task $task): JsonResponse
    {
        $this->assertCanView($request, $task);

        return ApiResponse::success($this->sync->syncPayload($task));
    }

    public function enable(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'timezone' => ['nullable', 'timezone'],
            'location' => ['nullable', 'string', 'max:255'],
            'google_meet_enabled' => ['sometimes', 'boolean'],
            'reminders' => ['nullable', 'array', 'max:10'],
            'reminders.*' => ['nullable'],
        ]);

        if (isset($data['start_at']) || isset($data['due_at']) || isset($data['timezone']) || isset($data['location']) || array_key_exists('google_meet_enabled', $data)) {
            $task->fill([
                'start_at' => $data['start_at'] ?? $task->start_at,
                'due_at' => $data['due_at'] ?? $task->due_at,
                'timezone' => $data['timezone'] ?? $task->timezone,
                'location' => $data['location'] ?? $task->location,
                'google_meet_enabled' => array_key_exists('google_meet_enabled', $data)
                    ? (bool) $data['google_meet_enabled']
                    : $task->google_meet_enabled,
            ])->save();
        }

        if (isset($data['reminders']) && is_array($data['reminders'])) {
            $this->reminders->syncReminders($task->fresh() ?? $task, $data['reminders']);
        } else {
            $this->reminders->ensureDefaultReminders($task);
        }

        $updated = $this->sync->enableAndSync($request->user(), $task->fresh() ?? $task);

        return ApiResponse::success($this->sync->syncPayload($updated));
    }

    public function sync(Request $request, Task $task): JsonResponse
    {
        $updated = $this->sync->enableAndSync($request->user(), $task);

        return ApiResponse::success($this->sync->syncPayload($updated));
    }

    public function disable(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'delete_remote' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->sync->disableAndCancel(
            $request->user(),
            $task,
            (bool) ($data['delete_remote'] ?? true),
        );

        return ApiResponse::success($this->sync->syncPayload($updated));
    }

    public function updateReminders(Request $request, Task $task): JsonResponse
    {
        $this->assertCanView($request, $task);

        $request->validate([
            'reminders' => ['required', 'array', 'max:10'],
        ]);

        $offsets = [];
        foreach ($request->input('reminders', []) as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $minutes = (int) $value;
                if ($minutes >= 0 && $minutes <= 10080) {
                    $offsets[] = $minutes;
                }
            } elseif (is_string($value) && in_array($value, [
                'AT_START', 'MINUTES_5', 'MINUTES_15', 'MINUTES_30', 'HOUR_1', 'DAY_1',
            ], true)) {
                $offsets[] = $value;
            }
        }

        $created = $this->reminders->syncReminders($task, $offsets);

        return ApiResponse::success([
            'reminders' => $created->map(fn ($reminder): array => [
                'id' => $reminder->id,
                'offset' => $reminder->offset,
                'custom_minutes' => $reminder->custom_minutes,
                'remind_at' => $reminder->remind_at?->toIso8601String(),
                'sent_at' => $reminder->sent_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    private function assertCanView(Request $request, Task $task): void
    {
        $user = $request->user();
        if ((int) $task->assigned_to === (int) $user->id || (int) $task->created_by === (int) $user->id) {
            return;
        }
        $task->loadMissing('project');
        if ($task->project && (int) $task->project->account_manager_id === (int) $user->id) {
            return;
        }
        if ($user->role instanceof UserRole && in_array($user->role, [UserRole::Owner, UserRole::AdminManager], true)) {
            return;
        }

        abort(403);
    }
}
