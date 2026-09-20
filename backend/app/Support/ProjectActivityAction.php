<?php

namespace App\Support;

final class ProjectActivityAction
{
    public const PROJECT_CREATED = 'project.created';

    public const PROJECT_UPDATED = 'project.updated';

    public const PROJECT_STATUS_CHANGED = 'project.status_changed';

    public const PROJECT_ACCOUNT_MANAGER_ASSIGNED = 'project.account_manager_assigned';

    public const PROJECT_MEMBER_ADDED = 'project.member_added';

    public const PROJECT_MEMBER_REMOVED = 'project.member_removed';

    public const TASK_CREATED = 'task.created';

    public const TASK_UPDATED = 'task.updated';

    public const TASK_STATUS_CHANGED = 'task.status_changed';

    public const TASK_COMPLETED = 'task.completed';

    public const TASK_ASSIGNED = 'task.assigned';

    public const MILESTONE_CREATED = 'milestone.created';

    public const MILESTONE_UPDATED = 'milestone.updated';

    public const MILESTONE_COMPLETED = 'milestone.completed';

    public const FILE_UPLOADED = 'file.uploaded';

    public const FILE_UPDATED = 'file.updated';

    public const FILE_CLIENT_VISIBILITY_CHANGED = 'file.client_visibility_changed';

    public const CALENDAR_ITEM_CREATED = 'calendar_item.created';

    public const CALENDAR_ITEM_UPDATED = 'calendar_item.updated';

    public const CALENDAR_ITEM_COMPLETED = 'calendar_item.completed';

    public const CUSTOMER_PROJECT_ACTION = 'customer.project_action';

    public const CUSTOMER_FILE_UPLOADED = 'customer.file_uploaded';

    public const CUSTOMER_MILESTONE_ACTION = 'customer.milestone_action';

    /**
     * Actions customers may see (still subject to is_client_visible on the row).
     *
     * @return list<string>
     */
    public static function customerSafeActions(): array
    {
        return [
            self::PROJECT_CREATED,
            self::PROJECT_STATUS_CHANGED,
            self::MILESTONE_CREATED,
            self::MILESTONE_COMPLETED,
            self::FILE_UPLOADED,
            self::FILE_CLIENT_VISIBILITY_CHANGED,
            self::CUSTOMER_FILE_UPLOADED,
            self::CUSTOMER_MILESTONE_ACTION,
            self::CUSTOMER_PROJECT_ACTION,
            self::CALENDAR_ITEM_COMPLETED,
        ];
    }
}
