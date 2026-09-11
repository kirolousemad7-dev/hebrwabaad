<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\CalendarItem;
use App\Models\Task;
use App\Services\Calendar\CalendarService;
use App\Services\Operations\Work\TaskCalendarLinkService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskCalendarLinkController extends Controller
{
    public function __construct(
        private readonly TaskCalendarLinkService $links,
        private readonly CalendarService $calendar,
    ) {}

    public function linkFromTask(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $this->authorize('create', CalendarItem::class);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['sometimes', 'boolean'],
            'reminders' => ['nullable', 'array'],
            'reminders.*' => ['string'],
        ]);

        $item = $this->links->linkFromTask($request->user(), $task, $validated);

        return ApiResponse::success([
            'task' => (new TaskResource($task->fresh(['assignee', 'creator', 'project', 'calendarItem'])))->resolve(),
            'calendar_item' => $this->calendar->serialize($item),
        ], 201);
    }

    public function linkFromCalendar(Request $request, CalendarItem $calendarItem): JsonResponse
    {
        $this->authorize('update', $calendarItem);
        $this->authorize('create', Task::class);

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        $task = $this->links->linkFromCalendar(
            $request->user(),
            $calendarItem,
            isset($validated['project_id']) ? (int) $validated['project_id'] : null,
        );

        return ApiResponse::success([
            'task' => (new TaskResource($task))->resolve(),
            'calendar_item' => $this->calendar->serialize(
                $calendarItem->fresh(['creator', 'assignees', 'reminders'])->loadCount(['comments', 'files'])
            ),
        ], 201);
    }

    public function unlink(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        if ($task->calendar_item_id !== null) {
            $calendar = CalendarItem::query()->find((int) $task->calendar_item_id);
            if ($calendar !== null) {
                $this->authorize('update', $calendar);
            }
        }

        $this->links->unlink($request->user(), $task);

        return ApiResponse::success([
            'task_id' => $task->id,
            'unlinked' => true,
        ]);
    }
}
