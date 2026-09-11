<?php

namespace App\Services\Operations;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\UserRole;
use App\Models\BusinessCalendar;
use App\Models\CalendarItem;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\OperationalSlaRule;
use App\Models\PrintingRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class SlaEvaluationService
{
    public const MODULE_CRM_LEAD = 'crm_lead';

    public const MODULE_PRINTING = 'printing';

    public const MODULE_CALENDAR_TASK = 'calendar_task';

    public const EVENT_FIRST_ACTIVITY = 'first_activity';

    public const EVENT_REVIEW_QUOTE = 'review_quote';

    public const EVENT_COMPLETION_DELAY = 'completion_delay';

    public function __construct(
        private readonly BusinessTimeService $businessTime,
        private readonly OperationsAuditLogger $audit,
    ) {}

    /**
     * @return list<string>
     */
    public static function modules(): array
    {
        return [self::MODULE_CRM_LEAD, self::MODULE_PRINTING, self::MODULE_CALENDAR_TASK];
    }

    /**
     * @return list<string>
     */
    public static function eventTypes(): array
    {
        return [self::EVENT_FIRST_ACTIVITY, self::EVENT_REVIEW_QUOTE, self::EVENT_COMPLETION_DELAY];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRules(): array
    {
        return OperationalSlaRule::query()
            ->with(['department:id,name', 'creator:id,name', 'businessCalendar:id,name'])
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->map(fn (OperationalSlaRule $rule) => $this->serializeRule($rule))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createRule(User $actor, array $attributes): OperationalSlaRule
    {
        $this->assertCanManage($actor);
        $this->assertRuleShape($attributes);

        $rule = OperationalSlaRule::query()->create([
            'name' => $attributes['name'],
            'module' => $attributes['module'],
            'event_type' => $attributes['event_type'],
            'target_minutes' => (int) $attributes['target_minutes'],
            'department_id' => $attributes['department_id'] ?? null,
            'business_calendar_id' => $attributes['business_calendar_id'] ?? null,
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'created_by' => $actor->id,
        ])->load(['department:id,name', 'creator:id,name', 'businessCalendar:id,name']);

        $this->audit->log($actor, 'sla_rule.created', $rule, [
            'module' => $rule->module,
            'event_type' => $rule->event_type,
            'target_minutes' => $rule->target_minutes,
        ]);

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateRule(User $actor, OperationalSlaRule $rule, array $attributes): OperationalSlaRule
    {
        $this->assertCanManage($actor);

        $merged = array_merge([
            'module' => $rule->module,
            'event_type' => $rule->event_type,
            'target_minutes' => $rule->target_minutes,
        ], $attributes);
        $this->assertRuleShape($merged);

        $rule->update([
            'name' => $attributes['name'] ?? $rule->name,
            'module' => $attributes['module'] ?? $rule->module,
            'event_type' => $attributes['event_type'] ?? $rule->event_type,
            'target_minutes' => isset($attributes['target_minutes'])
                ? (int) $attributes['target_minutes']
                : $rule->target_minutes,
            'department_id' => array_key_exists('department_id', $attributes)
                ? $attributes['department_id']
                : $rule->department_id,
            'business_calendar_id' => array_key_exists('business_calendar_id', $attributes)
                ? $attributes['business_calendar_id']
                : $rule->business_calendar_id,
            'is_active' => array_key_exists('is_active', $attributes)
                ? (bool) $attributes['is_active']
                : $rule->is_active,
        ]);

        $this->audit->log($actor, 'sla_rule.updated', $rule, [
            'changes' => array_keys($attributes),
        ]);

        return $rule->fresh(['department:id,name', 'creator:id,name', 'businessCalendar:id,name']) ?? $rule;
    }

    public function deleteRule(User $actor, OperationalSlaRule $rule): void
    {
        $this->assertCanManage($actor);
        $ruleId = $rule->id;
        $rule->delete();
        $this->audit->log($actor, 'sla_rule.deleted', null, [
            'rule_id' => $ruleId,
        ]);
    }

    /**
     * Evaluate open/recent samples against active rules.
     *
     * @return array{breached: int, approaching: int, on_time: int, unknown: int, samples: list<array<string, mixed>>}
     */
    public function evaluateActive(?int $limit = 50): array
    {
        $rules = OperationalSlaRule::query()->where('is_active', true)->get();
        $counts = ['breached' => 0, 'approaching' => 0, 'on_time' => 0, 'unknown' => 0];
        $samples = [];

        foreach ($rules as $rule) {
            foreach ($this->samplesForRule($rule, 20) as $sample) {
                $status = $sample['status'];
                $counts[$status] = ($counts[$status] ?? 0) + 1;
                if (count($samples) < ($limit ?? 50)) {
                    $samples[] = $sample;
                }
            }
        }

        return [
            'breached' => $counts['breached'],
            'approaching' => $counts['approaching'],
            'on_time' => $counts['on_time'],
            'unknown' => $counts['unknown'],
            'samples' => $samples,
        ];
    }

    public function breachedCount(): int
    {
        return (int) ($this->evaluateActive(1)['breached'] ?? 0);
    }

    /**
     * @return array{on_time: int, approaching: int, breached: int, unknown: int, rules: int}
     */
    public function complianceSummary(): array
    {
        $eval = $this->evaluateActive(5);

        return [
            'on_time' => $eval['on_time'],
            'approaching' => $eval['approaching'],
            'breached' => $eval['breached'],
            'unknown' => $eval['unknown'],
            'rules' => OperationalSlaRule::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function samplesForRule(OperationalSlaRule $rule, int $limit): array
    {
        return match ($rule->module) {
            self::MODULE_CRM_LEAD => $this->evaluateCrmLeads($rule, $limit),
            self::MODULE_PRINTING => $this->evaluatePrinting($rule, $limit),
            self::MODULE_CALENDAR_TASK => $this->evaluateCalendarTasks($rule, $limit),
            default => [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function evaluateCrmLeads(OperationalSlaRule $rule, int $limit): array
    {
        if ($rule->event_type !== self::EVENT_FIRST_ACTIVITY) {
            return [];
        }

        $leads = CrmLead::query()->orderByDesc('id')->limit($limit)->get();
        $out = [];

        foreach ($leads as $lead) {
            $start = $lead->created_at;
            if ($start === null) {
                $out[] = $this->sample($rule, 'crm_lead', (int) $lead->id, 'unknown', null, null);

                continue;
            }

            $end = $lead->first_contacted_at;
            if ($end === null) {
                $firstActivity = CrmActivity::query()
                    ->where('lead_id', $lead->id)
                    ->orderBy('occurred_at')
                    ->orderBy('id')
                    ->first();
                $end = $firstActivity?->occurred_at ?? $firstActivity?->created_at;
            }

            $out[] = $this->classify($rule, 'crm_lead', (int) $lead->id, $start, $end, $rule->department_id);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function evaluatePrinting(OperationalSlaRule $rule, int $limit): array
    {
        if ($rule->event_type !== self::EVENT_REVIEW_QUOTE) {
            return [];
        }

        $rows = PrintingRequest::query()->orderByDesc('id')->limit($limit)->get();
        $out = [];

        foreach ($rows as $row) {
            $start = $row->created_at;
            if ($start === null) {
                $out[] = $this->sample($rule, 'printing_request', (int) $row->id, 'unknown', null, null);

                continue;
            }

            $out[] = $this->classify(
                $rule,
                'printing_request',
                (int) $row->id,
                $start,
                $row->quoted_at,
                $row->assigned_department_id !== null ? (int) $row->assigned_department_id : $rule->department_id,
            );
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function evaluateCalendarTasks(OperationalSlaRule $rule, int $limit): array
    {
        if ($rule->event_type !== self::EVENT_COMPLETION_DELAY) {
            return [];
        }

        $query = CalendarItem::query()
            ->where('type', CalendarItemType::Task->value)
            ->orderByDesc('id')
            ->limit($limit);

        if ($rule->department_id) {
            $query->where('department_id', $rule->department_id);
        }

        $out = [];
        foreach ($query->get() as $item) {
            $start = $item->starts_at;
            if ($start === null) {
                $out[] = $this->sample($rule, 'calendar_item', (int) $item->id, 'unknown', null, null);

                continue;
            }

            $status = $item->status instanceof CalendarItemStatus
                ? $item->status->value
                : (string) $item->status;

            $end = $status === CalendarItemStatus::Completed->value
                ? ($item->completed_at ?? $item->updated_at)
                : null;

            // Still open: measure delay from starts_at to now when past due.
            if ($end === null && $start->isPast() && $status !== CalendarItemStatus::Cancelled->value) {
                $end = now();
            }

            $out[] = $this->classify(
                $rule,
                'calendar_item',
                (int) $item->id,
                $start,
                $end,
                $item->department_id !== null ? (int) $item->department_id : $rule->department_id,
            );
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function classify(
        OperationalSlaRule $rule,
        string $relatedType,
        int $relatedId,
        Carbon $start,
        mixed $end,
        ?int $contextDepartmentId = null,
    ): array {
        $calendar = $this->businessTime->resolveCalendar(
            $rule->business_calendar_id !== null ? (int) $rule->business_calendar_id : null,
            $contextDepartmentId ?? ($rule->department_id !== null ? (int) $rule->department_id : null),
        );

        if ($end === null) {
            $elapsed = $this->elapsedMinutes($start, now(), $calendar);
            if ($elapsed === null) {
                return $this->sample($rule, $relatedType, $relatedId, 'unknown', null, $rule->target_minutes);
            }

            $status = $this->statusFromElapsed($elapsed, (int) $rule->target_minutes);

            return $this->sample($rule, $relatedType, $relatedId, $status, $elapsed, (int) $rule->target_minutes);
        }

        $endAt = $end instanceof Carbon ? $end : Carbon::parse($end);
        $elapsed = $this->elapsedMinutes($start, $endAt, $calendar);
        if ($elapsed === null) {
            return $this->sample($rule, $relatedType, $relatedId, 'unknown', null, $rule->target_minutes);
        }

        return $this->sample(
            $rule,
            $relatedType,
            $relatedId,
            $this->statusFromElapsed($elapsed, (int) $rule->target_minutes),
            $elapsed,
            (int) $rule->target_minutes,
        );
    }

    /**
     * Prefer business-day minutes when an active calendar exists; otherwise wall-clock.
     */
    private function elapsedMinutes(Carbon $start, Carbon $end, ?BusinessCalendar $calendar = null): ?int
    {
        if ($this->businessTime->hasActiveCalendar()) {
            return $this->businessTime->businessMinutesBetween($start, $end, $calendar);
        }

        $elapsed = (int) $start->diffInMinutes($end, false);

        return $elapsed < 0 ? null : $elapsed;
    }

    private function statusFromElapsed(int $elapsed, int $target): string
    {
        if ($elapsed > $target) {
            return 'breached';
        }

        if ($elapsed >= (int) floor($target * 0.8)) {
            return 'approaching';
        }

        return 'on_time';
    }

    /**
     * @return array<string, mixed>
     */
    private function sample(
        OperationalSlaRule $rule,
        string $relatedType,
        int $relatedId,
        string $status,
        ?int $elapsed,
        ?int $target,
    ): array {
        return [
            'rule_id' => $rule->id,
            'module' => $rule->module,
            'event_type' => $rule->event_type,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'status' => $status,
            'elapsed_minutes' => $elapsed,
            'target_minutes' => $target,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeRule(OperationalSlaRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'module' => $rule->module,
            'event_type' => $rule->event_type,
            'target_minutes' => $rule->target_minutes,
            'department_id' => $rule->department_id,
            'department' => $rule->department ? [
                'id' => $rule->department->id,
                'name' => $rule->department->name,
            ] : null,
            'business_calendar_id' => $rule->business_calendar_id,
            'business_calendar' => $rule->businessCalendar ? [
                'id' => $rule->businessCalendar->id,
                'name' => $rule->businessCalendar->name,
            ] : null,
            'is_active' => (bool) $rule->is_active,
            'created_by' => $rule->created_by,
            'creator' => $rule->creator ? [
                'id' => $rule->creator->id,
                'name' => $rule->creator->name,
            ] : null,
            'created_at' => $rule->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertRuleShape(array $attributes): void
    {
        $module = (string) ($attributes['module'] ?? '');
        $event = (string) ($attributes['event_type'] ?? '');

        if (! in_array($module, self::modules(), true)) {
            throw ValidationException::withMessages([
                'module' => ['Unsupported SLA module.'],
            ]);
        }

        $allowedEvent = match ($module) {
            self::MODULE_CRM_LEAD => self::EVENT_FIRST_ACTIVITY,
            self::MODULE_PRINTING => self::EVENT_REVIEW_QUOTE,
            self::MODULE_CALENDAR_TASK => self::EVENT_COMPLETION_DELAY,
            default => null,
        };

        if ($event !== $allowedEvent) {
            throw ValidationException::withMessages([
                'event_type' => ['Event type does not match module.'],
            ]);
        }

        if ((int) ($attributes['target_minutes'] ?? 0) < 1) {
            throw ValidationException::withMessages([
                'target_minutes' => ['Target minutes must be at least 1.'],
            ]);
        }
    }

    private function assertCanManage(User $actor): void
    {
        if (! ($actor->role instanceof UserRole)
            || ! in_array($actor->role, [UserRole::Owner, UserRole::AdminManager], true)
        ) {
            throw ValidationException::withMessages([
                'sla' => ['Only Owner or Admin Manager can manage SLA rules.'],
            ]);
        }
    }
}
