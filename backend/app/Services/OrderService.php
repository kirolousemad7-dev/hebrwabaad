<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\CatalogAddon;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Package;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Services\Catalog\CustomPackageOperationsService;
use App\Services\Workflow\WorkflowAutomationEngine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * @return list<string>
     */
    public function eagerLoad(): array
    {
        return ['customer', 'accountManager', 'project', 'service', 'package', 'packageTier', 'items.service', 'items.addons.addon', 'addons.addon', 'statusHistory.changedBy'];
    }

    /**
     * @return Collection<int, Order>
     */
    public function forCustomer(User $customer): Collection
    {
        return Order::query()
            ->where('customer_id', $customer->id)
            ->with([...$this->eagerLoad(), 'latestPayment'])
            ->latest()
            ->get();
    }

    /**
     * Package CTA entry point. The package and its tier are resolved server-side;
     * an equivalent unpaid order is reused instead of creating a duplicate.
     *
     * @return array{order: Order, reused: bool}
     */
    public function createOrReusePackageOrder(User $customer, string $packageSlug, ?string $tierSlug = null): array
    {
        return DB::transaction(function () use ($customer, $packageSlug, $tierSlug): array {
            $package = Package::query()
                ->active()
                ->where('slug', $packageSlug)
                ->lockForUpdate()
                ->first();

            if ($package === null || trim((string) $package->currency) === '') {
                throw ValidationException::withMessages([
                    'package_slug' => ['هذه الباقة غير متاحة حاليًا.'],
                ]);
            }

            $tier = null;

            if ($tierSlug !== null && $tierSlug !== '') {
                $tier = $package->tiers()->active()->where('slug', $tierSlug)->first();

                if ($tier === null) {
                    throw ValidationException::withMessages([
                        'package_tier_slug' => ['هذا المستوى غير متاح حاليًا.'],
                    ]);
                }
            }

            $existing = Order::query()
                ->where('customer_id', $customer->id)
                ->where('package_id', $package->id)
                ->where('package_tier_id', $tier?->id)
                ->where('status', '!=', OrderStatus::Delivered->value)
                ->whereDoesntHave('payments', function (Builder $payments): void {
                    $payments->where('status', PaymentStatus::Paid->value);
                })
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return [
                    'order' => $this->load($existing)->load('latestPayment'),
                    'reused' => true,
                ];
            }

            $manager = User::query()
                ->active()
                ->where('role', UserRole::AccountManager)
                ->orderBy('id')
                ->first();

            if ($manager === null) {
                throw ValidationException::withMessages([
                    'package_slug' => ['تعذر إنشاء الطلب حاليًا. برجاء المحاولة مرة أخرى.'],
                ]);
            }

            $order = Order::query()->create([
                'reference' => 'TMP-'.Str::ulid(),
                'title' => $tier === null ? $package->name : $package->name.' — '.$tier->name,
                'customer_id' => $customer->id,
                'account_manager_id' => $manager->id,
                'package_id' => $package->id,
                'package_tier_id' => $tier?->id,
                'status' => OrderStatus::Received,
            ]);

            $order->update([
                'reference' => sprintf('HEBR-ORD-%06d', $order->id),
            ]);

            $this->recordHistory($order, null, OrderStatus::Received, $customer);

            return [
                'order' => $this->load($order->fresh())->load('latestPayment'),
                'reused' => false,
            ];
        });
    }

    /**
     * Build-your-own multi-service package. Stores normalized order_items (never comma strings).
     * If any line is not FIXED+priced, requires_quote is set and payable stays unavailable.
     *
     * @param  list<array{service_id: int, quantity: int, addon_slugs?: list<string>, notes?: string|null}>  $items
     * @param  list<string>  $packageAddonSlugs
     * @return array{order: Order, reused: bool}
     */
    public function createCustomPackageOrder(User $customer, array $items, array $packageAddonSlugs = []): array
    {
        return DB::transaction(function () use ($customer, $items, $packageAddonSlugs): array {
            if ($items === []) {
                throw ValidationException::withMessages([
                    'items' => ['اختر خدمة واحدة على الأقل.'],
                ]);
            }

            $manager = User::query()
                ->active()
                ->where('role', UserRole::AccountManager)
                ->orderBy('id')
                ->first();

            if ($manager === null) {
                $manager = User::query()
                    ->active()
                    ->where('role', UserRole::Owner)
                    ->orderBy('id')
                    ->first();
            }

            if ($manager === null) {
                throw ValidationException::withMessages([
                    'items' => ['تعذر إنشاء الطلب حاليًا. برجاء المحاولة مرة أخرى.'],
                ]);
            }

            $serviceIds = collect($items)->pluck('service_id')->map(fn ($id) => (int) $id)->unique()->values();
            $services = Service::query()
                ->active()
                ->where('is_public', true)
                ->whereIn('id', $serviceIds)
                ->get()
                ->keyBy('id');

            if ($services->count() !== $serviceIds->count()) {
                throw ValidationException::withMessages([
                    'items' => ['بعض الخدمات المحددة غير متاحة أو غير منشورة.'],
                ]);
            }

            $requiresQuote = false;
            $names = [];

            foreach ($items as $row) {
                $service = $services->get((int) $row['service_id']);
                $names[] = $service->name;
                if (! $service->isChargeable()) {
                    $requiresQuote = true;
                }
            }

            $title = count($names) === 1
                ? 'باقة مخصّصة — '.$names[0]
                : 'باقة مخصّصة ('.count($names).' خدمات)';

            $order = Order::query()->create([
                'reference' => 'TMP-'.Str::ulid(),
                'title' => $title,
                'description' => 'طلب باقة مخصّصة عبر صمّم باقتك.',
                'customer_id' => $customer->id,
                'account_manager_id' => $manager->id,
                'is_custom_package' => true,
                'requires_quote' => $requiresQuote,
                'status' => OrderStatus::Received,
            ]);

            $order->update([
                'reference' => sprintf('HEBR-ORD-%06d', $order->id),
            ]);

            $sort = 0;
            foreach ($items as $row) {
                $service = $services->get((int) $row['service_id']);
                $quantity = max(1, (int) ($row['quantity'] ?? 1));
                $mode = $service->pricingMode();
                $unitPrice = $service->isChargeable() ? $service->base_price : null;

                $item = $order->items()->create([
                    'service_id' => $service->id,
                    'quantity' => $quantity,
                    'pricing_mode' => $mode->value,
                    'unit_price' => $unitPrice,
                    'currency' => $service->currency ?: 'SAR',
                    'notes' => $row['notes'] ?? null,
                    'sort_order' => $sort++,
                ]);

                $addonSlugs = array_values(array_filter($row['addon_slugs'] ?? [], fn ($slug) => is_string($slug) && $slug !== ''));
                if ($addonSlugs !== []) {
                    $addons = CatalogAddon::query()
                        ->active()
                        ->public()
                        ->whereIn('slug', $addonSlugs)
                        ->with('services')
                        ->get();

                    foreach ($addons as $addon) {
                        if (! $addon->isAvailable()) {
                            continue;
                        }

                        $eligible = $addon->services->isEmpty()
                            || $addon->services->contains(fn (Service $s) => $s->id === $service->id);

                        if (! $eligible) {
                            throw ValidationException::withMessages([
                                'items' => ["الإضافة «{$addon->name}» غير متوافقة مع «{$service->name}»."],
                            ]);
                        }

                        if (! $addon->isChargeable()) {
                            $requiresQuote = true;
                        }

                        $item->addons()->create([
                            'catalog_addon_id' => $addon->id,
                            'quantity' => 1,
                        ]);
                    }
                }
            }

            if ($packageAddonSlugs !== []) {
                $packageAddons = CatalogAddon::query()
                    ->active()
                    ->public()
                    ->whereIn('slug', $packageAddonSlugs)
                    ->get();

                foreach ($packageAddons as $addon) {
                    if (! $addon->isAvailable()) {
                        continue;
                    }

                    if (! $addon->isChargeable()) {
                        $requiresQuote = true;
                    }

                    $order->addons()->create([
                        'catalog_addon_id' => $addon->id,
                        'quantity' => 1,
                    ]);
                }
            }

            if ($requiresQuote) {
                $order->update(['requires_quote' => true]);
            }

            $this->recordHistory($order, null, OrderStatus::Received, $customer);

            return [
                'order' => $this->load($order->fresh())->load('latestPayment'),
                'reused' => false,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateFor(User $user, array $filters): LengthAwarePaginator
    {
        $query = Order::query()->with($this->eagerLoad());
        $this->scopeVisibleTo($query, $user);
        $this->applyFilters($query, $filters);

        return $query->paginate($this->perPage($filters));
    }

    public function load(Order $order): Order
    {
        return $order->load($this->eagerLoad());
    }

    /**
     * Customer / project / manager options for authorized internal creation.
     *
     * @return array{customers: list<array{id: int, name: string, email: string}>, projects: list<array{id: int, title: string, customer_id: int}>, account_managers: list<array{id: int, name: string}>}
     */
    public function lookups(User $user): array
    {
        $customers = User::query()
            ->active()
            ->where('role', UserRole::Customer)
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
            ])
            ->values()
            ->all();

        $projectsQuery = Project::query()->orderBy('title');
        if ($user->role === UserRole::AccountManager) {
            $projectsQuery->where('account_manager_id', $user->id);
        }

        $projects = $projectsQuery
            ->limit(100)
            ->get(['id', 'title', 'customer_id'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'title' => $project->title,
                'customer_id' => $project->customer_id,
            ])
            ->values()
            ->all();

        $managers = [];
        if ($user->role === UserRole::Owner) {
            $managers = User::query()
                ->active()
                ->where('role', UserRole::AccountManager)
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name'])
                ->map(fn (User $manager): array => [
                    'id' => $manager->id,
                    'name' => $manager->name,
                ])
                ->values()
                ->all();
        }

        return [
            'customers' => $customers,
            'projects' => $projects,
            'account_managers' => $managers,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $actor, array $attributes): Order
    {
        $customer = $this->assertCustomer((int) $attributes['customer_id']);
        $manager = $this->resolveAccountManager($actor, $attributes);
        $project = $this->assertProject($attributes['project_id'] ?? null, $customer, $manager);
        $service = $this->assertService($attributes['service_id'] ?? null);
        $package = $this->assertPackage($attributes['package_id'] ?? null);

        $order = Order::query()->create([
            'reference' => 'TMP-'.Str::ulid(),
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
            'project_id' => $project?->id,
            'service_id' => $service?->id,
            'package_id' => $package?->id,
            'status' => OrderStatus::Received,
        ]);

        $order->update([
            'reference' => sprintf('HEBR-ORD-%06d', $order->id),
        ]);

        $this->recordHistory($order, null, OrderStatus::Received, $actor);

        try {
            app(WorkflowAutomationEngine::class)->dispatch('order.created', [
                'source_type' => 'order',
                'source_id' => $order->id,
                'actor_id' => $actor->id,
                'title' => 'متابعة طلب: '.$order->title,
                'description' => $order->description,
                'related_type' => 'order',
                'related_id' => $order->id,
                'assignee_ids' => [$manager->id],
                'payload' => [
                    'order_id' => $order->id,
                    'reference' => $order->reference,
                    'status' => OrderStatus::Received->value,
                ],
            ]);
        } catch (\Throwable) {
            // Workflow hooks must never break order creation.
        }

        return $this->load($order->fresh());
    }

    public function transition(User $actor, Order $order, OrderStatus $next): Order
    {
        $current = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::from((string) $order->status);

        if (! $current->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن نقل هذا الطلب إلى المرحلة المطلوبة.'],
            ]);
        }

        $attributes = ['status' => $next];

        if ($next === OrderStatus::Confirmed && $order->confirmed_at === null) {
            $attributes['confirmed_at'] = now();
        }

        if ($next === OrderStatus::Completed && $order->completed_at === null) {
            $attributes['completed_at'] = now();
        }

        if ($next === OrderStatus::Delivered && $order->delivered_at === null) {
            $attributes['delivered_at'] = now();
        }

        $order->update($attributes);
        $this->recordHistory($order, $current, $next, $actor);
        app(PlatformNotifier::class)->orderStatusUpdated($order->fresh(['customer']), $next);

        if ($next === OrderStatus::Confirmed) {
            try {
                app(CustomPackageOperationsService::class)
                    ->ensureOperationalWork($order->fresh(['items.service.department', 'customer', 'accountManager', 'project']));
            } catch (\Throwable) {
                // Operational fan-out must never break order transitions.
            }
        }

        try {
            app(WorkflowAutomationEngine::class)->dispatch('order.status_changed', [
                'source_type' => 'order',
                'source_id' => $order->id,
                'actor_id' => $actor->id,
                'title' => 'تحديث حالة طلب: '.$order->title,
                'related_type' => 'order',
                'related_id' => $order->id,
                'assignee_ids' => array_filter([$order->account_manager_id]),
                'old_status' => $current->value,
                'new_status' => $next->value,
                'order_id' => $order->id,
                'payload' => [
                    'order_id' => $order->id,
                    'old_status' => $current->value,
                    'new_status' => $next->value,
                    'actor_id' => $actor->id,
                ],
            ], $current->value.'->'.$next->value);
        } catch (\Throwable) {
            // Workflow hooks must never break order transitions.
        }

        return $this->load($order->fresh());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function timeline(Order $order): array
    {
        $history = $order->relationLoaded('statusHistory')
            ? $order->statusHistory
            : $order->statusHistory()->get();

        $occurred = [];
        foreach ($history as $item) {
            $key = $item->to_status instanceof OrderStatus
                ? $item->to_status->value
                : (string) $item->to_status;
            $occurred[$key] = $item->created_at?->toIso8601String();
        }

        $current = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::from((string) $order->status);

        $currentIndex = array_search($current, OrderStatus::lifecycle(), true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        $steps = [];
        foreach (OrderStatus::lifecycle() as $index => $status) {
            $state = 'pending';
            if ($status === $current) {
                $state = 'current';
            } elseif ($index < $currentIndex) {
                $state = 'completed';
            }

            $steps[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'state' => $state,
                'occurred_at' => $occurred[$status->value] ?? null,
            ];
        }

        return $steps;
    }

    private function recordHistory(Order $order, ?OrderStatus $from, OrderStatus $to, User $actor): void
    {
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'changed_by' => $actor->id,
        ]);
    }

    /**
     * @param  Builder<Order>  $query
     */
    private function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role === UserRole::Owner) {
            return;
        }

        $query->where('account_manager_id', $user->id);
    }

    /**
     * @param  Builder<Order>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('reference', 'like', $term)
                    ->orWhere('title', 'like', $term);
            });
        }

        $status = $filters['status'] ?? null;
        if (is_string($status) && in_array($status, array_column(OrderStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $query->latest();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return max(1, min($perPage, 50));
    }

    private function assertCustomer(int $userId): User
    {
        $customer = User::query()->find($userId);

        if ($customer === null || $customer->role !== UserRole::Customer) {
            throw ValidationException::withMessages([
                'customer_id' => ['العميل المحدد غير صالح.'],
            ]);
        }

        if (! $customer->is_active) {
            throw ValidationException::withMessages([
                'customer_id' => ['لا يمكن ربط عميل معطّل.'],
            ]);
        }

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveAccountManager(User $actor, array $attributes): User
    {
        if ($actor->role === UserRole::AccountManager) {
            return $actor;
        }

        $managerId = (int) ($attributes['account_manager_id'] ?? 0);
        $manager = User::query()->find($managerId);

        if ($manager === null || $manager->role !== UserRole::AccountManager || ! $manager->is_active) {
            throw ValidationException::withMessages([
                'account_manager_id' => ['مدير الحساب المحدد غير صالح.'],
            ]);
        }

        return $manager;
    }

    private function assertProject(mixed $projectId, User $customer, User $manager): ?Project
    {
        if ($projectId === null || $projectId === '') {
            return null;
        }

        $project = Project::query()->find((int) $projectId);

        if ($project === null || $project->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'project_id' => ['المشروع المحدد لا ينتمي إلى هذا العميل.'],
            ]);
        }

        if ($manager->role === UserRole::AccountManager && $project->account_manager_id !== $manager->id) {
            throw ValidationException::withMessages([
                'project_id' => ['لا يمكنك ربط مشروع لا تديره.'],
            ]);
        }

        return $project;
    }

    private function assertService(mixed $serviceId): ?Service
    {
        if ($serviceId === null || $serviceId === '') {
            return null;
        }

        $service = Service::query()->find((int) $serviceId);

        if ($service === null) {
            throw ValidationException::withMessages([
                'service_id' => ['الخدمة المحددة غير صالحة.'],
            ]);
        }

        return $service;
    }

    private function assertPackage(mixed $packageId): ?Package
    {
        if ($packageId === null || $packageId === '') {
            return null;
        }

        $package = Package::query()->find((int) $packageId);

        if ($package === null) {
            throw ValidationException::withMessages([
                'package_id' => ['الباقة المحددة غير صالحة.'],
            ]);
        }

        return $package;
    }
}
