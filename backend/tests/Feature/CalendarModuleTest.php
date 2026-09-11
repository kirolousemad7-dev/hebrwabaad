<?php

namespace Tests\Feature;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarModuleTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_owner_can_create_and_list_calendar_items(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->create([
            'role' => UserRole::GraphicDesigner,
            'is_active' => true,
        ]);

        $starts = now()->addDay()->setTime(10, 0)->toIso8601String();

        $create = $this->asUser($owner)->postJson('/api/calendar', [
            'title' => 'اجتماع تصميم',
            'type' => CalendarItemType::Meeting->value,
            'starts_at' => $starts,
            'ends_at' => now()->addDay()->setTime(11, 0)->toIso8601String(),
            'assignee_ids' => [$employee->id],
            'reminders' => ['MINUTES_30'],
            'priority' => 'HIGH',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.title', 'اجتماع تصميم')
            ->assertJsonPath('data.type', 'MEETING');

        $itemId = $create->json('data.id');
        $this->assertNotNull($itemId);

        $list = $this->asUser($owner)->getJson('/api/calendar?from='.now()->toDateString().'&to='.now()->addDays(7)->toDateString().'&scope=team');
        $list->assertOk();
        $titles = collect($list->json('data.items'))->pluck('title');
        $this->assertTrue($titles->contains('اجتماع تصميم'));
    }

    public function test_owner_can_create_task_and_complete_it(): void
    {
        $owner = User::factory()->owner()->create();
        $starts = now()->addHours(2)->toIso8601String();

        $create = $this->asUser($owner)->postJson('/api/calendar', [
            'title' => 'مهمة مراجعة',
            'type' => CalendarItemType::Task->value,
            'starts_at' => $starts,
            'status' => CalendarItemStatus::Scheduled->value,
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->asUser($owner)
            ->postJson('/api/calendar/'.$id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.status', CalendarItemStatus::Completed->value);

        $this->assertNotNull(CalendarItem::query()->find($id)?->completed_at);
    }

    public function test_employee_cannot_see_private_team_only_items(): void
    {
        $owner = User::factory()->owner()->create();
        $employeeA = User::factory()->create(['role' => UserRole::WebDeveloper, 'is_active' => true]);
        $employeeB = User::factory()->create(['role' => UserRole::GraphicDesigner, 'is_active' => true]);

        $item = CalendarItem::factory()->create([
            'title' => 'سري للموظف أ',
            'created_by' => $owner->id,
            'visibility' => 'PRIVATE',
            'starts_at' => now()->addDay(),
        ]);
        $item->assignees()->sync([$employeeA->id]);

        $visible = $this->asUser($employeeA)->getJson(
            '/api/calendar?from='.now()->toDateString().'&to='.now()->addDays(3)->toDateString().'&scope=mine&include_linked=0',
        );
        $visible->assertOk();
        $this->assertTrue(collect($visible->json('data.items'))->contains(fn ($row) => ($row['id'] ?? null) === $item->id));

        $hidden = $this->asUser($employeeB)->getJson(
            '/api/calendar?from='.now()->toDateString().'&to='.now()->addDays(3)->toDateString().'&scope=mine&include_linked=0',
        );
        $hidden->assertOk();
        $this->assertFalse(collect($hidden->json('data.items'))->contains(fn ($row) => ($row['id'] ?? null) === $item->id));
    }

    public function test_customer_cannot_access_calendar(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $this->asUser($customer)
            ->getJson('/api/calendar?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertForbidden();
    }

    public function test_owner_can_update_and_delete_item(): void
    {
        $owner = User::factory()->owner()->create();
        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'قديم',
            'starts_at' => now()->addDays(2),
        ]);
        $item->assignees()->sync([$owner->id]);

        $this->asUser($owner)
            ->putJson('/api/calendar/'.$item->id, [
                'title' => 'محدث',
                'starts_at' => now()->addDays(3)->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'محدث');

        $this->asUser($owner)
            ->deleteJson('/api/calendar/'.$item->id)
            ->assertOk();

        $this->assertSoftDeleted('calendar_items', ['id' => $item->id]);
    }

    public function test_date_range_filter_excludes_outside_items(): void
    {
        $owner = User::factory()->owner()->create();
        CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'داخل النطاق',
            'starts_at' => now()->addDays(2)->setTime(9, 0),
        ])->assignees()->sync([$owner->id]);

        CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'خارج النطاق',
            'starts_at' => now()->addDays(20)->setTime(9, 0),
        ])->assignees()->sync([$owner->id]);

        $response = $this->asUser($owner)->getJson(
            '/api/calendar?from='.now()->toDateString().'&to='.now()->addDays(5)->toDateString().'&scope=team&include_linked=0',
        )->assertOk();

        $titles = collect($response->json('data.items'))->pluck('title');
        $this->assertTrue($titles->contains('داخل النطاق'));
        $this->assertFalse($titles->contains('خارج النطاق'));
    }

    public function test_overdue_status_is_marked_for_past_tasks(): void
    {
        $owner = User::factory()->owner()->create();
        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'starts_at' => now()->subDay(),
            'title' => 'متأخرة',
        ]);
        $item->assignees()->sync([$owner->id]);

        $this->asUser($owner)->getJson(
            '/api/calendar?from='.now()->subDays(3)->toDateString().'&to='.now()->addDay()->toDateString().'&scope=team&include_linked=0',
        )->assertOk();

        $this->assertSame(CalendarItemStatus::Overdue, $item->fresh()->status);
    }
}
