<?php

namespace App\Services\Operations;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\OperationalAttentionSnooze;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Operations\Work\UnifiedWorkService;
use App\Services\Printing\RefundImpactService;
use App\Services\Quotes\QuoteRequestService;
use App\Support\Calendar\CalendarRelatedEntityUrlResolver;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class OperationalAttentionService
{
    public function __construct(
        private readonly CalendarRelatedEntityUrlResolver $urls,
        private readonly ProjectHealthService $health,
        private readonly UnifiedWorkService $unifiedWork,
        private readonly PrintingOperationsService $printing,
        private readonly SlaEvaluationService $sla,
        private readonly RefundImpactService $refundImpact,
        private readonly QuoteRequestService $quoteRequests,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function for(User $actor, int $limit = 25): array
    {
        $items = [];

        $overdueWork = $this->unifiedWork->list($actor, [
            'scope' => ($actor->role instanceof UserRole && $actor->role->canViewTeamCalendar()) ? 'team' : 'mine',
            'bucket' => 'overdue',
            'per_page' => 15,
            'page' => 1,
            'sort' => 'overdue_first',
        ]);

        $calendarOverdue = [];
        foreach ($overdueWork['items'] as $row) {
            if (($row['source_type'] ?? '') === 'task') {
                $items[] = $this->withKey([
                    'type' => 'task_overdue',
                    'severity' => 'critical',
                    'title' => $row['title'] ?? '',
                    'description' => 'مهمة مساحة عمل متأخرة',
                    'source' => 'task',
                    'related_type' => 'workspace_task',
                    'related_id' => $row['source_id'] ?? null,
                    'href' => $row['href'] ?? null,
                    'work_id' => $row['id'] ?? null,
                ]);
            } else {
                $calendarOverdue[] = $row;
            }
        }

        $items = array_merge($items, $this->groupCalendarOverdue($actor, $calendarOverdue));

        foreach ($this->printing->attentionItems($actor, 8) as $printItem) {
            $items[] = $this->withKey($printItem);
        }

        foreach ($this->refundImpact->attentionItems($actor, 8) as $refundItem) {
            $items[] = $this->withKey($refundItem);
        }

        if ($actor->role instanceof UserRole && $actor->role->canViewQuoteRequests()) {
            foreach ($this->quoteRequests->attentionItems() as $quoteItem) {
                $items[] = $this->withKey([
                    'type' => $quoteItem['type'],
                    'severity' => $quoteItem['type'] === 'quote_request_new' ? 'high' : 'medium',
                    'title' => $quoteItem['label'],
                    'description' => $quoteItem['count'].' عنصر يحتاج متابعة',
                    'source' => 'quote_request',
                    'related_type' => 'quote_request',
                    'related_id' => null,
                    'href' => $quoteItem['href'],
                    'count' => $quoteItem['count'],
                ]);
            }
        }

        $overdueOther = CalendarItem::query()
            ->where('status', CalendarItemStatus::Overdue->value)
            ->where('type', '!=', CalendarItemType::Task->value)
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        foreach ($overdueOther as $item) {
            $items[] = $this->withKey([
                'type' => 'calendar_overdue',
                'severity' => 'critical',
                'title' => $item->title,
                'description' => 'عنصر تقويم متأخر',
                'source' => 'calendar',
                'related_type' => 'calendar_item',
                'related_id' => $item->id,
                'href' => $this->calendarHref($actor, (int) $item->id),
            ]);
        }

        $projects = Project::query()
            ->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])
            ->orderBy('deadline')
            ->limit(20)
            ->get();

        foreach ($projects as $project) {
            $health = $this->health->evaluate($project);
            if (! in_array($health['status'], ['needs_attention', 'overdue'], true)) {
                continue;
            }

            $items[] = $this->withKey([
                'type' => 'project_health',
                'severity' => $health['status'] === 'overdue' ? 'critical' : 'high',
                'title' => $project->title,
                'description' => $health['label'],
                'source' => 'project',
                'related_type' => 'project',
                'related_id' => $project->id,
                'href' => $this->urls->resolve($actor, 'project', (int) $project->id),
            ]);
        }

        if ($actor->role instanceof UserRole && $actor->role->canManageOrders()) {
            $orders = Order::query()
                ->whereNull('delivered_at')
                ->whereIn('status', [
                    OrderStatus::Completed->value,
                    OrderStatus::InProgress->value,
                    OrderStatus::Review->value,
                ])
                ->orderByDesc('id')
                ->limit(8)
                ->get();

            foreach ($orders as $order) {
                $items[] = $this->withKey([
                    'type' => 'upcoming_delivery',
                    'severity' => 'medium',
                    'title' => $order->title,
                    'description' => 'طلب بانتظار التسليم ('.$order->reference.')',
                    'source' => 'order',
                    'related_type' => 'order',
                    'related_id' => $order->id,
                    'href' => $this->urls->resolve($actor, 'order', (int) $order->id),
                ]);
            }
        }

        if ($actor->role instanceof UserRole && $actor->role->canManagePayments()) {
            $pendingPayments = Payment::query()
                ->whereIn('status', [
                    PaymentStatus::Pending->value,
                    PaymentStatus::PendingVerification->value,
                    PaymentStatus::Processing->value,
                ])
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($pendingPayments as $payment) {
                $items[] = $this->withKey([
                    'type' => 'payment_attention',
                    'severity' => 'high',
                    'title' => 'دفعة #'.$payment->id,
                    'description' => 'دفعة تحتاج مراجعة',
                    'source' => 'payment',
                    'related_type' => 'payment',
                    'related_id' => $payment->id,
                    'href' => $this->urls->resolve($actor, 'payment', (int) $payment->id),
                ]);
            }

            $mismatchFailures = Payment::query()
                ->whereNotNull('printing_quotation_id')
                ->where('status', PaymentStatus::Failed->value)
                ->where(function ($query): void {
                    $query->where('failure_reason', 'Payment verification failed.')
                        ->orWhere('reconciliation_note', 'like', 'mismatch:%');
                })
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($mismatchFailures as $payment) {
                $items[] = $this->withKey([
                    'type' => 'printing_payment_mismatch',
                    'severity' => 'critical',
                    'title' => 'عدم تطابق دفعة طباعة #'.$payment->id,
                    'description' => 'فشل التحقق من مبلغ/بيانات الدفع لعرض طباعة',
                    'source' => 'payment',
                    'related_type' => 'payment',
                    'related_id' => $payment->id,
                    'href' => $this->urls->resolve($actor, 'payment', (int) $payment->id),
                ]);
            }
        }

        if ($actor->role instanceof UserRole && $actor->role->canViewCommandCenter()) {
            $breached = $this->sla->breachedCount();
            if ($breached > 0) {
                $items[] = $this->withKey([
                    'type' => 'sla_breached',
                    'severity' => 'critical',
                    'title' => 'اختراقات SLA',
                    'description' => $breached.' عنصر تجاوز هدف SLA',
                    'source' => 'sla',
                    'related_type' => 'sla',
                    'related_id' => null,
                    'count' => $breached,
                    'href' => '/operations/insights',
                ], 'sla_breached');
            }
        }

        usort($items, function (array $a, array $b): int {
            $rank = ['critical' => 0, 'high' => 1, 'medium' => 2];

            return ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9);
        });

        return array_slice($this->filterSnoozed($actor, $items), 0, $limit);
    }

    /**
     * @param  array{attention_key?: string, until?: string, preset?: string}  $payload
     * @return array<string, mixed>
     */
    public function snooze(User $actor, array $payload): array
    {
        $key = trim((string) ($payload['attention_key'] ?? ''));
        if ($key === '' || strlen($key) > 190) {
            throw ValidationException::withMessages([
                'attention_key' => ['A valid attention_key is required.'],
            ]);
        }

        $until = $this->resolveUntil($payload);

        $row = OperationalAttentionSnooze::query()->updateOrCreate(
            [
                'user_id' => $actor->id,
                'attention_key' => $key,
            ],
            [
                'snoozed_until' => $until,
            ],
        );

        return [
            'attention_key' => $row->attention_key,
            'snoozed_until' => $row->snoozed_until?->toIso8601String(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function filterSnoozed(User $actor, array $items): array
    {
        $active = OperationalAttentionSnooze::query()
            ->where('user_id', $actor->id)
            ->where('snoozed_until', '>', now())
            ->pluck('attention_key')
            ->all();

        if ($active === []) {
            return $items;
        }

        $set = array_flip($active);

        return array_values(array_filter(
            $items,
            fn (array $item): bool => ! isset($set[(string) ($item['attention_key'] ?? '')]),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function groupCalendarOverdue(User $actor, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        if (count($rows) <= 3) {
            $out = [];
            foreach ($rows as $row) {
                $out[] = $this->withKey([
                    'type' => 'calendar_overdue',
                    'severity' => 'critical',
                    'title' => $row['title'] ?? '',
                    'description' => 'مهمة تقويم متأخرة',
                    'source' => 'calendar',
                    'related_type' => 'calendar_item',
                    'related_id' => $row['source_id'] ?? null,
                    'href' => $row['href'] ?? null,
                    'work_id' => $row['id'] ?? null,
                ]);
            }

            return $out;
        }

        $titles = array_values(array_map(
            fn (array $row): string => (string) ($row['title'] ?? ''),
            array_slice($rows, 0, 5),
        ));

        return [$this->withKey([
            'type' => 'calendar_overdue',
            'severity' => 'critical',
            'title' => count($rows).' مهام تقويم متأخرة',
            'description' => 'تجميع مهام التقويم المتأخرة',
            'source' => 'calendar',
            'related_type' => 'calendar_item',
            'related_id' => null,
            'count' => count($rows),
            'sample_titles' => $titles,
            'href' => $this->calendarHref($actor, (int) ($rows[0]['source_id'] ?? 0)),
        ], 'calendar_overdue:group')];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function withKey(array $item, ?string $forced = null): array
    {
        $item['attention_key'] = $forced ?? $this->makeKey($item);

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function makeKey(array $item): string
    {
        $type = (string) ($item['type'] ?? 'item');
        $relatedType = (string) ($item['related_type'] ?? 'none');
        $relatedId = $item['related_id'] ?? 'x';

        return substr($type.':'.$relatedType.':'.$relatedId, 0, 190);
    }

    /**
     * @param  array{until?: string, preset?: string}  $payload
     */
    private function resolveUntil(array $payload): Carbon
    {
        if (! empty($payload['until'])) {
            $until = Carbon::parse((string) $payload['until']);
            if ($until->lte(now())) {
                throw ValidationException::withMessages([
                    'until' => ['Snooze time must be in the future.'],
                ]);
            }

            return $until;
        }

        $preset = (string) ($payload['preset'] ?? '');

        return match ($preset) {
            '1h' => now()->addHour(),
            'today' => now()->endOfDay(),
            'tomorrow' => now()->addDay()->endOfDay(),
            default => throw ValidationException::withMessages([
                'preset' => ['Provide until or preset: 1h|today|tomorrow.'],
            ]),
        };
    }

    private function calendarHref(User $actor, int $itemId): string
    {
        if ($itemId <= 0) {
            return match (true) {
                $actor->role === UserRole::Owner => '/owner/calendar',
                $actor->role instanceof UserRole && $actor->role->canAccessCrm() => '/crm/work-calendar',
                default => '/workspace/calendar',
            };
        }

        return match (true) {
            $actor->role === UserRole::Owner => '/owner/calendar?item='.$itemId,
            $actor->role instanceof UserRole && $actor->role->canAccessCrm() => '/crm/work-calendar?item='.$itemId,
            default => '/workspace/calendar?item='.$itemId,
        };
    }
}
