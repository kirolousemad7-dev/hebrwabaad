<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use App\Services\Operations\Work\UnifiedWorkService;
use Illuminate\Validation\ValidationException;

class TeamDashboardService
{
    public function __construct(
        private readonly UnifiedWorkService $unifiedWork,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $actor, ?int $departmentId = null): array
    {
        if (! $this->canAccess($actor)) {
            throw ValidationException::withMessages([
                'team' => ['You cannot view the team dashboard.'],
            ]);
        }

        $resolvedDepartmentId = $this->resolveDepartmentId($actor, $departmentId);
        $employeeIds = $this->employeeIds($actor, $resolvedDepartmentId);

        $baseFilters = [
            'scope' => 'team',
            'department_id' => $resolvedDepartmentId,
        ];

        $today = $this->unifiedWork->list($actor, array_merge($baseFilters, [
            'bucket' => 'today',
            'per_page' => 50,
            'page' => 1,
        ]));

        $overdue = $this->unifiedWork->list($actor, array_merge($baseFilters, [
            'bucket' => 'overdue',
            'per_page' => 50,
            'page' => 1,
            'sort' => 'overdue_first',
        ]));

        $upcoming = $this->unifiedWork->list($actor, array_merge($baseFilters, [
            'bucket' => 'upcoming',
            'per_page' => 50,
            'page' => 1,
            'sort' => 'due_asc',
        ]));

        $from = now()->startOfDay();
        $to = now()->endOfDay()->addDays(7);
        $workload = $this->unifiedWork->workload($actor, $from, $to);

        if ($resolvedDepartmentId !== null || $employeeIds !== null) {
            $allowed = $employeeIds ?? [];
            $workload['by_assignee'] = array_values(array_filter(
                $workload['by_assignee'] ?? [],
                function (array $row) use ($allowed, $resolvedDepartmentId): bool {
                    if ($allowed === [] && $resolvedDepartmentId === null) {
                        return true;
                    }

                    return in_array((int) ($row['user_id'] ?? 0), $allowed, true);
                },
            ));
        }

        return [
            'department_id' => $resolvedDepartmentId,
            'employees' => $employeeIds === null ? null : array_values($employeeIds),
            'today' => [
                'count' => $today['meta']['total'] ?? count($today['items']),
                'items' => array_slice($today['items'], 0, 20),
            ],
            'overdue' => [
                'count' => $overdue['meta']['total'] ?? count($overdue['items']),
                'items' => array_slice($overdue['items'], 0, 20),
            ],
            'upcoming' => [
                'count' => $upcoming['meta']['total'] ?? count($upcoming['items']),
                'items' => array_slice($upcoming['items'], 0, 20),
            ],
            'workload' => $workload,
        ];
    }

    public function canAccess(User $actor): bool
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->is_active) {
            return false;
        }

        if ($actor->role->canManageTeamWork()) {
            return true;
        }

        return Department::query()->where('manager_id', $actor->id)->exists();
    }

    private function resolveDepartmentId(User $actor, ?int $departmentId): ?int
    {
        $managed = Department::query()->where('manager_id', $actor->id)->pluck('id')->all();

        $isLeadership = $actor->role instanceof UserRole && in_array($actor->role, [
            UserRole::Owner,
            UserRole::AdminManager,
        ], true);

        if ($isLeadership) {
            return $departmentId;
        }

        if ($managed !== []) {
            if ($departmentId !== null && ! in_array($departmentId, $managed, true)) {
                throw ValidationException::withMessages([
                    'department_id' => ['You can only view your managed department.'],
                ]);
            }

            return $departmentId ?? (int) $managed[0];
        }

        if ($actor->role instanceof UserRole && $actor->role->canManageTeamWork()) {
            return $departmentId ?? ($actor->department_id ? (int) $actor->department_id : null);
        }

        return null;
    }

    /**
     * @return list<int>|null
     */
    private function employeeIds(User $actor, ?int $departmentId): ?array
    {
        if ($departmentId === null) {
            $isLeadership = $actor->role instanceof UserRole && in_array($actor->role, [
                UserRole::Owner,
                UserRole::AdminManager,
            ], true);

            return $isLeadership ? null : [];
        }

        return User::query()
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
