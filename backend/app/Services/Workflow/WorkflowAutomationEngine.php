<?php

namespace App\Services\Workflow;

use App\Enums\CalendarItemPriority;
use App\Enums\CalendarItemType;
use App\Enums\CalendarSource;
use App\Enums\CalendarVisibility;
use App\Enums\UserRole;
use App\Enums\WorkflowRunStatus;
use App\Models\CalendarItem;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Models\WorkflowAutomation;
use App\Models\WorkflowAutomationRun;
use App\Notifications\CalendarNotification;
use App\Services\Calendar\CalendarService;
use App\Services\Operations\OperationalNotifier;
use App\Services\Operations\WebhookDispatcher;
use App\Support\Workflow\AutomationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class WorkflowAutomationEngine
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly OperationalNotifier $notifier,
        private readonly WebhookDispatcher $webhooks,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    public function dispatch(string $trigger, array $context, ?string $idempotencySuffix = null): array
    {
        $context = $this->mergeAutomationContext($context);

        $automations = WorkflowAutomation::query()
            ->where('trigger', $trigger)
            ->where('is_active', true)
            ->where('is_template', false)
            ->orderBy('id')
            ->get();

        $results = [];
        foreach ($automations as $automation) {
            $results[] = $this->runAutomation($automation, $trigger, $context, $idempotencySuffix);
        }

        try {
            $webhookContext = $context;
            if ($idempotencySuffix !== null) {
                $webhookContext['idempotency_suffix'] = $idempotencySuffix;
            }
            $this->webhooks->queue($trigger, $webhookContext);
        } catch (Throwable $e) {
            Log::warning('Outbound webhook queue failed', [
                'trigger' => $trigger,
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Evaluate conditions and plan actions without mutating domain data.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function dryRun(WorkflowAutomation $automation, array $context = []): array
    {
        $context = $this->mergeAutomationContext($context);
        $depth = (int) ($context['automation_depth'] ?? 0);
        $chain = $this->normalizeChain($context['trigger_chain'] ?? []);
        $maxDepth = min((int) ($automation->max_depth ?? 3), 5);

        $preview = [
            'automation_id' => $automation->id,
            'trigger' => $automation->trigger,
            'depth' => $depth,
            'trigger_chain' => $chain,
            'max_depth' => $maxDepth,
            'conditions_pass' => $this->conditionsPass($automation->conditions ?? [], $context),
            'would_skip' => null,
            'planned_actions' => [],
        ];

        if (in_array($automation->id, $chain, true)) {
            $preview['would_skip'] = 'loop_detected';

            return $preview;
        }

        if ($depth >= $maxDepth) {
            $preview['would_skip'] = 'blocked_depth';

            return $preview;
        }

        if (! $preview['conditions_pass']) {
            $preview['would_skip'] = 'conditions_not_met';

            return $preview;
        }

        $actions = is_array($automation->actions) ? $automation->actions : [];
        $maxActions = max(1, (int) ($automation->max_actions_per_run ?? 10));
        $planned = [];
        $index = 0;
        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }
            if ($index >= $maxActions) {
                $planned[] = [
                    'type' => (string) ($action['type'] ?? ''),
                    'skipped' => 'max_actions_per_run',
                ];

                continue;
            }
            $planned[] = [
                'type' => (string) ($action['type'] ?? ''),
                'action' => $action,
                'would_execute' => true,
            ];
            $index++;
        }
        $preview['planned_actions'] = $planned;

        try {
            WorkflowAutomationRun::query()->create([
                'automation_id' => $automation->id,
                'trigger' => (string) $automation->trigger,
                'idempotency_key' => sprintf('dry:%d:%s', $automation->id, uniqid('', true)),
                'source_type' => isset($context['source_type']) ? (string) $context['source_type'] : null,
                'source_id' => isset($context['source_id']) ? (int) $context['source_id'] : null,
                'status' => WorkflowRunStatus::Skipped->value,
                'depth' => $depth,
                'origin_run_id' => isset($context['origin_run_id']) ? (int) $context['origin_run_id'] : null,
                'trigger_chain' => $chain,
                'is_dry_run' => true,
                'result' => ['preview' => $preview],
                'executed_at' => now(),
            ]);
        } catch (Throwable) {
            // Dry-run log is optional; preview still returned.
        }

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function runAutomation(
        WorkflowAutomation $automation,
        string $trigger,
        array $context,
        ?string $idempotencySuffix,
    ): array {
        $sourceType = isset($context['source_type']) ? (string) $context['source_type'] : null;
        $sourceId = isset($context['source_id']) ? (int) $context['source_id'] : null;
        $depth = (int) ($context['automation_depth'] ?? 0);
        $chain = $this->normalizeChain($context['trigger_chain'] ?? []);
        $originRunId = isset($context['origin_run_id']) ? (int) $context['origin_run_id'] : null;
        $maxDepth = min((int) ($automation->max_depth ?? 3), 5);
        $maxActions = max(1, (int) ($automation->max_actions_per_run ?? 10));

        $key = sprintf(
            'auto:%d|%s|%s:%s|%s|d%d',
            $automation->id,
            $trigger,
            $sourceType ?? 'none',
            $sourceId !== null ? (string) $sourceId : '0',
            $idempotencySuffix ?? 'default',
            $depth,
        );

        if (WorkflowAutomationRun::query()->where('idempotency_key', $key)->exists()) {
            return [
                'automation_id' => $automation->id,
                'status' => WorkflowRunStatus::Skipped->value,
                'reason' => 'idempotent',
                'depth' => $depth,
                'trigger_chain' => $chain,
            ];
        }

        if (in_array($automation->id, $chain, true)) {
            return $this->logRun($automation, $trigger, $key, $sourceType, $sourceId, WorkflowRunStatus::Skipped, [
                'reason' => 'loop_detected',
                'depth' => $depth,
                'trigger_chain' => $chain,
            ], $depth, $originRunId, $chain);
        }

        if ($depth >= $maxDepth) {
            return $this->logRun($automation, $trigger, $key, $sourceType, $sourceId, WorkflowRunStatus::Skipped, [
                'reason' => 'blocked_depth',
                'depth' => $depth,
                'max_depth' => $maxDepth,
                'trigger_chain' => $chain,
            ], $depth, $originRunId, $chain);
        }

        if (! $this->conditionsPass($automation->conditions ?? [], $context)) {
            return $this->logRun($automation, $trigger, $key, $sourceType, $sourceId, WorkflowRunStatus::Skipped, [
                'reason' => 'conditions_not_met',
                'depth' => $depth,
                'trigger_chain' => $chain,
            ], $depth, $originRunId, $chain);
        }

        try {
            $createdItemId = null;
            $actionResults = [];
            $childChain = [...$chain, (int) $automation->id];

            DB::transaction(function () use (
                $automation,
                $context,
                &$createdItemId,
                &$actionResults,
                $depth,
                $childChain,
                $originRunId,
                $maxActions,
            ): void {
                $actor = $this->resolveActor($automation);
                $actions = is_array($automation->actions) ? $automation->actions : [];
                $executed = 0;

                foreach ($actions as $action) {
                    if (! is_array($action)) {
                        continue;
                    }

                    if ($executed >= $maxActions) {
                        $actionResults[] = [
                            'type' => (string) ($action['type'] ?? ''),
                            'ok' => false,
                            'skipped' => 'max_actions_per_run',
                        ];

                        continue;
                    }

                    $type = (string) ($action['type'] ?? '');
                    $nestedContext = array_merge($context, [
                        'automation_depth' => $depth + 1,
                        'trigger_chain' => $childChain,
                        'origin_run_id' => $originRunId,
                    ]);

                    $actionResults[] = AutomationContext::with($nestedContext, function () use (
                        $type,
                        $actor,
                        $action,
                        $context,
                        &$createdItemId,
                    ) {
                        return match ($type) {
                            'create_task' => $this->actionCreateCalendar($actor, $action, $context, CalendarItemType::Task, $createdItemId),
                            'create_reminder' => $this->actionCreateCalendar($actor, $action, $context, CalendarItemType::Reminder, $createdItemId),
                            'create_event' => $this->actionCreateCalendar($actor, $action, $context, CalendarItemType::Event, $createdItemId),
                            'notify_user' => $this->actionNotifyUser($action, $context),
                            'assign_task' => $this->actionAssignTask($actor, $action, $createdItemId),
                            'set_priority' => $this->actionSetPriority($actor, $action, $createdItemId),
                            'add_checklist' => $this->actionAddChecklist($actor, $action, $createdItemId),
                            default => ['type' => $type, 'ok' => false, 'error' => 'unknown_action'],
                        };
                    });
                    $executed++;
                }

                $automation->forceFill(['last_run_at' => now()])->save();
            });

            return $this->logRun($automation, $trigger, $key, $sourceType, $sourceId, WorkflowRunStatus::Success, [
                'actions' => $actionResults,
                'calendar_item_id' => $createdItemId,
                'depth' => $depth,
                'trigger_chain' => $chain,
            ], $depth, $originRunId, $chain);
        } catch (Throwable $e) {
            Log::warning('Workflow automation failed', [
                'automation_id' => $automation->id,
                'trigger' => $trigger,
                'error' => $e->getMessage(),
            ]);

            return $this->logRun($automation, $trigger, $key, $sourceType, $sourceId, WorkflowRunStatus::Failed, [
                'error' => $e->getMessage(),
                'depth' => $depth,
                'trigger_chain' => $chain,
            ], $depth, $originRunId, $chain);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function mergeAutomationContext(array $context): array
    {
        $bag = AutomationContext::all();
        if ($bag === []) {
            return $context;
        }

        return array_merge([
            'automation_depth' => $bag['automation_depth'] ?? 0,
            'origin_run_id' => $bag['origin_run_id'] ?? null,
            'trigger_chain' => $bag['trigger_chain'] ?? [],
        ], $context);
    }

    /**
     * @return list<int>
     */
    private function normalizeChain(mixed $chain): array
    {
        if (! is_array($chain)) {
            return [];
        }

        return array_values(array_map('intval', $chain));
    }

    /**
     * @param  list<array<string, mixed>>|array<string, mixed>|null  $conditions
     * @param  array<string, mixed>  $context
     */
    private function conditionsPass(mixed $conditions, array $context): bool
    {
        if (! is_array($conditions) || $conditions === []) {
            return true;
        }

        $payload = array_merge($context, is_array($context['payload'] ?? null) ? $context['payload'] : []);

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            $field = (string) ($condition['field'] ?? '');
            $op = (string) ($condition['op'] ?? 'eq');
            $expected = $condition['value'] ?? null;
            $actual = data_get($payload, $field);

            $ok = match ($op) {
                'eq' => $actual == $expected,
                'neq' => $actual != $expected,
                'in' => is_array($expected) && in_array($actual, $expected, false),
                'gte' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
                'lte' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
                default => false,
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function actionCreateCalendar(
        User $actor,
        array $action,
        array $context,
        CalendarItemType $type,
        ?int &$createdItemId,
    ): array {
        $title = (string) ($action['title'] ?? $context['title'] ?? 'مهمة تلقائية');
        $startsAt = (string) ($action['starts_at'] ?? $context['starts_at'] ?? now()->addHour()->toIso8601String());
        $assigneeIds = $action['assignee_ids'] ?? $context['assignee_ids'] ?? [$actor->id];
        if (! is_array($assigneeIds) || $assigneeIds === []) {
            $assigneeIds = [$actor->id];
        }

        $interpolationContext = $this->safeInterpolationContext($context);

        $payload = [
            'title' => $this->interpolate($title, $interpolationContext),
            'description' => isset($action['description'])
                ? $this->interpolate((string) $action['description'], $interpolationContext)
                : null,
            'type' => $type->value,
            'priority' => $action['priority'] ?? CalendarItemPriority::Medium->value,
            'visibility' => $action['visibility'] ?? CalendarVisibility::Participants->value,
            'source' => CalendarSource::Manual->value,
            'starts_at' => $startsAt,
            'ends_at' => $action['ends_at'] ?? null,
            'all_day' => (bool) ($action['all_day'] ?? false),
            'assignee_ids' => array_map('intval', $assigneeIds),
            'related_type' => $action['related_type'] ?? $context['related_type'] ?? null,
            'related_id' => $action['related_id'] ?? $context['related_id'] ?? null,
            'reminders' => $action['reminders'] ?? [],
            'checklist' => $action['checklist'] ?? null,
            'department_id' => $action['department_id'] ?? $context['department_id'] ?? null,
        ];

        if (($payload['related_type'] === null || $payload['related_id'] === null)
            && isset($context['source_type'], $context['source_id'])
            && in_array((string) $context['source_type'], [
                'order', 'project', 'crm_lead', 'crm_contact', 'crm_company', 'crm_opportunity',
                'crm_quotation', 'crm_follow_up', 'printing_request',
            ], true)
        ) {
            $payload['related_type'] = (string) $context['source_type'];
            $payload['related_id'] = (int) $context['source_id'];
        }

        try {
            $item = $this->calendar->create($actor, $payload);
        } catch (ValidationException $e) {
            if (! array_key_exists('related_id', $e->errors()) && ! array_key_exists('related_type', $e->errors())) {
                throw $e;
            }
            $payload['related_type'] = null;
            $payload['related_id'] = null;
            $item = $this->calendar->create($actor, $payload);
        }

        $createdItemId = (int) $item->id;

        return ['type' => 'create_'.$type->value, 'ok' => true, 'calendar_item_id' => $item->id];
    }

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function actionNotifyUser(array $action, array $context): array
    {
        $userId = (int) ($action['user_id'] ?? $context['notify_user_id'] ?? 0);
        $user = User::query()->find($userId);
        if ($user === null || ! $user->is_active) {
            return ['type' => 'notify_user', 'ok' => false, 'error' => 'user_not_found'];
        }

        if (! $this->allowsAutomationNotification($user)) {
            return ['type' => 'notify_user', 'ok' => true, 'skipped' => 'preferences'];
        }

        $sent = $this->notifier->notify($user, new CalendarNotification([
            'type' => 'automation_notification',
            'title' => $this->interpolate((string) ($action['title'] ?? 'تنبيه تلقائي'), $context),
            'message' => $this->interpolate((string) ($action['message'] ?? ''), $context),
            'href' => $action['href'] ?? null,
        ]), 'automation');

        return [
            'type' => 'notify_user',
            'ok' => true,
            'user_id' => $user->id,
            'delayed' => ! $sent,
        ];
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private function actionAssignTask(User $actor, array $action, ?int $createdItemId): array
    {
        if ($createdItemId === null) {
            return ['type' => 'assign_task', 'ok' => false, 'error' => 'no_item'];
        }

        $ids = array_map('intval', (array) ($action['assignee_ids'] ?? []));
        if ($ids === []) {
            return ['type' => 'assign_task', 'ok' => false, 'error' => 'no_assignees'];
        }

        $item = CalendarItem::query()->findOrFail($createdItemId);
        $this->calendar->update($actor, $item, ['assignee_ids' => $ids]);

        return ['type' => 'assign_task', 'ok' => true, 'assignee_ids' => $ids];
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private function actionSetPriority(User $actor, array $action, ?int $createdItemId): array
    {
        if ($createdItemId === null) {
            return ['type' => 'set_priority', 'ok' => false, 'error' => 'no_item'];
        }

        $priority = (string) ($action['priority'] ?? CalendarItemPriority::High->value);
        $item = CalendarItem::query()->findOrFail($createdItemId);
        $this->calendar->update($actor, $item, ['priority' => $priority]);

        return ['type' => 'set_priority', 'ok' => true, 'priority' => $priority];
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private function actionAddChecklist(User $actor, array $action, ?int $createdItemId): array
    {
        if ($createdItemId === null) {
            return ['type' => 'add_checklist', 'ok' => false, 'error' => 'no_item'];
        }

        $checklist = is_array($action['checklist'] ?? null) ? $action['checklist'] : [];
        $item = CalendarItem::query()->findOrFail($createdItemId);
        $this->calendar->syncChecklist($actor, $item, $checklist);

        return ['type' => 'add_checklist', 'ok' => true, 'count' => count($checklist)];
    }

    private function resolveActor(WorkflowAutomation $automation): User
    {
        $creator = $automation->creator ?? User::query()->find($automation->created_by);
        if ($creator instanceof User && $creator->is_active) {
            return $creator;
        }

        $owner = User::query()
            ->where('role', UserRole::Owner)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($owner === null) {
            throw new \RuntimeException('No system actor available for workflow automation.');
        }

        return $owner;
    }

    private function allowsAutomationNotification(User $user): bool
    {
        $prefs = UserNotificationPreference::query()->where('user_id', $user->id)->first();
        if ($prefs === null) {
            return true;
        }

        return (bool) $prefs->automation_notifications;
    }

    /**
     * Context keys safe for title/description interpolation.
     * CRM task creation never exposes financial fields (amounts, totals, discounts).
     *
     * @return list<string>
     */
    public function allowlistedInterpolationKeys(?string $sourceType = null): array
    {
        $crmSources = [
            'crm_lead',
            'crm_contact',
            'crm_company',
            'crm_opportunity',
            'crm_quotation',
            'crm_follow_up',
        ];

        if ($sourceType !== null && in_array($sourceType, $crmSources, true)) {
            return ['title', 'reference', 'customer_name'];
        }

        return [
            'title',
            'reference',
            'customer_name',
            'source_type',
            'source_id',
            'related_type',
            'related_id',
            'department_id',
            'starts_at',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function safeInterpolationContext(array $context): array
    {
        $sourceType = isset($context['source_type']) ? (string) $context['source_type'] : null;
        $allowed = $this->allowlistedInterpolationKeys($sourceType);
        $safe = [];

        foreach ($allowed as $key) {
            $value = $context[$key] ?? data_get($context, 'payload.'.$key);
            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function interpolate(string $text, array $context): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function (array $matches) use ($context): string {
            $key = $matches[1];
            if (! array_key_exists($key, $context)) {
                return '';
            }

            $value = $context[$key];

            return is_scalar($value) || $value === null ? (string) $value : '';
        }, $text) ?? $text;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<int>  $chain
     * @return array<string, mixed>
     */
    private function logRun(
        WorkflowAutomation $automation,
        string $trigger,
        string $key,
        ?string $sourceType,
        ?int $sourceId,
        WorkflowRunStatus $status,
        array $result,
        int $depth = 0,
        ?int $originRunId = null,
        array $chain = [],
    ): array {
        try {
            WorkflowAutomationRun::query()->create([
                'automation_id' => $automation->id,
                'trigger' => $trigger,
                'idempotency_key' => $key,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'status' => $status->value,
                'depth' => $depth,
                'origin_run_id' => $originRunId,
                'trigger_chain' => $chain,
                'is_dry_run' => false,
                'result' => $result,
                'executed_at' => now(),
            ]);
        } catch (Throwable $e) {
            return [
                'automation_id' => $automation->id,
                'status' => WorkflowRunStatus::Skipped->value,
                'reason' => 'idempotent_race',
                'depth' => $depth,
                'trigger_chain' => $chain,
            ];
        }

        return [
            'automation_id' => $automation->id,
            'status' => $status->value,
            'result' => $result,
            'depth' => $depth,
            'trigger_chain' => $chain,
        ];
    }
}
