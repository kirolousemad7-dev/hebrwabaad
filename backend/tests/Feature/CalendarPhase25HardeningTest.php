<?php

namespace Tests\Feature;

use App\Enums\CalendarItemPriority;
use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\CalendarVisibility;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarPhase25HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_virtual_id_show_resolves_to_real_item(): void
    {
        $owner = User::factory()->owner()->create();
        $start = Carbon::parse('2026-09-08')->setTime(10, 0);

        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'سلسلة افتراضية',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'recurrence_rule' => 'FREQ=DAILY;INTERVAL=1',
        ]);
        $item->assignees()->sync([$owner->id]);

        $this->asUser($owner)
            ->getJson('/api/calendar/'.$item->id.':2026-09-10')
            ->assertOk()
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.title', 'سلسلة افتراضية');
    }

    public function test_virtual_id_put_with_scope_all_updates_master(): void
    {
        $owner = User::factory()->owner()->create();
        $start = Carbon::parse('2026-09-08')->setTime(10, 0);

        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'قبل التحديث',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'recurrence_rule' => 'FREQ=DAILY;INTERVAL=1',
        ]);
        $item->assignees()->sync([$owner->id]);

        $this->asUser($owner)->putJson('/api/calendar/'.$item->id.':2026-09-10', [
            'title' => 'بعد التحديث',
            'scope' => 'all',
        ])->assertOk()
            ->assertJsonPath('data.title', 'بعد التحديث')
            ->assertJsonPath('data.id', $item->id);

        $this->assertSame('بعد التحديث', $item->fresh()->title);
        $this->assertSame(1, CalendarItem::query()->where('title', 'بعد التحديث')->count());
    }

    public function test_recurrence_this_only_edit_adds_exception_and_standalone(): void
    {
        $owner = User::factory()->owner()->create();
        $start = Carbon::parse('2026-09-08')->setTime(11, 0);

        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'سلسلة يومية',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'recurrence_rule' => 'FREQ=DAILY;INTERVAL=1',
        ]);
        $item->assignees()->sync([$owner->id]);

        $occurrence = $start->copy()->addDays(2);

        $this->asUser($owner)->putJson('/api/calendar/'.$item->id, [
            'title' => 'استثناء يوم',
            'scope' => 'this',
            'occurrence_at' => $occurrence->toIso8601String(),
            'starts_at' => $occurrence->copy()->setTime(15, 0)->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.title', 'استثناء يوم');

        $item->refresh();
        $this->assertContains($occurrence->toDateString(), $item->recurrence_exceptions ?? []);
        $this->assertDatabaseHas('calendar_items', [
            'title' => 'استثناء يوم',
            'recurrence_parent_id' => $item->id,
        ]);

        $list = $this->asUser($owner)->getJson(
            '/api/calendar?from=2026-09-08&to=2026-09-14&scope=team&include_linked=0',
        )->assertOk();

        $titles = collect($list->json('data.items'));
        $this->assertTrue($titles->contains(fn ($row) => ($row['title'] ?? null) === 'استثناء يوم'));
        $this->assertTrue($titles->contains(
            fn ($row) => ($row['title'] ?? null) === 'سلسلة يومية'
                && str_starts_with((string) ($row['starts_at'] ?? ''), '2026-09-09')
        ));
        $this->assertFalse($titles->contains(
            fn ($row) => ($row['title'] ?? null) === 'سلسلة يومية'
                && str_starts_with((string) ($row['starts_at'] ?? ''), '2026-09-10')
        ));
    }

    public function test_recurrence_future_split_preserves_history(): void
    {
        $owner = User::factory()->owner()->create();
        $start = Carbon::parse('2026-09-01')->setTime(9, 0);

        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'سلسلة للتقسيم',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'recurrence_rule' => 'FREQ=DAILY;INTERVAL=1',
            'recurrence_until' => Carbon::parse('2026-09-30')->endOfDay(),
        ]);
        $item->assignees()->sync([$owner->id]);

        $splitAt = Carbon::parse('2026-09-10')->setTime(9, 0);

        $this->asUser($owner)->putJson('/api/calendar/'.$item->id, [
            'title' => 'سلسلة جديدة',
            'scope' => 'future',
            'occurrence_at' => $splitAt->toIso8601String(),
            'starts_at' => $splitAt->toIso8601String(),
        ])->assertOk();

        $item->refresh();
        $this->assertTrue($item->recurrence_until->lt($splitAt->copy()->startOfDay()));

        $newSeries = CalendarItem::query()
            ->where('title', 'سلسلة جديدة')
            ->whereNotNull('recurrence_rule')
            ->whereNull('recurrence_parent_id')
            ->where('id', '!=', $item->id)
            ->first();

        $this->assertNotNull($newSeries);
        $this->assertSame($splitAt->toDateString(), $newSeries->starts_at->toDateString());

        $list = $this->asUser($owner)->getJson(
            '/api/calendar?from=2026-09-01&to=2026-09-15&scope=team&include_linked=0',
        )->assertOk();

        $items = collect($list->json('data.items'));
        $history = $items->first(
            fn ($row) => str_starts_with((string) ($row['starts_at'] ?? ''), '2026-09-05')
                && (($row['master_id'] ?? $row['id']) == $item->id || ($row['id'] ?? null) === $item->id.':2026-09-05')
        );
        $this->assertNotNull($history);
        $this->assertTrue(
            ($history['master_id'] ?? null) === $item->id
            || ($history['id'] ?? null) === $item->id
            || ($history['id'] ?? null) === $item->id.':2026-09-05'
        );

        $after = $items->first(
            fn ($row) => str_starts_with((string) ($row['starts_at'] ?? ''), '2026-09-12')
                && (($row['title'] ?? null) === 'سلسلة جديدة')
        );
        $this->assertNotNull($after);
    }

    public function test_delete_this_only_adds_exception_series_continues(): void
    {
        $owner = User::factory()->owner()->create();
        $start = Carbon::parse('2026-09-08')->setTime(10, 0);

        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'للحذف الجزئي',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'recurrence_rule' => 'FREQ=DAILY;INTERVAL=1',
        ]);
        $item->assignees()->sync([$owner->id]);

        $occurrence = $start->copy()->addDays(1);

        $this->asUser($owner)->deleteJson('/api/calendar/'.$item->id, [
            'scope' => 'this',
            'occurrence_at' => $occurrence->toIso8601String(),
        ])->assertOk();

        $item->refresh();
        $this->assertContains($occurrence->toDateString(), $item->recurrence_exceptions ?? []);
        $this->assertNull($item->deleted_at);

        $list = $this->asUser($owner)->getJson(
            '/api/calendar?from=2026-09-08&to=2026-09-12&scope=team&include_linked=0',
        )->assertOk();

        $dates = collect($list->json('data.items'))
            ->filter(fn ($row) => ($row['title'] ?? null) === 'للحذف الجزئي')
            ->map(fn ($row) => substr((string) $row['starts_at'], 0, 10))
            ->values();

        $this->assertTrue($dates->contains('2026-09-08'));
        $this->assertFalse($dates->contains($occurrence->toDateString()));
        $this->assertTrue($dates->contains('2026-09-10'));
    }

    public function test_delete_future_truncates_until(): void
    {
        $owner = User::factory()->owner()->create();
        $start = Carbon::parse('2026-09-01')->setTime(10, 0);

        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'للقطع',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'recurrence_rule' => 'FREQ=DAILY;INTERVAL=1',
            'recurrence_until' => Carbon::parse('2026-09-30')->endOfDay(),
        ]);
        $item->assignees()->sync([$owner->id]);

        $cut = Carbon::parse('2026-09-15')->setTime(10, 0);

        $this->asUser($owner)->deleteJson('/api/calendar/'.$item->id, [
            'scope' => 'future',
            'occurrence_at' => $cut->toIso8601String(),
        ])->assertOk();

        $item->refresh();
        $this->assertTrue($item->recurrence_until->lt($cut->copy()->startOfDay()));
        $this->assertNull($item->deleted_at);

        $list = $this->asUser($owner)->getJson(
            '/api/calendar?from=2026-09-01&to=2026-09-20&scope=team&include_linked=0',
        )->assertOk();

        $dates = collect($list->json('data.items'))
            ->filter(fn ($row) => ($row['title'] ?? null) === 'للقطع')
            ->map(fn ($row) => substr((string) $row['starts_at'], 0, 10));

        $this->assertTrue($dates->contains('2026-09-14'));
        $this->assertFalse($dates->contains('2026-09-15'));
    }

    public function test_range_over_93_days_returns_422(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)->getJson(
            '/api/calendar?from=2026-01-01&to=2026-04-05&scope=mine&include_linked=0',
        )->assertStatus(422);
    }

    public function test_conflict_meeting_overlaps_task_does_not(): void
    {
        $owner = User::factory()->owner()->create();
        $starts = Carbon::parse('2026-09-20')->setTime(10, 0);
        $ends = $starts->copy()->addHour();

        $meeting = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'اجتماع متعارض',
            'type' => CalendarItemType::Meeting,
            'starts_at' => $starts,
            'ends_at' => $ends,
        ]);
        $meeting->assignees()->sync([$owner->id]);

        $task = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'مهمة لا تتعارض',
            'type' => CalendarItemType::Task,
            'starts_at' => $starts->copy()->addMinutes(15),
            'ends_at' => $ends->copy()->addMinutes(15),
        ]);
        $task->assignees()->sync([$owner->id]);

        $response = $this->asUser($owner)->postJson('/api/calendar/conflicts', [
            'starts_at' => $starts->copy()->addMinutes(30)->toIso8601String(),
            'ends_at' => $ends->copy()->addHour()->toIso8601String(),
            'assignee_ids' => [$owner->id],
        ])->assertOk();

        $ids = collect($response->json('data.items'))->pluck('id');
        $this->assertTrue($ids->contains($meeting->id));
        $this->assertFalse($ids->contains($task->id));
    }

    public function test_bulk_skips_unauthorized_private_item(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->create([
            'role' => UserRole::GraphicDesigner,
            'is_active' => true,
        ]);

        $owned = CalendarItem::factory()->create([
            'created_by' => $employee->id,
            'title' => 'ملك الموظف',
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'starts_at' => now()->addDay(),
            'priority' => CalendarItemPriority::Medium,
        ]);
        $owned->assignees()->sync([$employee->id]);

        $private = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'خاص بالمالك',
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'visibility' => CalendarVisibility::Private,
            'starts_at' => now()->addDays(2),
            'priority' => CalendarItemPriority::Medium,
        ]);
        $private->assignees()->sync([$owner->id]);

        $response = $this->asUser($employee)->postJson('/api/calendar/bulk', [
            'ids' => [$owned->id, $private->id],
            'changes' => ['priority' => CalendarItemPriority::High->value],
        ])->assertOk();

        $this->assertCount(1, $response->json('data.items'));
        $this->assertContains($private->id, $response->json('data.skipped_ids'));
        $this->assertSame(CalendarItemPriority::High, $owned->fresh()->priority);
        $this->assertSame(CalendarItemPriority::Medium, $private->fresh()->priority);
    }

    public function test_derived_project_hidden_from_unrelated_employee(): void
    {
        $employee = User::factory()->create([
            'role' => UserRole::GraphicDesigner,
            'is_active' => true,
        ]);

        $project = Project::factory()->create([
            'title' => 'مشروع مخفي',
            'deadline' => now()->addDays(2)->toDateString(),
            'started_at' => now()->subDay()->toDateString(),
        ]);

        $response = $this->asUser($employee)->getJson(
            '/api/calendar?from='.now()->toDateString().'&to='.now()->addDays(5)->toDateString().'&scope=mine&include_linked=1',
        )->assertOk();

        $this->assertFalse(
            collect($response->json('data.items'))->contains(
                fn ($row) => ($row['id'] ?? null) === 'derived-project-deadline-'.$project->id
            )
        );
    }

    public function test_ics_all_day_uses_value_date(): void
    {
        $owner = User::factory()->owner()->create();
        $day = Carbon::parse('2026-09-12')->startOfDay();

        $item = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'يوم كامل',
            'starts_at' => $day,
            'ends_at' => $day->copy()->addDay(),
            'all_day' => true,
        ]);
        $item->assignees()->sync([$owner->id]);

        $response = $this->asUser($owner)
            ->get('/api/calendar/'.$item->id.'/ics')
            ->assertOk();

        $body = $response->getContent();
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260912', $body);
        $this->assertStringContainsString('DTEND;VALUE=DATE:', $body);
    }

    public function test_workload_excludes_completed(): void
    {
        $owner = User::factory()->owner()->create();

        $open = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'starts_at' => now()->addDay()->setTime(9, 0),
        ]);
        $open->assignees()->sync([$owner->id]);

        $done = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Completed,
            'completed_at' => now(),
            'starts_at' => now()->addDay()->setTime(11, 0),
        ]);
        $done->assignees()->sync([$owner->id]);

        $response = $this->asUser($owner)->getJson(
            '/api/calendar/workload?from='.now()->toDateString().'&to='.now()->addDays(3)->toDateString(),
        )->assertOk();

        $this->assertSame(1, (int) $response->json('data.totals.tasks'));
        $row = collect($response->json('data.by_assignee'))->firstWhere('id', $owner->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('level', $row);
        $this->assertArrayHasKey('level_label', $row);
        $this->assertSame(1, (int) $row['tasks']);
    }

    public function test_invalid_occurrence_reference_returns_404(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)
            ->getJson('/api/calendar/abc')
            ->assertNotFound();
    }
}
