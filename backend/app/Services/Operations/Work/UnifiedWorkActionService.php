<?php

namespace App\Services\Operations\Work;

use App\Models\CalendarItem;
use App\Models\Task;
use App\Models\User;
use App\Support\Operations\UnifiedWorkReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UnifiedWorkActionService
{
    public function __construct(
        private readonly TaskWorkAdapter $tasks,
        private readonly CalendarTaskWorkAdapter $calendarTasks,
        private readonly UnifiedWorkService $work,
    ) {}

    /**
     * @return array{ref: UnifiedWorkReference, model: Task|CalendarItem, adapter: 'task'|'calendar'}
     */
    public function resolve(User $actor, string $ref): array
    {
        $parsed = UnifiedWorkReference::parse($ref);

        if ($parsed->isTask()) {
            $task = Task::query()->with(['assignee', 'project', 'creator'])->find($parsed->sourceId());
            if ($task === null) {
                throw (new ModelNotFoundException)->setModel(Task::class, [$parsed->sourceId()]);
            }

            if (! $this->work->actorCanViewTask($actor, $task)) {
                throw new AuthorizationException;
            }

            return [
                'ref' => $parsed,
                'model' => $task,
                'adapter' => 'task',
            ];
        }

        $item = CalendarItem::query()
            ->with(['assignees', 'creator', 'blockedBy'])
            ->find($parsed->sourceId());

        if ($item === null) {
            throw (new ModelNotFoundException)->setModel(CalendarItem::class, [$parsed->sourceId()]);
        }

        if ($parsed->occurrenceDate() !== null) {
            $item->resolved_occurrence_at = $parsed->occurrenceDate()->toIso8601String();
        }

        if (! Gate::forUser($actor)->allows('view', $item)) {
            throw new AuthorizationException;
        }

        return [
            'ref' => $parsed,
            'model' => $item,
            'adapter' => 'calendar',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(User $actor, string $ref): array
    {
        $resolved = $this->resolve($actor, $ref);

        if ($resolved['adapter'] === 'task') {
            /** @var Task $task */
            $task = $resolved['model'];

            return $this->tasks->toItem($task, $actor);
        }

        /** @var CalendarItem $item */
        $item = $resolved['model'];
        /** @var UnifiedWorkReference $parsed */
        $parsed = $resolved['ref'];

        return $this->calendarTasks->toItem($item, $actor, $parsed->occurrenceDate());
    }

    /**
     * @return array<string, mixed>
     */
    public function complete(User $actor, string $ref): array
    {
        $resolved = $this->resolve($actor, $ref);

        if ($resolved['adapter'] === 'task') {
            /** @var Task $task */
            $task = $resolved['model'];

            return $this->tasks->complete($actor, $task);
        }

        /** @var CalendarItem $item */
        $item = $resolved['model'];

        return $this->calendarTasks->complete($actor, $item);
    }

    /**
     * @param  list<int>  $assigneeIds
     * @return array<string, mixed>
     */
    public function assign(User $actor, string $ref, array $assigneeIds): array
    {
        $resolved = $this->resolve($actor, $ref);

        if ($resolved['adapter'] === 'task') {
            /** @var Task $task */
            $task = $resolved['model'];

            return $this->tasks->assign($actor, $task, $assigneeIds);
        }

        /** @var CalendarItem $item */
        $item = $resolved['model'];
        /** @var UnifiedWorkReference $parsed */
        $parsed = $resolved['ref'];

        return $this->calendarTasks->assign(
            $actor,
            $item,
            $assigneeIds,
            $this->occurrenceScope($item, $parsed),
            $parsed->occurrenceDate()?->toDateString(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function setPriority(User $actor, string $ref, string $priority): array
    {
        $resolved = $this->resolve($actor, $ref);

        if ($resolved['adapter'] === 'task') {
            /** @var Task $task */
            $task = $resolved['model'];

            return $this->tasks->setPriority($actor, $task, $priority);
        }

        /** @var CalendarItem $item */
        $item = $resolved['model'];
        /** @var UnifiedWorkReference $parsed */
        $parsed = $resolved['ref'];

        return $this->calendarTasks->setPriority(
            $actor,
            $item,
            $priority,
            $this->occurrenceScope($item, $parsed),
            $parsed->occurrenceDate()?->toDateString(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function setStatus(User $actor, string $ref, string $status): array
    {
        $resolved = $this->resolve($actor, $ref);

        if ($resolved['adapter'] === 'task') {
            /** @var Task $task */
            $task = $resolved['model'];

            return $this->tasks->setStatus($actor, $task, $status);
        }

        /** @var CalendarItem $item */
        $item = $resolved['model'];
        /** @var UnifiedWorkReference $parsed */
        $parsed = $resolved['ref'];

        return $this->calendarTasks->setStatus(
            $actor,
            $item,
            $status,
            $this->occurrenceScope($item, $parsed),
            $parsed->occurrenceDate()?->toDateString(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function reschedule(User $actor, string $ref, ?string $dueAt, ?string $startsAt): array
    {
        $resolved = $this->resolve($actor, $ref);

        if ($resolved['adapter'] === 'task') {
            /** @var Task $task */
            $task = $resolved['model'];

            return $this->tasks->reschedule($actor, $task, $dueAt ?? $startsAt);
        }

        /** @var CalendarItem $item */
        $item = $resolved['model'];
        /** @var UnifiedWorkReference $parsed */
        $parsed = $resolved['ref'];

        $when = $startsAt ?? $dueAt;
        if ($when === null || $when === '') {
            throw ValidationException::withMessages([
                'starts_at' => ['starts_at or due_at is required.'],
            ]);
        }

        return $this->calendarTasks->reschedule(
            $actor,
            $item,
            $when,
            $this->occurrenceScope($item, $parsed),
            $parsed->occurrenceDate()?->toDateString(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function start(User $actor, string $ref): array
    {
        $resolved = $this->resolve($actor, $ref);

        if ($resolved['adapter'] === 'task') {
            /** @var Task $task */
            $task = $resolved['model'];

            return $this->tasks->start($actor, $task);
        }

        /** @var CalendarItem $item */
        $item = $resolved['model'];
        /** @var UnifiedWorkReference $parsed */
        $parsed = $resolved['ref'];

        return $this->calendarTasks->start(
            $actor,
            $item,
            $this->occurrenceScope($item, $parsed),
            $parsed->occurrenceDate()?->toDateString(),
        );
    }

    private function occurrenceScope(CalendarItem $item, UnifiedWorkReference $ref): ?string
    {
        if ($ref->occurrenceDate() !== null && $item->isRecurringMaster()) {
            return 'this';
        }

        return null;
    }
}
