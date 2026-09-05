<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\PrintingPricingType;
use App\Enums\PrintingRequestStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\ConsultationLead;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PrintingRequest;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OwnerDashboardService
{
    private const ACTIVITY_LIMIT = 12;

    private const PENDING_LIMIT = 8;

    private const ACTIVITY_WINDOW_DAYS = 14;

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $now = Carbon::now($timezone);
        $monthStart = $now->copy()->startOfMonth();
        $activityFrom = $now->copy()->subDays(self::ACTIVITY_WINDOW_DAYS - 1)->startOfDay();

        return [
            'overview' => [
                'revenue' => $this->revenueMetric(),
                'orders' => $this->availableMetric(
                    Order::query()->count(),
                    [
                        'in_progress' => Order::query()->where('status', 'IN_PROGRESS')->count(),
                        'delivered' => Order::query()->where('status', 'DELIVERED')->count(),
                    ],
                ),
                'customers' => $this->availableMetric(
                    User::query()->where('role', UserRole::Customer)->count(),
                    [
                        'this_month' => User::query()
                            ->where('role', UserRole::Customer)
                            ->where('created_at', '>=', $monthStart)
                            ->count(),
                    ],
                ),
                'projects' => $this->availableMetric(
                    Project::query()->count(),
                    [
                        'in_progress' => Project::query()
                            ->where('status', ProjectStatus::InProgress)
                            ->count(),
                    ],
                ),
                'employees' => $this->availableMetric(
                    User::query()->employees()->active()->count(),
                    ['by_role' => $this->employeeCountsByRole()],
                ),
                'suppliers' => $this->availableMetric(
                    Supplier::query()->count(),
                    ['active' => Supplier::query()->active()->count()],
                ),
                'leads' => $this->availableMetric(
                    ConsultationLead::query()->count(),
                    [
                        'this_month' => ConsultationLead::query()
                            ->where('created_at', '>=', $monthStart)
                            ->count(),
                    ],
                ),
                'pending_requests' => $this->availableMetric(
                    PrintingRequest::query()->needsOwnerAttention()->count(),
                    [
                        'awaiting_pricing' => PrintingRequest::query()->whereNull('pricing_type')->count(),
                        'quote_required' => PrintingRequest::query()
                            ->where('pricing_type', PrintingPricingType::QuoteRequired)
                            ->count(),
                    ],
                ),
            ],
            'request_activity' => $this->requestActivitySeries($activityFrom, $now),
            'pricing_breakdown' => $this->pricingBreakdown(),
            'recent_activity' => $this->recentActivity(),
            'pending_requests' => $this->pendingRequests(),
        ];
    }

    /**
     * @param  array<string, mixed>  $secondary
     * @return array<string, mixed>
     */
    private function availableMetric(int|float $value, array $secondary = []): array
    {
        return [
            'available' => true,
            'value' => $value,
            'secondary' => $secondary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revenueMetric(): array
    {
        $paid = Payment::query()->where('status', PaymentStatus::Paid);
        $count = (clone $paid)->count();

        if ($count === 0) {
            return $this->unavailableMetric('no_recorded_revenue');
        }

        $amount = (clone $paid)->sum('amount');
        $currency = (string) ((clone $paid)->orderBy('id')->value('currency') ?: 'SAR');

        return $this->availableMetric(round((float) $amount, 2), [
            'paid_count' => $count,
            'currency' => $currency,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableMetric(string $reason): array
    {
        return [
            'available' => false,
            'value' => null,
            'reason' => $reason,
            'secondary' => [],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function employeeCountsByRole(): array
    {
        $counts = User::query()
            ->toBase()
            ->whereIn('role', UserRole::employeeValues())
            ->where('is_active', true)
            ->select('role', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('role')
            ->pluck('aggregate', 'role');

        $result = [];

        foreach (UserRole::employeeValues() as $role) {
            $result[$role] = (int) ($counts[$role] ?? 0);
        }

        return $result;
    }

    /**
     * @return array<int, array{date: string, count: int}>
     */
    private function requestActivitySeries(Carbon $from, Carbon $until): array
    {
        $driver = DB::connection()->getDriverName();
        $dateExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', created_at)"
            : 'DATE(created_at)';

        $counts = PrintingRequest::query()
            ->toBase()
            ->where('created_at', '>=', $from)
            ->selectRaw($dateExpression.' as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $series = [];
        $cursor = $from->copy()->startOfDay();
        $end = $until->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $day = $cursor->toDateString();
            $series[] = [
                'date' => $day,
                'count' => (int) ($counts[$day] ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * @return array<string, int>
     */
    private function pricingBreakdown(): array
    {
        return [
            'unpriced' => PrintingRequest::query()->whereNull('pricing_type')->count(),
            'estimated' => PrintingRequest::query()
                ->where('pricing_type', PrintingPricingType::Estimated)
                ->count(),
            'quote_required' => PrintingRequest::query()
                ->where('pricing_type', PrintingPricingType::QuoteRequired)
                ->count(),
            'quote_ready' => PrintingRequest::query()
                ->where('pricing_type', PrintingPricingType::QuoteReady)
                ->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(): array
    {
        $items = collect()
            ->merge($this->printingRequestSubmittedActivity()->take(4))
            ->merge($this->printingQuoteActivity()->take(3))
            ->merge($this->customerActivity()->take(3))
            ->merge($this->supplierActivity()->take(3))
            ->sortByDesc(fn (array $item) => $item['occurred_at'])
            ->values()
            ->take(self::ACTIVITY_LIMIT);

        return $items->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function printingRequestSubmittedActivity(): Collection
    {
        return PrintingRequest::query()
            ->with('user')
            ->latest()
            ->limit(self::ACTIVITY_LIMIT)
            ->get()
            ->map(fn (PrintingRequest $request) => [
                'id' => 'printing_request_submitted:'.$request->id,
                'type' => 'printing_request_submitted',
                'title' => $request->product_name,
                'actor' => $this->actorPayload($request->user),
                'entity' => [
                    'type' => 'printing_request',
                    'id' => $request->id,
                    'label' => '#'.$request->id,
                    'href' => '/printing-requests/'.$request->id,
                ],
                'status' => $request->status instanceof PrintingRequestStatus
                    ? $request->status->value
                    : (string) $request->status,
                'occurred_at' => $request->created_at?->toIso8601String(),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function printingQuoteActivity(): Collection
    {
        return PrintingRequest::query()
            ->with(['user', 'quotedBy'])
            ->whereNotNull('quoted_at')
            ->latest('quoted_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get()
            ->map(fn (PrintingRequest $request) => [
                'id' => 'printing_quote_ready:'.$request->id,
                'type' => 'printing_quote_ready',
                'title' => $request->product_name,
                'actor' => $this->actorPayload($request->quotedBy),
                'entity' => [
                    'type' => 'printing_request',
                    'id' => $request->id,
                    'label' => '#'.$request->id,
                    'href' => '/printing-requests/'.$request->id,
                ],
                'status' => PrintingPricingType::QuoteReady->value,
                'occurred_at' => $request->quoted_at?->toIso8601String(),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function customerActivity(): Collection
    {
        return User::query()
            ->where('role', UserRole::Customer)
            ->latest()
            ->limit(self::ACTIVITY_LIMIT)
            ->get()
            ->map(fn (User $user) => [
                'id' => 'customer_registered:'.$user->id,
                'type' => 'customer_registered',
                'title' => $user->name,
                'actor' => $this->actorPayload($user),
                'entity' => [
                    'type' => 'customer',
                    'id' => $user->id,
                    'label' => $user->email,
                    'href' => null,
                ],
                'status' => 'CUSTOMER',
                'occurred_at' => $user->created_at?->toIso8601String(),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function supplierActivity(): Collection
    {
        return Supplier::query()
            ->latest()
            ->limit(self::ACTIVITY_LIMIT)
            ->get()
            ->map(fn (Supplier $supplier) => [
                'id' => 'supplier_added:'.$supplier->id,
                'type' => 'supplier_added',
                'title' => $supplier->name,
                'actor' => null,
                'entity' => [
                    'type' => 'supplier',
                    'id' => $supplier->id,
                    'label' => $supplier->slug,
                    'href' => '/suppliers/'.$supplier->slug,
                ],
                'status' => $supplier->is_active ? 'ACTIVE' : 'INACTIVE',
                'occurred_at' => $supplier->created_at?->toIso8601String(),
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pendingRequests(): array
    {
        return PrintingRequest::query()
            ->needsOwnerAttention()
            ->with('user')
            ->latest()
            ->limit(self::PENDING_LIMIT)
            ->get()
            ->map(fn (PrintingRequest $request) => [
                'id' => $request->id,
                'product_name' => $request->product_name,
                'status' => $request->status instanceof PrintingRequestStatus
                    ? $request->status->value
                    : (string) $request->status,
                'pricing_type' => $request->pricing_type instanceof PrintingPricingType
                    ? $request->pricing_type->value
                    : $request->pricing_type,
                'created_at' => $request->created_at?->toIso8601String(),
                'href' => '/printing-requests/'.$request->id,
                'customer' => $request->user === null ? null : [
                    'id' => $request->user->id,
                    'name' => $request->user->name,
                    'email' => $request->user->email,
                ],
            ])
            ->all();
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function actorPayload(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
}
