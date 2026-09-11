<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class NotificationCenterService
{
    public function __construct(
        private readonly NotificationCategorizer $categorizer,
        private readonly NotificationDeepLinkResolver $deepLinks,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{items: list<array<string, mixed>>, unread_count: int, meta: array<string, int>}
     */
    public function inbox(User $user, array $filters, ?Request $request = null): array
    {
        $category = $this->resolveCategoryFilter($filters);
        $grouped = $this->wantsGrouped($filters);
        $hidden = $this->hiddenCategoriesFor($user);

        $page = $this->paginateVisible($user, $filters, $category, $hidden);
        $request ??= Request::create('/');

        $serialized = [];
        foreach ($page->items() as $notification) {
            /** @var DatabaseNotification $notification */
            $serialized[] = $this->serializeItem($notification, $user, $request);
        }

        return [
            'items' => $grouped ? $this->groupItems($serialized) : $serialized,
            'unread_count' => $this->unreadCount($user)['total'],
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }

    /**
     * @return array{total: int, unread_count: int, by_category: array<string, int>}
     */
    public function unreadCount(User $user): array
    {
        $hidden = $this->hiddenCategoriesFor($user);
        $byCategory = array_fill_keys(NotificationCategory::filterableValues(), 0);

        $rows = $user->unreadNotifications()
            ->orderByDesc('created_at')
            ->limit(500)
            ->get(['id', 'data']);

        $total = 0;

        foreach ($rows as $notification) {
            $category = $this->categorizer->categorizeValue($notification);

            if (in_array($category, $hidden, true)) {
                continue;
            }

            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
            $total++;
        }

        return [
            'total' => $total,
            'unread_count' => $total,
            'by_category' => $byCategory,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $hidden
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    private function paginateVisible(
        User $user,
        array $filters,
        ?NotificationCategory $category,
        array $hidden,
    ): LengthAwarePaginator {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 50));

        $query = $user->notifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($category === null && $hidden === []) {
            return $query->paginate($perPage);
        }

        $window = $query->limit(max($perPage * 10, 100))->get();
        $filtered = $window->filter(function (DatabaseNotification $notification) use ($category, $hidden): bool {
            $value = $this->categorizer->categorizeValue($notification);

            if (in_array($value, $hidden, true)) {
                return false;
            }

            if ($category !== null && $value !== $category->value) {
                return false;
            }

            return true;
        })->values();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $slice = $filtered->forPage($page, $perPage)->values();

        return new Paginator(
            $slice->all(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(DatabaseNotification $notification, User $user, Request $request): array
    {
        $base = NotificationResource::make($notification)->resolve($request);
        $category = $this->categorizer->categorizeValue($notification);
        $href = $this->deepLinks->resolve($notification, $user);

        return array_merge($base, [
            'is_group' => false,
            'category' => $category,
            'href' => $href,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function groupItems(array $items): array
    {
        /** @var array<string, list<array<string, mixed>>> $groups */
        $groups = [];
        $order = [];

        foreach ($items as $item) {
            $type = is_string($item['type'] ?? null) ? $item['type'] : 'unknown';
            $category = is_string($item['category'] ?? null) ? $item['category'] : NotificationCategory::Alerts->value;
            $createdAt = is_string($item['created_at'] ?? null) ? $item['created_at'] : null;
            $day = $createdAt !== null ? substr($createdAt, 0, 10) : 'unknown';

            if ($this->categorizer->mustStayIndividual($type) || $category === NotificationCategory::Approvals->value) {
                $soloKey = 'solo:'.(string) ($item['id'] ?? uniqid('n', true));
                $groups[$soloKey] = [$item];
                $order[] = $soloKey;

                continue;
            }

            $key = $type.'|'.$day;
            if (! isset($groups[$key])) {
                $groups[$key] = [];
                $order[] = $key;
            }

            $groups[$key][] = $item;
        }

        $result = [];
        foreach ($order as $key) {
            $bucket = $groups[$key];
            if (count($bucket) < 2) {
                $result[] = $bucket[0];

                continue;
            }

            $latest = $bucket[0];
            foreach ($bucket as $row) {
                $at = is_string($row['created_at'] ?? null) ? $row['created_at'] : '';
                $latestAt = is_string($latest['created_at'] ?? null) ? $latest['created_at'] : '';
                if ($at > $latestAt) {
                    $latest = $row;
                }
            }

            $category = is_string($latest['category'] ?? null) ? $latest['category'] : NotificationCategory::Alerts->value;
            $seedTitle = is_string($latest['title'] ?? null) ? $latest['title'] : '';

            $result[] = [
                'is_group' => true,
                'key' => $key,
                'category' => $category,
                'title' => $this->groupTitle($seedTitle, count($bucket), $category),
                'count' => count($bucket),
                'latest_at' => is_string($latest['created_at'] ?? null) ? $latest['created_at'] : null,
                'items' => $bucket,
                'href' => is_string($latest['href'] ?? null) ? $latest['href'] : null,
            ];
        }

        return $result;
    }

    private function groupTitle(string $seedTitle, int $count, string $category): string
    {
        $label = match ($category) {
            NotificationCategory::Calendar->value => 'تحديثات تقويم',
            NotificationCategory::Tasks->value => 'تحديثات مهام',
            NotificationCategory::Printing->value => 'تنبيهات طباعة',
            NotificationCategory::Projects->value => 'تنبيهات مشاريع',
            NotificationCategory::Crm->value => 'تنبيهات CRM',
            NotificationCategory::Automation->value => 'تنبيهات أتمتة',
            default => 'إشعارات',
        };

        if ($seedTitle !== '') {
            return $label.' ('.$count.') — '.$seedTitle;
        }

        return $label.' ('.$count.')';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveCategoryFilter(array $filters): ?NotificationCategory
    {
        $raw = $filters['category'] ?? null;
        if (! is_string($raw) || $raw === '' || $raw === NotificationCategory::All->value) {
            return null;
        }

        return NotificationCategory::tryFrom($raw);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function wantsGrouped(array $filters): bool
    {
        $raw = $filters['grouped'] ?? null;

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            return in_array(strtolower($raw), ['1', 'true', 'yes'], true);
        }

        return (int) $raw === 1;
    }

    /**
     * @return list<string>
     */
    private function hiddenCategoriesFor(User $user): array
    {
        $prefs = UserNotificationPreference::query()->where('user_id', $user->id)->first();
        if ($prefs === null) {
            return [];
        }

        $hidden = [];

        if ($prefs->printing_alerts === false) {
            $hidden[] = NotificationCategory::Printing->value;
        }

        if ($prefs->crm_alerts === false) {
            $hidden[] = NotificationCategory::Crm->value;
        }

        return $hidden;
    }
}
