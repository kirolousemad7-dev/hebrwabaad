<?php

namespace App\Support;

use App\Enums\UserRole;

final class EmployeeWorkspace
{
    /**
     * @return array<int, string>
     */
    public static function capabilitiesFor(UserRole $role): array
    {
        if (! $role->usesEmployeeWorkspace()) {
            return [];
        }

        return match ($role) {
            UserRole::WebDeveloper => [
                'tasks.view',
                'projects.view',
                'deadlines.view',
                'requirements.view',
                'revisions.view',
                'files.view',
                'messages.view',
            ],
            UserRole::GraphicDesigner => [
                'projects.view',
                'briefs.view',
                'tasks.view',
                'deadlines.view',
                'files.view',
                'revisions.view',
                'messages.view',
            ],
            UserRole::VideoEditor => [
                'projects.view',
                'tasks.view',
                'files.view',
                'revisions.view',
                'deadlines.view',
                'messages.view',
            ],
            UserRole::MarketingSpecialist => [
                'campaigns.view',
                'tasks.view',
                'projects.view',
                'deadlines.view',
                'reports.view',
                'requests.view',
            ],
            UserRole::EventSpecialist => [
                'events.view',
                'tasks.view',
                'projects.view',
                'deadlines.view',
                'files.view',
                'requests.view',
            ],
            UserRole::PrintingSpecialist => [
                'printing.queue.view',
                'tasks.view',
                'projects.view',
                'deadlines.view',
                'files.view',
                'messages.view',
            ],
            UserRole::MediaBuyer => [
                'campaigns.view',
                'tasks.view',
                'projects.view',
                'deadlines.view',
                'budgets.view',
                'reports.view',
            ],
            UserRole::AccountManager => [
                'tasks.view',
                'tasks.manage',
                'deadlines.view',
                'clients.view',
                'requests.view',
                'projects.view',
                'files.view',
            ],
            UserRole::Hr => [
                'employees.directory.view',
                'tasks.view',
                'attendance.view',
            ],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function widgetIdsFor(UserRole $role): array
    {
        if (! $role->usesEmployeeWorkspace()) {
            return [];
        }

        $ids = ['overview'];
        $capabilityWidgets = [
            'projects.view' => ['projects'],
            'tasks.view' => ['tasks'],
            'tasks.manage' => ['task-progress'],
            'files.view' => ['files'],
            'messages.view' => ['messages'],
            'revisions.view' => ['revisions'],
            'requirements.view' => ['requirements'],
            'deadlines.view' => ['deadlines'],
            'briefs.view' => ['design-briefs'],
            'campaigns.view' => ['campaigns'],
            'events.view' => ['events'],
            'printing.queue.view' => ['printing-queue'],
            'budgets.view' => ['budgets'],
            'reports.view' => ['reports'],
            'clients.view' => ['clients'],
            'requests.view' => ['client-requests'],
            'employees.directory.view' => ['employees', 'active-employees', 'inactive-employees'],
            'attendance.view' => ['attendance'],
        ];

        foreach (self::capabilitiesFor($role) as $capability) {
            foreach ($capabilityWidgets[$capability] ?? [] as $widgetId) {
                $ids[] = $widgetId;
            }
        }

        if ($role === UserRole::Hr) {
            $ids[] = 'employee-requests';
        }

        if ($role === UserRole::MarketingSpecialist) {
            $ids[] = 'content';
        }

        if ($role === UserRole::MediaBuyer) {
            $ids[] = 'performance';
        }

        return self::sortWidgetIds($role, array_values(array_unique($ids)));
    }

    /**
     * @return array<string, array{available: bool, status: string}>
     */
    public static function domainsFor(UserRole $role): array
    {
        if (! $role->usesEmployeeWorkspace()) {
            return [];
        }

        $keys = match ($role) {
            UserRole::WebDeveloper => [
                'projects', 'tasks', 'requirements', 'files', 'deadlines', 'revisions', 'messages',
            ],
            UserRole::GraphicDesigner => [
                'projects', 'design-briefs', 'tasks', 'deadlines', 'files', 'revisions', 'messages',
            ],
            UserRole::VideoEditor => [
                'tasks', 'projects', 'files', 'revisions', 'deadlines', 'messages',
            ],
            UserRole::MarketingSpecialist => [
                'campaigns', 'tasks', 'projects', 'content', 'deadlines', 'client-requests', 'reports',
            ],
            UserRole::EventSpecialist => ['tasks', 'projects', 'events', 'client-requests', 'deadlines', 'files'],
            UserRole::PrintingSpecialist => ['printing-queue', 'tasks', 'projects', 'deadlines', 'files', 'messages'],
            UserRole::MediaBuyer => [
                'tasks', 'projects', 'campaigns', 'budgets', 'performance', 'deadlines', 'reports',
            ],
            UserRole::AccountManager => [
                'clients', 'client-requests', 'tasks', 'task-progress', 'deadlines', 'projects', 'files',
            ],
            UserRole::Hr => [
                'employees', 'active-employees', 'inactive-employees', 'tasks', 'attendance', 'employee-requests',
            ],
            default => [],
        };

        $live = [];
        if ($role === UserRole::PrintingSpecialist) {
            $live[] = 'printing-queue';
        }
        if (in_array('tasks.view', self::capabilitiesFor($role), true)) {
            $live[] = 'tasks';
        }
        if (in_array('deadlines.view', self::capabilitiesFor($role), true)) {
            $live[] = 'deadlines';
        }
        if (in_array('tasks.manage', self::capabilitiesFor($role), true)) {
            $live[] = 'task-progress';
        }
        if (in_array('employees.directory.view', self::capabilitiesFor($role), true)) {
            $live[] = 'employees';
            $live[] = 'active-employees';
            $live[] = 'inactive-employees';
        }
        if (in_array('projects.view', self::capabilitiesFor($role), true)) {
            $live[] = 'projects';
        }
        if (in_array('files.view', self::capabilitiesFor($role), true)) {
            $live[] = 'files';
        }

        $domains = [];
        foreach ($keys as $key) {
            $available = in_array($key, $live, true);
            $domains[$key] = [
                'available' => $available,
                'status' => $available ? 'ready' : 'unavailable',
            ];
        }

        return $domains;
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<int, string>
     */
    private static function sortWidgetIds(UserRole $role, array $ids): array
    {
        $order = match ($role) {
            UserRole::WebDeveloper => [
                'overview' => 10, 'tasks' => 12, 'projects' => 14, 'deadlines' => 16,
                'requirements' => 40, 'revisions' => 45, 'files' => 50, 'messages' => 60,
            ],
            UserRole::GraphicDesigner => [
                'overview' => 10, 'projects' => 12, 'design-briefs' => 14, 'tasks' => 16,
                'deadlines' => 18, 'files' => 40, 'revisions' => 45, 'messages' => 50,
            ],
            UserRole::VideoEditor => [
                'overview' => 10, 'tasks' => 12, 'projects' => 14, 'files' => 16,
                'revisions' => 18, 'deadlines' => 20, 'messages' => 50,
            ],
            UserRole::MediaBuyer => [
                'overview' => 10, 'tasks' => 12, 'projects' => 13, 'campaigns' => 14, 'budgets' => 16,
                'performance' => 18, 'deadlines' => 20, 'reports' => 50,
            ],
            UserRole::AccountManager => [
                'overview' => 10, 'projects' => 11, 'tasks' => 12, 'task-progress' => 14, 'deadlines' => 16,
                'files' => 38, 'clients' => 40, 'client-requests' => 45,
            ],
            UserRole::Hr => [
                'overview' => 10, 'employees' => 12, 'active-employees' => 13, 'inactive-employees' => 14,
                'tasks' => 16, 'employee-requests' => 40, 'attendance' => 50,
            ],
            UserRole::MarketingSpecialist => [
                'overview' => 10, 'campaigns' => 12, 'tasks' => 14, 'projects' => 15, 'content' => 16,
                'deadlines' => 18, 'client-requests' => 20, 'reports' => 22,
            ],
            UserRole::EventSpecialist => [
                'overview' => 10, 'tasks' => 12, 'projects' => 13, 'events' => 14, 'client-requests' => 16,
                'deadlines' => 18, 'files' => 40,
            ],
            UserRole::PrintingSpecialist => [
                'overview' => 10, 'printing-queue' => 12, 'tasks' => 14, 'projects' => 15, 'deadlines' => 16,
                'files' => 40, 'messages' => 50,
            ],
            default => [],
        };

        if ($order === []) {
            return $ids;
        }

        usort($ids, function (string $left, string $right) use ($order): int {
            return ($order[$left] ?? 100) <=> ($order[$right] ?? 100);
        });

        return array_values($ids);
    }
}
