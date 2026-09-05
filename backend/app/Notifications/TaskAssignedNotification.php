<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    public function __construct(private readonly Task $task) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_assigned',
            'title' => 'تم إسناد مهمة جديدة إليك',
            'message' => 'تم إسناد مهمة "'.$this->task->title.'" إليك.',
            'href' => '/workspace/tasks/'.$this->task->id,
            'task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
        ];
    }
}
