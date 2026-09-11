<?php

namespace App\Services\Operations;

use App\Enums\PrintingRequestStatus;
use App\Enums\UserRole;
use App\Models\PrintingRequest;
use App\Models\PrintingStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PrintingOperationsService
{
    public function __construct(
        private readonly PrintingStatusTransitionService $transitions,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{items: list<array<string, mixed>>, summary: array<string, int>, approaching_days: int}
     */
    public function list(User $actor, array $filters = []): array
    {
        $this->assertCanList($actor);

        $approachingDays = max(0, (int) ($filters['approaching_days'] ?? 2));
        $category = isset($filters['category']) ? (string) $filters['category'] : null;
        $statusFilter = isset($filters['status']) ? (string) $filters['status'] : null;
        $today = Carbon::today()->toDateString();
        $approachingUntil = Carbon::today()->addDays($approachingDays)->toDateString();

        $query = $this->baseVisibleQuery($actor)
            ->with([
                'user:id,name,email',
                'quotedBy:id,name',
                'assignee:id,name,email',
                'assignedDepartment:id,name,slug',
            ])
            ->orderBy('required_date')
            ->orderByDesc('id');

        if ($statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all') {
            if (! in_array($statusFilter, PrintingRequestStatus::values(), true)) {
                throw ValidationException::withMessages([
                    'status' => ['Invalid printing request status filter.'],
                ]);
            }
            $query->where('status', $statusFilter);
        } else {
            $query->whereIn('status', PrintingRequestStatus::openValues());
        }

        if ($category !== null && $category !== '' && $category !== 'all') {
            $this->applyCategory($query, $category, $today, $approachingUntil);
        }

        if (isset($filters['q']) && is_string($filters['q']) && trim($filters['q']) !== '') {
            $term = '%'.trim($filters['q']).'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('product_name', 'like', $term)
                    ->orWhere('product_slug', 'like', $term)
                    ->orWhereHas('user', function (Builder $userQuery) use ($term): void {
                        $userQuery->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
            });
        }

        $items = $query->limit(200)->get()->map(function (PrintingRequest $row) use ($actor, $today, $approachingDays): array {
            return $this->serialize($row, $actor, $today, $approachingDays);
        })->all();

        return [
            'items' => $items,
            'summary' => $this->summaryCounts($actor, $approachingDays),
            'approaching_days' => $approachingDays,
        ];
    }

    /**
     * @return array{
     *     columns: array<string, list<array<string, mixed>>>,
     *     summary: array<string, int>,
     *     approaching_days: int
     * }
     */
    public function board(User $actor, int $approachingDays = 2): array
    {
        $this->assertCanList($actor);

        $approachingDays = max(0, $approachingDays);
        $today = Carbon::today()->toDateString();

        $rows = $this->baseVisibleQuery($actor)
            ->with([
                'user:id,name,email',
                'quotedBy:id,name',
                'assignee:id,name,email',
                'assignedDepartment:id,name,slug',
            ])
            ->orderBy('required_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $columns = [];
        foreach (PrintingRequestStatus::cases() as $status) {
            $columns[$status->value] = [];
        }

        foreach ($rows as $row) {
            $statusValue = $row->status instanceof PrintingRequestStatus
                ? $row->status->value
                : (string) $row->status;
            $columns[$statusValue][] = $this->serialize($row, $actor, $today, $approachingDays);
        }

        return [
            'columns' => $columns,
            'summary' => $this->summaryCounts($actor, $approachingDays),
            'approaching_days' => $approachingDays,
        ];
    }

    /**
     * @return array{summary: array<string, int>, approaching_days: int}
     */
    public function summary(User $actor, int $approachingDays = 2): array
    {
        $this->assertCanList($actor);

        return [
            'summary' => $this->summaryCounts($actor, $approachingDays),
            'approaching_days' => $approachingDays,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(User $actor, PrintingRequest $request): array
    {
        if (! $this->canManage($actor) && $request->user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'printing' => ['You cannot view this printing request history.'],
            ]);
        }

        $rows = $request->statusHistories()
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return $rows->map(function (PrintingStatusHistory $row): array {
            return [
                'id' => $row->id,
                'from_status' => $row->from_status instanceof PrintingRequestStatus
                    ? $row->from_status->value
                    : $row->from_status,
                'to_status' => $row->to_status instanceof PrintingRequestStatus
                    ? $row->to_status->value
                    : $row->to_status,
                'note' => $row->note,
                'actor' => $row->actor ? [
                    'id' => $row->actor->id,
                    'name' => $row->actor->name,
                ] : null,
                'created_at' => $row->created_at?->toIso8601String(),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attentionItems(User $actor, int $limit = 10): array
    {
        if (! $this->canList($actor)) {
            return [];
        }

        $today = Carbon::today()->toDateString();
        $rows = $this->baseOpenQuery($actor)
            ->whereDate('required_date', '<', $today)
            ->orderBy('required_date')
            ->limit($limit)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $daysLate = (int) Carbon::parse($row->required_date)->startOfDay()
                ->diffInDays(Carbon::today()->startOfDay(), false);
            $severity = match (true) {
                $daysLate >= 2 => 'critical',
                $daysLate === 1 => 'high',
                default => 'medium',
            };

            $items[] = [
                'type' => 'printing_overdue',
                'severity' => $severity,
                'title' => $row->product_name,
                'description' => 'طلب طباعة متأخر ('.max(0, $daysLate).' يوم)',
                'source' => 'printing',
                'related_type' => 'printing_request',
                'related_id' => $row->id,
                'href' => '/operations/printing?item='.$row->id,
                'days_late' => max(0, $daysLate),
            ];
        }

        return $items;
    }

    /**
     * Derived actionable items for Command Center / unified work surfaces.
     *
     * @return list<array<string, mixed>>
     */
    public function actionableItems(User $actor, int $limit = 20): array
    {
        if (! $this->canList($actor)) {
            return [];
        }

        $today = Carbon::today()->toDateString();
        $rows = $this->baseOpenQuery($actor)
            ->with(['assignee:id,name', 'assignedDepartment:id,name'])
            ->orderByRaw('case when required_date < ? then 0 else 1 end', [$today])
            ->orderBy('required_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $status = $row->status instanceof PrintingRequestStatus
                ? $row->status
                : PrintingRequestStatus::from((string) $row->status);
            $required = $row->required_date?->toDateString();
            $overdue = $required !== null && $required < $today;

            $items[] = [
                'id' => 'printing-'.$row->id,
                'type' => 'printing_request',
                'title' => $row->product_name,
                'status' => $status->value,
                'status_label' => $status->label(),
                'required_date' => $required,
                'overdue' => $overdue,
                'assigned_to' => $row->assignee ? [
                    'id' => $row->assignee->id,
                    'name' => $row->assignee->name,
                ] : null,
                'assigned_department' => $row->assignedDepartment ? [
                    'id' => $row->assignedDepartment->id,
                    'name' => $row->assignedDepartment->name,
                ] : null,
                'allowed_transitions' => $this->canManage($actor)
                    ? $this->transitions->allowedTransitionValues($row)
                    : [],
                'href' => '/operations/printing?item='.$row->id,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, int>
     */
    private function summaryCounts(User $actor, int $approachingDays): array
    {
        $today = Carbon::today()->toDateString();
        $approachingUntil = Carbon::today()->addDays($approachingDays)->toDateString();
        $open = $this->baseOpenQuery($actor);

        $byStatus = [];
        foreach (PrintingRequestStatus::cases() as $status) {
            $byStatus['status_'.$status->value] = (clone $this->baseVisibleQuery($actor))
                ->where('status', $status->value)
                ->count();
        }

        return array_merge([
            'today' => (clone $open)->whereDate('required_date', $today)->count(),
            'approaching' => (clone $open)
                ->whereDate('required_date', '>', $today)
                ->whereDate('required_date', '<=', $approachingUntil)
                ->count(),
            'overdue' => (clone $open)->whereDate('required_date', '<', $today)->count(),
            'pending_other' => (clone $open)->whereDate('required_date', '>', $approachingUntil)->count(),
            'total_pending' => (clone $open)->count(),
            'total_open' => (clone $open)->count(),
        ], $byStatus);
    }

    /**
     * @return Builder<PrintingRequest>
     */
    private function baseVisibleQuery(User $actor): Builder
    {
        $query = PrintingRequest::query();

        if ($actor->role instanceof UserRole && $actor->role->canReviewPrintingRequests()) {
            return $query;
        }

        return $query->where('user_id', $actor->id);
    }

    /**
     * Open statuses only (excludes completed/cancelled).
     *
     * @return Builder<PrintingRequest>
     */
    private function baseOpenQuery(User $actor): Builder
    {
        return $this->baseVisibleQuery($actor)
            ->whereIn('status', PrintingRequestStatus::openValues());
    }

    /**
     * @param  Builder<PrintingRequest>  $query
     */
    private function applyCategory(
        Builder $query,
        string $category,
        string $today,
        string $approachingUntil,
    ): void {
        match ($category) {
            'today' => $query->whereDate('required_date', $today),
            'approaching' => $query
                ->whereDate('required_date', '>', $today)
                ->whereDate('required_date', '<=', $approachingUntil),
            'overdue' => $query->whereDate('required_date', '<', $today),
            'pending_other' => $query->whereDate('required_date', '>', $approachingUntil),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeItem(User $actor, PrintingRequest $row, int $approachingDays = 2): array
    {
        $row->loadMissing([
            'user:id,name,email',
            'quotedBy:id,name',
            'assignee:id,name,email',
            'assignedDepartment:id,name,slug',
        ]);

        return $this->serialize($row, $actor, Carbon::today()->toDateString(), max(0, $approachingDays));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PrintingRequest $row, User $actor, string $today, int $approachingDays): array
    {
        $required = $row->required_date?->toDateString();
        $category = 'pending_other';
        if ($required !== null) {
            if ($required < $today) {
                $category = 'overdue';
            } elseif ($required === $today) {
                $category = 'today';
            } elseif ($required <= Carbon::parse($today)->addDays($approachingDays)->toDateString()) {
                $category = 'approaching';
            }
        }

        $status = $row->status instanceof PrintingRequestStatus
            ? $row->status
            : PrintingRequestStatus::from((string) $row->status);

        return [
            'id' => $row->id,
            'product_name' => $row->product_name,
            'product_slug' => $row->product_slug,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_changed_at' => $row->status_changed_at?->toIso8601String(),
            'required_date' => $required,
            'category' => $category,
            'quantity' => $row->quantity,
            'pricing_type' => $row->pricing_type?->value ?? $row->pricing_type,
            'assigned_to' => $row->assignee ? [
                'id' => $row->assignee->id,
                'name' => $row->assignee->name,
                'email' => $row->assignee->email,
            ] : null,
            'assigned_department_id' => $row->assigned_department_id,
            'assigned_department' => $row->assignedDepartment ? [
                'id' => $row->assignedDepartment->id,
                'name' => $row->assignedDepartment->name,
                'slug' => $row->assignedDepartment->slug,
            ] : null,
            'allowed_transitions' => $this->canManage($actor)
                ? $this->transitions->allowedTransitionValues($row)
                : [],
            'user' => $row->user ? [
                'id' => $row->user->id,
                'name' => $row->user->name,
                'email' => $row->user->email,
            ] : null,
            'quoted_by' => $row->quotedBy ? [
                'id' => $row->quotedBy->id,
                'name' => $row->quotedBy->name,
            ] : null,
            'delivery_method' => $row->delivery_method,
            'delivery_notes' => $row->delivery_notes,
            'delivered_at' => $row->delivered_at?->toIso8601String(),
            'received_by' => $row->received_by,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }

    private function assertCanList(User $actor): void
    {
        if (! $this->canList($actor)) {
            throw ValidationException::withMessages([
                'printing' => ['You cannot view printing operations.'],
            ]);
        }
    }

    private function canList(User $actor): bool
    {
        if (Gate::forUser($actor)->allows('viewAny', PrintingRequest::class)) {
            return true;
        }

        return $actor->role instanceof UserRole && $actor->role->value === 'CUSTOMER';
    }

    private function canManage(User $actor): bool
    {
        return $actor->role instanceof UserRole && $actor->role->canReviewPrintingRequests();
    }
}
