<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use Illuminate\Notifications\DatabaseNotification;

class NotificationCategorizer
{
    /**
     * Map a stored notification type (or payload) to an inbox category.
     *
     * Categories cover types actually emitted in this codebase.
     */
    public function categorize(DatabaseNotification|array|string $notification): NotificationCategory
    {
        $type = $this->resolveType($notification);

        if ($type === '') {
            return NotificationCategory::Alerts;
        }

        if ($this->isApprovalType($type)) {
            return NotificationCategory::Approvals;
        }

        if ($this->isCrmType($type)) {
            return NotificationCategory::Crm;
        }

        if ($this->isPrintingType($type)) {
            return NotificationCategory::Printing;
        }

        if ($this->isProjectType($type)) {
            return NotificationCategory::Projects;
        }

        if ($this->isTaskType($type)) {
            return NotificationCategory::Tasks;
        }

        if ($this->isCalendarType($type)) {
            return NotificationCategory::Calendar;
        }

        if ($this->isAutomationType($type)) {
            return NotificationCategory::Automation;
        }

        return NotificationCategory::Alerts;
    }

    public function categorizeValue(DatabaseNotification|array|string $notification): string
    {
        return $this->categorize($notification)->value;
    }

    /**
     * Approvals and other critical alerts must stay as individual rows.
     */
    public function mustStayIndividual(DatabaseNotification|array|string $notification): bool
    {
        $category = $this->categorize($notification);
        $type = $this->resolveType($notification);

        if ($category === NotificationCategory::Approvals) {
            return true;
        }

        return in_array($type, [
            'crm_quotation_approval_needed',
            'project_escalation',
            'printing_escalation',
            'escalation_calendar_manager',
            'escalation_workspace_manager',
            'owner_instapay_verification',
        ], true);
    }

    private function isApprovalType(string $type): bool
    {
        return $type === 'crm_quotation_approval_needed'
            || str_starts_with($type, 'approval_');
    }

    private function isCrmType(string $type): bool
    {
        return in_array($type, [
            'crm_lead_assigned',
            'crm_follow_up_overdue',
            'crm_mention',
            'crm_deal_won',
            'crm_stale_lead',
        ], true) || (str_starts_with($type, 'crm_') && $type !== 'crm_quotation_approval_needed');
    }

    private function isPrintingType(string $type): bool
    {
        return in_array($type, [
            'printing_escalation',
            'printing_overdue',
            'printing_request_submitted',
            'printing_quote_ready',
        ], true) || str_starts_with($type, 'printing_');
    }

    private function isProjectType(string $type): bool
    {
        return in_array($type, [
            'project_escalation',
            'project_health',
        ], true) || str_starts_with($type, 'project_');
    }

    private function isTaskType(string $type): bool
    {
        return in_array($type, [
            'task_assigned',
            'task_overdue',
            'escalation_workspace_task',
            'escalation_workspace_manager',
        ], true) || str_starts_with($type, 'task_');
    }

    private function isCalendarType(string $type): bool
    {
        return in_array($type, [
            'calendar_assigned',
            'calendar_updated',
            'calendar_completed',
            'calendar_cancelled',
            'calendar_reminder',
            'calendar_mention',
            'calendar_daily_digest',
            'calendar_overdue',
            'escalation_calendar',
            'escalation_calendar_manager',
        ], true) || str_starts_with($type, 'calendar_');
    }

    private function isAutomationType(string $type): bool
    {
        return $type === 'automation_notification'
            || str_starts_with($type, 'automation_');
    }

    private function resolveType(DatabaseNotification|array|string $notification): string
    {
        if (is_string($notification)) {
            return $notification;
        }

        if ($notification instanceof DatabaseNotification) {
            $payload = is_array($notification->data) ? $notification->data : [];

            return is_string($payload['type'] ?? null) ? $payload['type'] : '';
        }

        return is_string($notification['type'] ?? null) ? $notification['type'] : '';
    }
}
