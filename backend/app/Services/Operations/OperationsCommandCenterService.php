<?php

namespace App\Services\Operations;

use App\Enums\CalendarItemType;
use App\Enums\UserRole;
use App\Enums\WorkflowRunStatus;
use App\Models\CalendarItem;
use App\Models\Project;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WorkflowAutomationRun;
use App\Services\Calendar\CalendarService;
use App\Services\Operations\Work\UnifiedWorkService;
use App\Services\Printing\PrintingRevenueService;
use Illuminate\Database\Eloquent\Builder;

class OperationsCommandCenterService
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly OperationalAttentionService $attention,
        private readonly ProjectHealthService $health,
        private readonly UnifiedWorkService $unifiedWork,
        private readonly SlaEvaluationService $sla,
        private readonly PrintingOperationsService $printing,
        private readonly PrintingRevenueService $printingRevenue,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $actor): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $scope = ($actor->role instanceof UserRole && $actor->role->canViewTeamCalendar()) ? 'team' : 'mine';

        $workSummary = $this->unifiedWork->summary($actor);

        $todayQuery = CalendarItem::query()->whereBetween('starts_at', [$todayStart, $todayEnd]);
        $this->applyScope($todayQuery, $actor, $scope);

        $todayMeetings = (clone $todayQuery)->whereIn('type', [
            CalendarItemType::Meeting->value,
            CalendarItemType::Appointment->value,
            CalendarItemType::Call->value,
        ])->count();

        $projectsNeedAttention = 0;
        $projectHealth = [];
        $projects = Project::query()
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->orderBy('deadline')
            ->limit(30)
            ->get();

        foreach ($projects as $project) {
            $eval = $this->health->evaluate($project);
            if (in_array($eval['status'], ['needs_attention', 'overdue'], true)) {
                $projectsNeedAttention++;
                $projectHealth[] = [
                    'id' => $project->id,
                    'title' => $project->title,
                    'health' => $eval,
                    'deadline' => $project->deadline?->toDateString(),
                ];
            }
        }

        usort($projectHealth, function (array $a, array $b): int {
            $rank = ['overdue' => 0, 'needs_attention' => 1, 'on_track' => 2];

            return ($rank[$a['health']['status']] ?? 9) <=> ($rank[$b['health']['status']] ?? 9);
        });

        $timeline = CalendarItem::query()
            ->with(['assignees:id,name', 'creator:id,name'])
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->orderBy('starts_at');
        $this->applyScope($timeline, $actor, $scope);

        $workload = $this->unifiedWork->workload($actor, $todayStart->copy(), $todayEnd->copy()->addDays(7));
        $byAssignee = $workload['by_assignee'] ?? [];
        usort($byAssignee, fn (array $a, array $b): int => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));

        $attention = $this->attention->for($actor);
        $upcomingDeliveries = count(array_filter(
            $attention,
            fn (array $row): bool => ($row['type'] ?? '') === 'upcoming_delivery',
        ));

        $printingOverdue = 0;
        try {
            $printingSummary = $this->printing->summary($actor);
            $printingOverdue = (int) ($printingSummary['summary']['overdue'] ?? 0);
        } catch (\Throwable) {
            $printingOverdue = 0;
        }
        $slaBreached = $this->sla->breachedCount();

        $payload = [
            'summary' => [
                'today_tasks' => $workSummary['today_tasks'],
                'overdue' => $workSummary['overdue'],
                'overdue_unified_work' => $workSummary['overdue'],
                'today_meetings' => $todayMeetings,
                'upcoming_deliveries' => $upcomingDeliveries,
                'projects_need_attention' => $projectsNeedAttention,
                'open_work' => $workSummary['open'],
                'in_progress_work' => $workSummary['in_progress'],
                'sla_breached' => $slaBreached,
                'printing_overdue' => $printingOverdue,
            ],
            'attention' => $attention,
            'today_timeline' => $timeline->limit(40)->get()->map(
                fn (CalendarItem $item) => $this->calendar->serialize($item)
            )->all(),
            'workload_snapshot' => array_slice($byAssignee, 0, 8),
            'project_health' => array_slice($projectHealth, 0, 8),
            'focus' => $this->unifiedWork->focus($actor, 5),
            'operational' => [
                'printing_lifecycle_issues' => $printingOverdue,
                'sla_breaches' => $slaBreached,
                'overdue_unified_work' => $workSummary['overdue'],
            ],
        ];

        if ($this->canViewSystemHealth($actor)) {
            $since = now()->subDay();
            $failedAutomations = WorkflowAutomationRun::query()
                ->where('status', WorkflowRunStatus::Failed->value)
                ->where('created_at', '>=', $since)
                ->count();
            $failedWebhooks = WebhookDelivery::query()
                ->where('status', WebhookDelivery::STATUS_FAILED)
                ->where(function ($query) use ($since): void {
                    $query->where('failed_at', '>=', $since)
                        ->orWhere(function ($inner) use ($since): void {
                            $inner->whereNull('failed_at')->where('created_at', '>=', $since);
                        });
                })
                ->count();

            $payload['system_health'] = [
                'failed_automations_24h' => $failedAutomations,
                'failed_webhooks_24h' => $failedWebhooks,
            ];
            $payload['summary']['failed_automations_24h'] = $failedAutomations;
            $payload['summary']['failed_webhooks_24h'] = $failedWebhooks;
        }

        $revenue = $this->printingRevenue->commandCenterSection($actor);
        if ($revenue !== null) {
            $payload['revenue'] = $revenue;
            $payload['summary']['quotes_awaiting_customer'] = $revenue['quotes_awaiting_customer'];
            $payload['summary']['payments_required'] = $revenue['payments_required'];
            $payload['summary']['awaiting_payment'] = $revenue['awaiting_payment'];
            $payload['summary']['pending_review'] = $revenue['pending_review'] ?? 0;
            $payload['summary']['pending_processing'] = $revenue['pending_processing'] ?? 0;
            $payload['summary']['failed_payments'] = $revenue['failed_payments'] ?? 0;
            $payload['summary']['refunds_confirmed_count'] = $revenue['refunds_confirmed_count'] ?? 0;
            $payload['summary']['reconciliation_problems'] = $revenue['reconciliation_problems'] ?? 0;
            $payload['summary']['payments_processing'] = $revenue['payments_processing'];
            $payload['summary']['reconcile_attention'] = $revenue['reconcile_attention'];
            $payload['summary']['execution_eligible'] = $revenue['execution_eligible'];
            $payload['summary']['ready_for_delivery'] = $revenue['ready_for_delivery'];
        }

        return $payload;
    }

    private function canViewSystemHealth(User $actor): bool
    {
        return $actor->role instanceof UserRole
            && in_array($actor->role, [UserRole::Owner, UserRole::AdminManager], true);
    }

    /**
     * @param  Builder<CalendarItem>  $query
     */
    private function applyScope($query, User $actor, string $scope): void
    {
        if ($scope === 'mine') {
            $query->where(function ($builder) use ($actor): void {
                $builder->where('created_by', $actor->id)
                    ->orWhereHas('assignees', fn ($q) => $q->where('users.id', $actor->id));
            });
        }
    }
}
