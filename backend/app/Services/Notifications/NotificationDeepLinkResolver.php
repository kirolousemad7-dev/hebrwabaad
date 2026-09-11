<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\Conversation;
use App\Models\CrmLead;
use App\Models\CrmQuotation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSubmission;
use Illuminate\Notifications\DatabaseNotification;

class NotificationDeepLinkResolver
{
    /**
     * Allowed frontend path prefixes used by notification hrefs in this app.
     *
     * @var list<string>
     */
    private const ALLOWED_PREFIXES = [
        '/dashboard',
        '/workspace',
        '/owner',
        '/crm',
        '/supplier',
        '/operations',
        '/printing-requests',
        '/cq',
    ];

    public function __construct(
        private readonly NotificationCategorizer $categorizer,
    ) {}

    public function resolve(DatabaseNotification $notification, ?User $viewer = null): string
    {
        $payload = is_array($notification->data) ? $notification->data : [];
        $category = $this->categorizer->categorize($notification);
        $fallback = $this->moduleHome($category, $viewer);

        $href = is_string($payload['href'] ?? null) ? trim($payload['href']) : '';
        if ($href === '' && is_string($payload['action_url'] ?? null)) {
            $href = trim($payload['action_url']);
        }

        if ($href === '' || ! $this->isAllowedHref($href)) {
            return $fallback;
        }

        if (! $this->relatedEntityExists($payload)) {
            return $fallback;
        }

        return $href;
    }

    public function isAllowedHref(string $href): bool
    {
        if (! str_starts_with($href, '/') || str_starts_with($href, '//')) {
            return false;
        }

        $path = parse_url($href, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return false;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public function moduleHome(NotificationCategory $category, ?User $viewer = null): string
    {
        $role = $viewer?->role instanceof UserRole ? $viewer->role : null;

        return match ($category) {
            NotificationCategory::Tasks => match (true) {
                $role === UserRole::Owner => '/owner/work',
                $role === UserRole::Customer => '/dashboard',
                default => '/workspace',
            },
            NotificationCategory::Calendar => match (true) {
                $role === UserRole::Owner => '/owner/calendar',
                $role instanceof UserRole && $role->canAccessCrm() => '/crm/work-calendar',
                default => '/workspace/calendar',
            },
            NotificationCategory::Projects => match (true) {
                $role === UserRole::Owner => '/owner/work',
                default => '/operations/projects',
            },
            NotificationCategory::Printing => '/operations/printing',
            NotificationCategory::Approvals => match (true) {
                $role === UserRole::Owner => '/owner/approvals',
                default => '/operations/approvals',
            },
            NotificationCategory::Automation => match (true) {
                $role === UserRole::Owner => '/owner/work',
                default => '/workspace',
            },
            NotificationCategory::Crm => '/crm',
            NotificationCategory::Alerts, NotificationCategory::All => match (true) {
                $role === UserRole::Owner => '/owner',
                $role === UserRole::Customer => '/dashboard',
                $role === UserRole::Supplier => '/supplier',
                default => '/workspace',
            },
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function relatedEntityExists(array $payload): bool
    {
        $checks = [
            'calendar_item_id' => CalendarItem::class,
            'lead_id' => CrmLead::class,
            'quotation_id' => CrmQuotation::class,
            'order_id' => Order::class,
            'project_id' => Project::class,
            'payment_id' => Payment::class,
            'conversation_id' => Conversation::class,
            'task_id' => Task::class,
            'work_submission_id' => WorkSubmission::class,
            'supplier_id' => Supplier::class,
        ];

        foreach ($checks as $key => $model) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
                continue;
            }

            $id = (int) $payload[$key];
            if ($id <= 0 || ! $model::query()->whereKey($id)->exists()) {
                return false;
            }
        }

        return true;
    }
}
