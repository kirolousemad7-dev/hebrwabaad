<?php

namespace App\Services\GoogleCalendar;

use App\Contracts\SmsSender;
use App\Enums\CalendarReminderOffset;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Task;
use App\Models\TaskReminder;
use App\Models\User;
use App\Notifications\TaskReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TaskReminderService
{
    public function __construct() {}

    /**
     * @param  list<string|int>  $offsets  CalendarReminderOffset values or custom minute ints
     * @return Collection<int, TaskReminder>
     */
    public function syncReminders(Task $task, array $offsets): Collection
    {
        $anchor = $this->anchorAt($task);
        TaskReminder::query()->where('task_id', $task->id)->whereNull('sent_at')->delete();

        $created = collect();
        if ($anchor === null) {
            return $created;
        }

        foreach ($offsets as $offset) {
            $minutes = null;
            $offsetValue = null;

            if (is_int($offset) || (is_string($offset) && ctype_digit($offset))) {
                $minutes = (int) $offset;
                $offsetValue = null;
            } else {
                $enum = CalendarReminderOffset::tryFrom((string) $offset);
                if ($enum === null) {
                    continue;
                }
                $minutes = $enum->minutesBefore();
                $offsetValue = $enum->value;
            }

            $remindAt = $anchor->copy()->subMinutes($minutes);
            if ($remindAt->isPast() && $minutes > 0) {
                continue;
            }

            $created->push(TaskReminder::query()->create([
                'task_id' => $task->id,
                'offset' => $offsetValue,
                'custom_minutes' => $offsetValue === null ? $minutes : null,
                'remind_at' => $remindAt,
            ]));
        }

        return $created;
    }

    public function ensureDefaultReminders(Task $task): void
    {
        if (TaskReminder::query()->where('task_id', $task->id)->exists()) {
            return;
        }

        $this->syncReminders($task, [
            CalendarReminderOffset::Minutes15->value,
            CalendarReminderOffset::Minutes30->value,
            CalendarReminderOffset::Hour1->value,
            CalendarReminderOffset::Day1->value,
        ]);
    }

    public function dispatchDue(): int
    {
        $sent = 0;
        $dueIds = TaskReminder::query()
            ->whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->orderBy('id')
            ->limit(200)
            ->pluck('id');

        foreach ($dueIds as $reminderId) {
            $claimed = DB::transaction(function () use ($reminderId) {
                $reminder = TaskReminder::query()
                    ->whereKey($reminderId)
                    ->whereNull('sent_at')
                    ->lockForUpdate()
                    ->first();

                if ($reminder === null) {
                    return null;
                }

                $updated = TaskReminder::query()
                    ->whereKey($reminder->id)
                    ->whereNull('sent_at')
                    ->update(['sent_at' => now()]);

                if ($updated !== 1) {
                    return null;
                }

                return $reminder->fresh(['task.assignee', 'task.creator', 'task.project', 'task.supplier.user']);
            });

            if ($claimed === null || $claimed->task === null) {
                continue;
            }

            $task = $claimed->task;
            $status = $task->status instanceof TaskStatus
                ? $task->status
                : TaskStatus::tryFrom((string) $task->status);

            if ($status === TaskStatus::Completed) {
                continue;
            }

            $recipients = $this->recipientsFor($task);
            if ($recipients->isEmpty()) {
                continue;
            }

            Notification::send($recipients, new TaskReminderNotification($task, $claimed));

            if (config('sms.default') === 'http') {
                $supplierPhone = $task->supplier?->phone;
                if (is_string($supplierPhone) && $supplierPhone !== '') {
                    try {
                        app(SmsSender::class)->send($supplierPhone, 'تذكير مهمة: '.$task->title);
                    } catch (\Throwable) {
                        // SMS is best-effort when configured.
                    }
                }
            }

            $sent++;
        }

        return $sent;
    }

    /**
     * @return Collection<int, User>
     */
    public function recipientsFor(Task $task): Collection
    {
        $users = collect();

        if ($task->assignee) {
            $users->push($task->assignee);
        }

        if ($task->project?->account_manager_id) {
            $manager = User::query()->find($task->project->account_manager_id);
            if ($manager) {
                $users->push($manager);
            }
        }

        if ($task->creator) {
            $users->push($task->creator);
        }

        $supplierUser = $task->supplier?->user;
        if ($supplierUser && $supplierUser->role === UserRole::Supplier) {
            $users->push($supplierUser);
        }

        return $users->unique('id')->values();
    }

    private function anchorAt(Task $task): ?Carbon
    {
        if ($task->due_at !== null) {
            return Carbon::parse($task->due_at);
        }
        if ($task->start_at !== null) {
            return Carbon::parse($task->start_at);
        }
        if ($task->deadline !== null) {
            return Carbon::parse($task->deadline->toDateString())->setTime(9, 0);
        }

        return null;
    }
}
