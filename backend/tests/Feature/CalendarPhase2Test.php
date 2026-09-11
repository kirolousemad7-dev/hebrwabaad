<?php

namespace Tests\Feature;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\PrintingRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarPhase2Test extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_recurrence_weekly_expands_in_range(): void
    {
        $owner = User::factory()->owner()->create();
        $start = now()->startOfWeek()->setTime(10, 0);

        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'اجتماع أسبوعي',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'recurrence_rule' => 'FREQ=WEEKLY;INTERVAL=1',
        ]);
        $item->assignees()->sync([$owner->id]);

        $response = $this->asUser($owner)->getJson(
            '/api/calendar?from='.$start->toDateString().'&to='.$start->copy()->addWeeks(3)->toDateString().'&scope=team&include_linked=0',
        )->assertOk();

        $occurrences = collect($response->json('data.items'))
            ->filter(fn ($row) => ($row['title'] ?? null) === 'اجتماع أسبوعي');

        $this->assertGreaterThanOrEqual(3, $occurrences->count());
        $this->assertTrue($occurrences->contains(fn ($row) => ($row['is_occurrence'] ?? false) === true));
    }

    public function test_recurrence_until_stops(): void
    {
        $owner = User::factory()->owner()->create();
        $start = now()->startOfDay()->setTime(9, 0);
        $until = $start->copy()->addDays(2)->endOfDay();

        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'يومي محدود',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'recurrence_rule' => 'FREQ=DAILY;INTERVAL=1',
            'recurrence_until' => $until,
        ]);
        $item->assignees()->sync([$owner->id]);

        $response = $this->asUser($owner)->getJson(
            '/api/calendar?from='.$start->toDateString().'&to='.$start->copy()->addDays(10)->toDateString().'&scope=team&include_linked=0',
        )->assertOk();

        $dates = collect($response->json('data.items'))
            ->filter(fn ($row) => ($row['title'] ?? null) === 'يومي محدود')
            ->map(fn ($row) => substr((string) $row['starts_at'], 0, 10))
            ->values();

        $this->assertTrue($dates->contains($start->toDateString()));
        $this->assertFalse($dates->contains($start->copy()->addDays(3)->toDateString()));
    }

    public function test_edit_this_only_creates_exception(): void
    {
        $owner = User::factory()->owner()->create();
        $start = now()->addDay()->setTime(11, 0);

        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'سلسلة',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'recurrence_rule' => 'FREQ=WEEKLY;INTERVAL=1',
        ]);
        $item->assignees()->sync([$owner->id]);

        $this->asUser($owner)->putJson('/api/calendar/'.$item->id, [
            'title' => 'استثناء فقط',
            'scope' => 'this',
            'occurrence_at' => $start->toIso8601String(),
            'starts_at' => $start->copy()->addHours(2)->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.title', 'استثناء فقط');

        $item->refresh();
        $this->assertContains($start->toDateString(), $item->recurrence_exceptions ?? []);
        $this->assertDatabaseHas('calendar_items', [
            'title' => 'استثناء فقط',
            'recurrence_parent_id' => $item->id,
        ]);
    }

    public function test_delete_series_soft_deletes_parent(): void
    {
        $owner = User::factory()->owner()->create();
        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'للحذف',
            'starts_at' => now()->addDay(),
            'recurrence_rule' => 'FREQ=DAILY',
        ]);
        $item->assignees()->sync([$owner->id]);

        $this->asUser($owner)
            ->deleteJson('/api/calendar/'.$item->id, ['scope' => 'all'])
            ->assertOk();

        $this->assertSoftDeleted('calendar_items', ['id' => $item->id]);
    }

    public function test_comment_create_and_delete_own(): void
    {
        $owner = User::factory()->owner()->create();
        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDay(),
        ]);
        $item->assignees()->sync([$owner->id]);

        $create = $this->asUser($owner)->postJson('/api/calendar/'.$item->id.'/comments', [
            'body' => 'تعليق تجريبي',
        ])->assertCreated();

        $commentId = $create->json('data.id');
        $this->assertNotNull($commentId);

        $this->asUser($owner)
            ->deleteJson('/api/calendar/comments/'.$commentId)
            ->assertOk();

        $this->assertSoftDeleted('calendar_item_comments', ['id' => $commentId]);
    }

    public function test_checklist_update(): void
    {
        $owner = User::factory()->owner()->create();
        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDay(),
        ]);
        $item->assignees()->sync([$owner->id]);

        $this->asUser($owner)->putJson('/api/calendar/'.$item->id.'/checklist', [
            'checklist' => [
                ['id' => '1', 'text' => 'خطوة 1', 'done' => false],
                ['id' => '2', 'text' => 'خطوة 2', 'done' => true],
            ],
        ])->assertOk()
            ->assertJsonPath('data.checklist.0.text', 'خطوة 1')
            ->assertJsonPath('data.checklist.1.done', true);
    }

    public function test_derived_project_deadline_appears_for_owner(): void
    {
        $owner = User::factory()->owner()->create();
        $deadline = now()->addDays(2)->toDateString();

        $project = Project::factory()->create([
            'title' => 'مشروع تقويم',
            'deadline' => $deadline,
            'started_at' => now()->subDay()->toDateString(),
        ]);

        $response = $this->asUser($owner)->getJson(
            '/api/calendar?from='.now()->toDateString().'&to='.now()->addDays(5)->toDateString().'&scope=team&include_linked=1',
        )->assertOk();

        $this->assertTrue(
            collect($response->json('data.items'))->contains(
                fn ($row) => ($row['id'] ?? null) === 'derived-project-deadline-'.$project->id
            )
        );
    }

    public function test_printing_required_date_appears(): void
    {
        $owner = User::factory()->owner()->create();
        $required = now()->addDays(3)->toDateString();

        $printing = PrintingRequest::factory()->create([
            'user_id' => $owner->id,
            'required_date' => $required,
            'product_name' => 'كروت',
        ]);

        $response = $this->asUser($owner)->getJson(
            '/api/calendar?from='.now()->toDateString().'&to='.now()->addDays(7)->toDateString().'&scope=team&include_linked=1',
        )->assertOk();

        $this->assertTrue(
            collect($response->json('data.items'))->contains(
                fn ($row) => ($row['id'] ?? null) === 'derived-printing-required-'.$printing->id
            )
        );
    }

    public function test_employee_cannot_see_unauthorized_private_item(): void
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

        $hidden = $this->asUser($employeeB)->getJson(
            '/api/calendar?from='.now()->toDateString().'&to='.now()->addDays(3)->toDateString().'&scope=mine&include_linked=0',
        )->assertOk();

        $this->assertFalse(collect($hidden->json('data.items'))->contains(fn ($row) => ($row['id'] ?? null) === $item->id));
    }

    public function test_reschedule_updates_starts_at(): void
    {
        $owner = User::factory()->owner()->create();
        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'ends_at' => now()->addDays(2)->setTime(11, 0),
        ]);
        $item->assignees()->sync([$owner->id]);

        $newStart = now()->addDays(4)->setTime(14, 0)->toIso8601String();
        $newEnd = now()->addDays(4)->setTime(15, 0)->toIso8601String();

        $this->asUser($owner)->postJson('/api/calendar/'.$item->id.'/reschedule', [
            'starts_at' => $newStart,
            'ends_at' => $newEnd,
        ])->assertOk();

        $item->refresh();
        $this->assertSame(
            now()->addDays(4)->setTime(14, 0)->format('Y-m-d H:i'),
            $item->starts_at->format('Y-m-d H:i'),
        );
    }

    public function test_conflict_detection_returns_overlap(): void
    {
        $owner = User::factory()->owner()->create();
        $starts = now()->addDay()->setTime(10, 0);
        $ends = $starts->copy()->addHour();

        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'متعارض',
            'starts_at' => $starts,
            'ends_at' => $ends,
        ]);
        $item->assignees()->sync([$owner->id]);

        $response = $this->asUser($owner)->postJson('/api/calendar/conflicts', [
            'starts_at' => $starts->copy()->addMinutes(30)->toIso8601String(),
            'ends_at' => $ends->copy()->addHour()->toIso8601String(),
            'assignee_ids' => [$owner->id],
        ])->assertOk();

        $this->assertTrue(
            collect($response->json('data.items'))->contains(fn ($row) => ($row['id'] ?? null) === $item->id)
        );
    }

    public function test_workload_counts(): void
    {
        $owner = User::factory()->owner()->create();
        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'type' => CalendarItemType::Task,
            'starts_at' => now()->addDay()->setTime(9, 0),
        ]);
        $item->assignees()->sync([$owner->id]);

        $response = $this->asUser($owner)->getJson(
            '/api/calendar/workload?from='.now()->toDateString().'&to='.now()->addDays(3)->toDateString(),
        )->assertOk();

        $this->assertGreaterThanOrEqual(1, (int) $response->json('data.totals.items'));
        $this->assertGreaterThanOrEqual(1, (int) $response->json('data.totals.tasks'));
    }

    public function test_ics_export_200_for_owner(): void
    {
        $owner = User::factory()->owner()->create();
        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'ICS Event',
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
        ]);
        $item->assignees()->sync([$owner->id]);

        $this->asUser($owner)
            ->get('/api/calendar/'.$item->id.'/ics')
            ->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=utf-8');
    }

    public function test_bulk_complete(): void
    {
        $owner = User::factory()->owner()->create();
        $a = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'starts_at' => now()->addDay(),
        ]);
        $b = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'starts_at' => now()->addDays(2),
        ]);
        $a->assignees()->sync([$owner->id]);
        $b->assignees()->sync([$owner->id]);

        $this->asUser($owner)->postJson('/api/calendar/bulk', [
            'ids' => [$a->id, $b->id],
            'changes' => ['status' => CalendarItemStatus::Completed->value],
        ])->assertOk();

        $this->assertSame(CalendarItemStatus::Completed, $a->fresh()->status);
        $this->assertSame(CalendarItemStatus::Completed, $b->fresh()->status);
    }

    public function test_reminder_offset_minutes5_accepted(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)->postJson('/api/calendar', [
            'title' => 'تذكير قصير',
            'type' => CalendarItemType::Meeting->value,
            'starts_at' => now()->addDay()->setTime(12, 0)->toIso8601String(),
            'reminders' => ['MINUTES_5'],
        ])->assertCreated()
            ->assertJsonPath('data.reminders.0.offset', 'MINUTES_5');
    }
}
