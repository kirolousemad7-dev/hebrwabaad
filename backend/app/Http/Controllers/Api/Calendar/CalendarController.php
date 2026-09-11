<?php

namespace App\Http\Controllers\Api\Calendar;

use App\Enums\CalendarItemPriority;
use App\Enums\CalendarItemType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\StoreCalendarItemRequest;
use App\Http\Requests\Calendar\UpdateCalendarItemRequest;
use App\Models\CalendarItem;
use App\Models\CalendarItemComment;
use App\Models\CalendarSavedFilter;
use App\Models\CalendarTemplate;
use App\Models\CalendarUserSetting;
use App\Models\ManagedFile;
use App\Models\User;
use App\Notifications\CalendarNotification;
use App\Services\Calendar\CalendarActivityLogger;
use App\Services\Calendar\CalendarIcsExporter;
use App\Services\Calendar\CalendarService;
use App\Services\FileService;
use App\Support\ApiResponse;
use App\Support\Calendar\CalendarOccurrenceReference;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CalendarController extends Controller
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly CalendarIcsExporter $icsExporter,
        private readonly CalendarActivityLogger $activityLogger,
        private readonly FileService $files,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarItem::class);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'scope' => ['nullable', 'in:mine,team'],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:200'],
            'include_linked' => ['nullable', 'in:0,1'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->startOfDay();

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages([
                'from' => 'تاريخ البداية يجب أن يكون قبل أو يساوي تاريخ النهاية.',
            ]);
        }

        if ($from->diffInDays($to) > 93) {
            throw ValidationException::withMessages([
                'to' => 'نطاق التقويم يجب ألا يتجاوز 93 يوماً.',
            ]);
        }

        return ApiResponse::success($this->calendar->listFor($request->user(), $data));
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarItem::class);

        $scope = $request->validate([
            'scope' => ['nullable', 'in:mine,team'],
        ])['scope'] ?? 'mine';

        return ApiResponse::success([
            'summary' => $this->calendar->summaryFor($request->user(), $scope),
            'upcoming' => $this->upcoming($request, $scope),
        ]);
    }

    public function assignees(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarItem::class);

        return ApiResponse::success([
            'items' => $this->calendar->assignableUsers($request->user()),
        ]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarItem::class);

        $filters = $request->validate([
            'scope' => ['nullable', 'in:mine,team'],
            'status' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $page = $this->calendar->tasksList($request->user(), $filters);

        return ApiResponse::success([
            'items' => collect($page->items())->map(fn (CalendarItem $item) => $this->calendar->serialize($item))->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function workload(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarItem::class);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        return ApiResponse::success($this->calendar->workload(
            $request->user(),
            Carbon::parse($data['from'])->startOfDay(),
            Carbon::parse($data['to'])->endOfDay(),
            isset($data['department_id']) ? (int) $data['department_id'] : null,
        ));
    }

    public function conflicts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarItem::class);

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'exclude_id' => ['nullable', 'integer'],
        ]);

        return ApiResponse::success([
            'items' => $this->calendar->conflicts(
                $request->user(),
                $data['starts_at'],
                $data['ends_at'],
                $data['assignee_ids'] ?? [],
                isset($data['exclude_id']) ? (int) $data['exclude_id'] : null,
            ),
        ]);
    }

    public function bulk(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarItem::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required'],
            'changes' => ['required', 'array'],
            'changes.status' => ['nullable', 'string'],
            'changes.priority' => ['nullable', 'string'],
            'changes.assignee_ids' => ['nullable', 'array'],
        ]);

        $ids = [];
        foreach ($data['ids'] as $rawId) {
            try {
                $ids[] = CalendarOccurrenceReference::parse($rawId)->itemId();
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        $ids = array_values(array_unique($ids));
        $result = $this->calendar->bulkUpdate($request->user(), $ids, $data['changes']);

        return ApiResponse::success([
            'items' => $result['items'],
            'skipped_ids' => $result['skipped_ids'],
        ]);
    }

    public function templatesIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarItem::class);

        $items = CalendarTemplate::query()
            ->where('is_active', true)
            ->where(function ($q) use ($request): void {
                $q->where('created_by', $request->user()->id);
                if ($request->user()->role instanceof UserRole && $request->user()->role->canManageWorkCalendar()) {
                    $q->orWhere('is_active', true);
                }
            })
            ->latest()
            ->get();

        return ApiResponse::success(['items' => $items]);
    }

    public function templatesStore(Request $request): JsonResponse
    {
        $this->authorize('create', CalendarItem::class);
        abort_unless(
            $request->user()->role instanceof UserRole && $request->user()->role->canManageWorkCalendar(),
            403
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', Rule::in(CalendarItemType::values())],
            'priority' => ['nullable', 'string', Rule::in(CalendarItemPriority::values())],
            'title_pattern' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'default_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'default_reminders' => ['nullable', 'array'],
        ]);

        $template = CalendarTemplate::query()->create([
            ...$data,
            'priority' => $data['priority'] ?? CalendarItemPriority::Medium->value,
            'default_duration_minutes' => $data['default_duration_minutes'] ?? 60,
            'created_by' => $request->user()->id,
            'is_active' => true,
        ]);

        return ApiResponse::success($template, 201);
    }

    public function settingsShow(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarItem::class);

        $settings = CalendarUserSetting::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'default_view' => 'month',
                'week_starts_on' => 6,
                'workday_start' => '08:00:00',
                'workday_end' => '18:00:00',
                'daily_digest' => false,
                'end_of_day_digest' => false,
                'show_completed' => true,
                'default_scope' => 'mine',
            ],
        );

        return ApiResponse::success($settings);
    }

    public function settingsUpdate(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarItem::class);

        $data = $request->validate([
            'default_view' => ['nullable', 'string', 'in:month,week,day,agenda'],
            'week_starts_on' => ['nullable', 'integer', 'min:0', 'max:6'],
            'workday_start' => ['nullable', 'date_format:H:i'],
            'workday_end' => ['nullable', 'date_format:H:i'],
            'daily_digest' => ['nullable', 'boolean'],
            'end_of_day_digest' => ['nullable', 'boolean'],
            'show_completed' => ['nullable', 'boolean'],
            'default_scope' => ['nullable', 'in:mine,team'],
            'default_reminders' => ['nullable', 'array'],
        ]);

        $settings = CalendarUserSetting::query()->firstOrCreate(['user_id' => $request->user()->id]);
        $settings->fill($data)->save();

        return ApiResponse::success($settings->fresh());
    }

    public function savedFiltersIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarItem::class);

        $items = CalendarSavedFilter::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return ApiResponse::success(['items' => $items]);
    }

    public function savedFiltersStore(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarItem::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'filters' => ['required', 'array'],
        ]);

        $filter = CalendarSavedFilter::query()->create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'filters' => $data['filters'],
        ]);

        return ApiResponse::success($filter, 201);
    }

    public function savedFiltersDestroy(Request $request, CalendarSavedFilter $savedFilter): JsonResponse
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 403);
        $savedFilter->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function exportIcs(Request $request): Response
    {
        $this->authorize('viewAny', CalendarItem::class);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'scope' => ['nullable', 'in:mine,team'],
        ]);

        $result = $this->calendar->listFor($request->user(), [
            ...$data,
            'include_linked' => '0',
        ]);

        $ids = collect($result['items'])
            ->filter(fn (array $row) => is_numeric($row['id'] ?? null))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $items = CalendarItem::query()->whereIn('id', $ids)->get();
        $ics = $this->icsExporter->exportMany($items);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="calendar-export.ics"',
        ]);
    }

    public function store(StoreCalendarItemRequest $request): JsonResponse
    {
        $this->authorize('create', CalendarItem::class);

        $item = $this->calendar->create($request->user(), $request->validated());

        return ApiResponse::success($this->calendar->serialize($item), 201);
    }

    public function show(Request $request, CalendarItem $calendarItem): JsonResponse
    {
        $this->authorize('view', $calendarItem);

        $calendarItem->load(['creator', 'assignees', 'reminders'])->loadCount(['comments', 'files']);

        return ApiResponse::success($this->calendar->serialize($calendarItem));
    }

    public function update(UpdateCalendarItemRequest $request, CalendarItem $calendarItem): JsonResponse
    {
        $this->authorize('update', $calendarItem);

        $payload = $request->validated();
        $hint = $this->occurrenceHint($request, $calendarItem);
        if ($hint !== null) {
            $payload['occurrence_at'] = $payload['occurrence_at'] ?? $hint;
        }

        if ($calendarItem->isRecurringMaster() && empty($payload['scope']) && $hint !== null) {
            $payload['scope'] = 'this';
        }

        $item = $this->calendar->update($request->user(), $calendarItem, $payload);

        return ApiResponse::success($this->calendar->serialize($item));
    }

    public function destroy(Request $request, CalendarItem $calendarItem): JsonResponse
    {
        $this->authorize('delete', $calendarItem);

        $data = $request->validate([
            'scope' => ['nullable', 'in:this,future,all'],
            'occurrence_at' => ['nullable', 'date'],
        ]);

        $hint = $this->occurrenceHint($request, $calendarItem);
        $occurrenceAt = $data['occurrence_at'] ?? $hint;
        $scope = $data['scope'] ?? (($calendarItem->isRecurringMaster() && $hint !== null) ? 'this' : 'all');

        if ($calendarItem->isRecurringMaster() && $scope !== 'all') {
            $this->calendar->deleteRecurring(
                $request->user(),
                $calendarItem,
                $scope,
                $occurrenceAt !== null ? Carbon::parse($occurrenceAt) : null,
            );
        } else {
            $this->calendar->deleteRecurring($request->user(), $calendarItem, 'all');
        }

        return ApiResponse::success(null);
    }

    public function complete(Request $request, CalendarItem $calendarItem): JsonResponse
    {
        $this->authorize('complete', $calendarItem);

        $data = $request->validate([
            'scope' => ['nullable', 'in:this,future,all'],
            'occurrence_at' => ['nullable', 'date'],
        ]);

        $hint = $this->occurrenceHint($request, $calendarItem);
        $occurrenceAt = $data['occurrence_at'] ?? $hint;
        $scope = $data['scope'] ?? null;

        if ($calendarItem->isRecurringMaster() && $scope === 'this' && $occurrenceAt !== null) {
            $standalone = $this->calendar->updateRecurring($request->user(), $calendarItem, [
                'occurrence_at' => $occurrenceAt,
                'scope' => 'this',
            ], 'this');
            $item = $this->calendar->complete($request->user(), $standalone);

            return ApiResponse::success($this->calendar->serialize($item));
        }

        $item = $this->calendar->complete($request->user(), $calendarItem);

        return ApiResponse::success($this->calendar->serialize($item));
    }

    public function reschedule(Request $request, CalendarItem $calendarItem): JsonResponse
    {
        $this->authorize('update', $calendarItem);

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'scope' => ['nullable', 'in:this,future,all'],
            'occurrence_at' => ['nullable', 'date'],
        ]);

        $hint = $this->occurrenceHint($request, $calendarItem);
        $occurrenceAt = $data['occurrence_at'] ?? $hint;
        $scope = $data['scope'] ?? (($calendarItem->isRecurringMaster() && $hint !== null) ? 'this' : null);

        $item = $this->calendar->reschedule(
            $request->user(),
            $calendarItem,
            $data['starts_at'],
            $data['ends_at'] ?? null,
            $scope,
            $occurrenceAt,
        );

        return ApiResponse::success($this->calendar->serialize($item));
    }

    public function duplicate(Request $request, CalendarItem $calendarItem): JsonResponse
    {
        $this->authorize('view', $calendarItem);
        $this->authorize('create', CalendarItem::class);

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
        ]);

        $item = $this->calendar->duplicate($request->user(), $calendarItem, $data['starts_at']);

        return ApiResponse::success($this->calendar->serialize($item), 201);
    }

    public function checklist(Request $request, CalendarItem $calendarItem): JsonResponse
    {
        $this->authorize('update', $calendarItem);

        $data = $request->validate([
            'checklist' => ['required', 'array'],
            'checklist.*.id' => ['nullable', 'string', 'max:64'],
            'checklist.*.text' => ['required', 'string', 'max:500'],
            'checklist.*.done' => ['nullable', 'boolean'],
        ]);

        $item = $this->calendar->syncChecklist($request->user(), $calendarItem, $data['checklist']);

        return ApiResponse::success($this->calendar->serialize($item));
    }

    public function activities(Request $request, CalendarItem $calendarItem): JsonResponse
    {
        $this->authorize('view', $calendarItem);

        $items = $calendarItem->activities()
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($activity) => [
                'id' => $activity->id,
                'action' => $activity->action,
                'summary' => $activity->summary,
                'meta' => $activity->meta,
                'user' => $activity->user ? ['id' => $activity->user->id, 'name' => $activity->user->name] : null,
                'created_at' => $activity->created_at?->toIso8601String(),
            ])
            ->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function downloadIcs(Request $request, CalendarItem $calendarItem): Response
    {
        $this->authorize('view', $calendarItem);

        $ics = $this->icsExporter->exportOne($calendarItem);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="calendar-item-'.$calendarItem->id.'.ics"',
        ]);
    }

    public function commentsIndex(Request $request, CalendarItem $calendarItem): JsonResponse
    {
        $this->authorize('view', $calendarItem);

        $items = $calendarItem->comments()
            ->with('user:id,name')
            ->latest()
            ->get()
            ->map(fn (CalendarItemComment $comment) => $this->serializeComment($comment))
            ->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function commentsStore(Request $request, CalendarItem $calendarItem): JsonResponse
    {
        $this->authorize('update', $calendarItem);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment = CalendarItemComment::query()->create([
            'calendar_item_id' => $calendarItem->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        $this->notifyMentions($request->user(), $calendarItem, $data['body']);
        $this->activityLogger->log($calendarItem, $request->user(), 'commented', 'تم إضافة تعليق');

        return ApiResponse::success($this->serializeComment($comment->load('user:id,name')), 201);
    }

    public function commentsUpdate(Request $request, CalendarItemComment $comment): JsonResponse
    {
        $comment->load('item');
        $this->authorize('update', $comment->item);
        abort_unless((int) $comment->user_id === (int) $request->user()->id
            || ($request->user()->role instanceof UserRole && $request->user()->role->canManageWorkCalendar()), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment->update([
            'body' => $data['body'],
            'edited_at' => now(),
        ]);

        return ApiResponse::success($this->serializeComment($comment->fresh('user:id,name')));
    }

    public function commentsDestroy(Request $request, CalendarItemComment $comment): JsonResponse
    {
        $comment->load('item');
        $this->authorize('update', $comment->item);
        abort_unless((int) $comment->user_id === (int) $request->user()->id
            || ($request->user()->role instanceof UserRole && $request->user()->role->canManageWorkCalendar()), 403);

        $comment->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function filesIndex(Request $request, CalendarItem $calendarItem): JsonResponse
    {
        $this->authorize('view', $calendarItem);

        $items = $calendarItem->files()->with($this->files->eagerLoad())->latest()->get();

        return ApiResponse::success([
            'items' => $items->map(fn (ManagedFile $file) => [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'uploaded_by' => $file->uploaded_by,
                'created_at' => $file->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function filesStore(Request $request, CalendarItem $calendarItem): JsonResponse
    {
        $this->authorize('update', $calendarItem);

        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $file = $this->files->store(
            $request->user(),
            $request->file('file'),
            ['calendar_item_id' => $calendarItem->id],
        );

        $this->activityLogger->log($calendarItem, $request->user(), 'attachment_added', 'تم إرفاق ملف');

        return ApiResponse::success([
            'id' => $file->id,
            'original_name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
        ], 201);
    }

    public function filesDetach(Request $request, CalendarItem $calendarItem, ManagedFile $file): JsonResponse
    {
        $this->authorize('update', $calendarItem);
        abort_unless((int) $file->calendar_item_id === (int) $calendarItem->id, 404);

        $file->update(['calendar_item_id' => null]);
        $this->activityLogger->log($calendarItem, $request->user(), 'attachment_removed', 'تم فصل مرفق');

        return ApiResponse::success(['detached' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeComment(CalendarItemComment $comment): array
    {
        return [
            'id' => $comment->id,
            'calendar_item_id' => $comment->calendar_item_id,
            'body' => $comment->body,
            'edited_at' => $comment->edited_at?->toIso8601String(),
            'created_at' => $comment->created_at?->toIso8601String(),
            'user' => $comment->user ? [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
            ] : null,
        ];
    }

    private function notifyMentions(User $actor, CalendarItem $item, string $body): void
    {
        $assignable = collect($this->calendar->assignableUsers($actor));
        $assignableIds = $assignable->pluck('id')->map(fn ($id) => (int) $id)->all();
        $assignableByName = $assignable
            ->keyBy(fn (array $row) => mb_strtolower((string) $row['name']))
            ->map(fn (array $row) => (int) $row['id']);

        $ids = [];
        if (preg_match_all('/@user:(\d+)/', $body, $matches)) {
            foreach ($matches[1] as $rawId) {
                $id = (int) $rawId;
                if (in_array($id, $assignableIds, true)) {
                    $ids[] = $id;
                }
            }
        }
        if (preg_match_all('/@([\p{L}\p{N}_\.\-]+)/u', $body, $nameMatches)) {
            foreach ($nameMatches[1] as $name) {
                if (str_starts_with(strtolower($name), 'user:')) {
                    continue;
                }
                $matchedId = $assignableByName->get(mb_strtolower($name));
                if ($matchedId !== null) {
                    $ids[] = $matchedId;
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids, fn (int $id) => $id !== (int) $actor->id)));
        if ($ids === []) {
            return;
        }

        foreach (User::query()->whereIn('id', $ids)->where('is_active', true)->get() as $recipient) {
            if (! ($recipient->role instanceof UserRole) || ! $recipient->role->canAccessWorkCalendar()) {
                continue;
            }

            $href = $recipient->role === UserRole::Owner
                ? '/owner/calendar?item='.$item->id
                : ($recipient->role->canAccessCrm()
                    ? '/crm/work-calendar?item='.$item->id
                    : '/workspace/calendar?item='.$item->id);

            $recipient->notify(new CalendarNotification([
                'type' => 'calendar_mention',
                'title' => 'تمت الإشارة إليك في التقويم',
                'message' => $item->title,
                'href' => $href,
                'calendar_item_id' => $item->id,
            ]));
        }
    }

    private function occurrenceHint(Request $request, CalendarItem $item): ?string
    {
        $fromRequest = $request->input('occurrence_at');
        if (is_string($fromRequest) && $fromRequest !== '') {
            return $fromRequest;
        }

        $resolved = $item->resolved_occurrence_at;

        return is_string($resolved) && $resolved !== '' ? $resolved : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function upcoming(Request $request, string $scope): array
    {
        $result = $this->calendar->listFor($request->user(), [
            'from' => now()->toDateString(),
            'to' => now()->addDays(7)->toDateString(),
            'scope' => $scope,
            'include_linked' => '1',
        ]);

        return array_slice(array_values(array_filter(
            $result['items'],
            fn (array $item) => ! in_array($item['status'] ?? '', ['COMPLETED', 'CANCELLED'], true),
        )), 0, 8);
    }
}
