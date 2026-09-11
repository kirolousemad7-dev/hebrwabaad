<?php

namespace App\Enums;

enum ApprovalRequestType: string
{
    case CalendarTaskCompletion = 'calendar_task_completion';
    case ProjectMilestone = 'project_milestone';
    case ContentPublish = 'content_publish';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
