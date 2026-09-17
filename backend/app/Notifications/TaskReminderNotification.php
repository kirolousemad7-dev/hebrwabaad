<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\TaskReminder;
use App\Services\Notifications\NotificationChannelManager;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification
{
    public function __construct(
        private readonly Task $task,
        private readonly TaskReminder $reminder,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return app(NotificationChannelManager::class)->enabled();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_reminder',
            'title' => 'تذكير بمهمة',
            'message' => 'تذكير: المهمة "'.$this->task->title.'" قريبة من موعدها.',
            'href' => '/workspace/tasks/'.$this->task->id,
            'task_id' => $this->task->id,
            'reminder_id' => $this->reminder->id,
            'remind_at' => $this->reminder->remind_at?->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تذكير مهمة: '.$this->task->title)
            ->line('تذكير بالمهمة: '.$this->task->title)
            ->line('الموعد: '.($this->task->due_at?->toDateTimeString() ?? $this->task->deadline?->toDateString() ?? '—'))
            ->action('فتح المهمة', url('/workspace/tasks/'.$this->task->id));
    }
}
