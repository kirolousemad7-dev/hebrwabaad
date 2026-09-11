<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CalendarNotification;
use App\Notifications\CrmNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterPhase5Test extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_category_filter_returns_only_matching_notifications(): void
    {
        $owner = User::factory()->owner()->create();

        $owner->notify(new CalendarNotification([
            'type' => 'calendar_overdue',
            'title' => 'عنصر متأخر: A',
            'message' => 'متأخر',
            'href' => '/owner/calendar?item=1',
        ]));
        $owner->notify(new CrmNotification([
            'type' => 'crm_lead_assigned',
            'title' => 'Lead assigned',
            'message' => 'CRM',
            'href' => '/crm/leads/1',
            'lead_id' => 999999,
        ]));
        $owner->notify(new CalendarNotification([
            'type' => 'task_assigned',
            'title' => 'مهمة جديدة',
            'message' => 'مهمة',
            'href' => '/workspace/tasks/1',
        ]));

        $this->asUser($owner)
            ->getJson('/api/notifications?category=calendar')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.type', 'calendar_overdue')
            ->assertJsonPath('data.items.0.category', 'calendar');

        $this->asUser($owner)
            ->getJson('/api/notifications?category=tasks')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.type', 'task_assigned')
            ->assertJsonPath('data.items.0.category', 'tasks');
    }

    public function test_grouping_produces_group_for_three_similar_overdue(): void
    {
        $owner = User::factory()->owner()->create();
        $this->travelTo('2026-09-08 12:00:00');

        foreach (['A', 'B', 'C'] as $label) {
            $owner->notify(new CalendarNotification([
                'type' => 'calendar_overdue',
                'title' => 'عنصر متأخر: '.$label,
                'message' => 'ما زال "'.$label.'" متأخراً',
                'href' => '/owner/calendar',
            ]));
            $this->travel(1)->seconds();
        }

        $owner->notify(new CrmNotification([
            'type' => 'crm_quotation_approval_needed',
            'title' => 'Quotation needs approval',
            'message' => 'Needs decision',
            'href' => '/crm/quotations/1',
        ]));

        $response = $this->asUser($owner)
            ->getJson('/api/notifications?grouped=1&per_page=20')
            ->assertOk()
            ->json('data.items');

        $groups = array_values(array_filter($response, fn (array $row): bool => ($row['is_group'] ?? false) === true));
        $this->assertCount(1, $groups);
        $this->assertSame(3, $groups[0]['count']);
        $this->assertSame('calendar', $groups[0]['category']);
        $this->assertCount(3, $groups[0]['items']);

        $approvals = array_values(array_filter(
            $response,
            fn (array $row): bool => ($row['type'] ?? null) === 'crm_quotation_approval_needed'
        ));
        $this->assertCount(1, $approvals);
        $this->assertFalse($approvals[0]['is_group'] ?? true);
    }

    public function test_unread_count_includes_totals_by_category(): void
    {
        $owner = User::factory()->owner()->create();

        $owner->notify(new CalendarNotification([
            'type' => 'calendar_reminder',
            'title' => 'تذكير',
            'message' => 'قريباً',
            'href' => '/owner/calendar',
        ]));
        $owner->notify(new CalendarNotification([
            'type' => 'calendar_overdue',
            'title' => 'متأخر',
            'message' => 'متأخر',
            'href' => '/owner/calendar',
        ]));
        $owner->notify(new CalendarNotification([
            'type' => 'printing_escalation',
            'title' => 'طباعة',
            'message' => 'متأخر',
            'href' => '/operations/printing',
        ]));
        $owner->notify(new CrmNotification([
            'type' => 'crm_lead_assigned',
            'title' => 'Lead',
            'message' => 'Assigned',
            'href' => '/crm/leads/1',
        ]));

        $this->asUser($owner)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.unread_count', 4)
            ->assertJsonPath('data.by_category.calendar', 2)
            ->assertJsonPath('data.by_category.printing', 1)
            ->assertJsonPath('data.by_category.crm', 1);
    }

    public function test_deep_link_falls_back_when_related_entity_missing(): void
    {
        $owner = User::factory()->owner()->create();

        $owner->notify(new CalendarNotification([
            'type' => 'calendar_overdue',
            'title' => 'عنصر متأخر',
            'message' => 'مفقود',
            'href' => '/owner/calendar?item=404',
            'calendar_item_id' => 404404,
        ]));

        $this->asUser($owner)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.items.0.category', 'calendar')
            ->assertJsonPath('data.items.0.href', '/owner/calendar');
    }

    public function test_mark_all_read_still_works(): void
    {
        $owner = User::factory()->owner()->create();
        $owner->notify(new CalendarNotification([
            'type' => 'calendar_reminder',
            'title' => 'تذكير',
            'message' => 'قريباً',
            'href' => '/owner/calendar',
        ]));

        $this->asUser($owner)
            ->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());
    }
}
