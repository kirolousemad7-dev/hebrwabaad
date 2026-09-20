<?php

namespace Tests\Feature;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\ApprovalRequest;
use App\Models\CalendarItem;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\User;
use App\Services\Operations\Work\TaskCalendarLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectWorkspaceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    /**
     * @return array{owner: User, manager: User, otherManager: User, customer: User, project: Project, assignee: User}
     */
    private function seedProject(): array
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $otherManager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $assignee = User::factory()->create(['role' => UserRole::GraphicDesigner]);
        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => $customer->id,
            'title' => 'مشروع سلامة Phase 6',
        ]);

        return compact('owner', 'manager', 'otherManager', 'customer', 'project', 'assignee');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function findForbiddenKeys(array $payload, array $forbidden, string $prefix = ''): array
    {
        $hits = [];
        foreach ($payload as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (in_array((string) $key, $forbidden, true)) {
                $hits[] = $path;
            }
            if (is_array($value)) {
                $hits = array_merge($hits, $this->findForbiddenKeys($value, $forbidden, $path));
            }
        }

        return $hits;
    }

    public function test_calendar_link_is_idempotent_and_unique_per_task(): void
    {
        ['owner' => $owner, 'project' => $project, 'assignee' => $assignee] = $this->seedProject();

        $taskId = (int) $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'مهمة ربط',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'link_to_calendar' => false,
        ])->assertCreated()->json('data.task.id');

        $first = $this->asUser($owner)->postJson(
            '/api/operations/projects/'.$project->id.'/tasks/'.$taskId.'/link-calendar',
            ['starts_at' => now()->addDay()->toIso8601String()]
        )->assertOk()->json('data.calendar_item_id');

        $second = $this->asUser($owner)->postJson(
            '/api/operations/projects/'.$project->id.'/tasks/'.$taskId.'/link-calendar',
            ['starts_at' => now()->addDays(2)->toIso8601String()]
        )->assertOk()->json('data.calendar_item_id');

        $this->assertSame($first, $second);
        $this->assertSame(1, CalendarItem::query()
            ->where('related_type', 'workspace_task')
            ->where('related_id', $taskId)
            ->count());

        $task = Task::query()->findOrFail($taskId);
        $service = app(TaskCalendarLinkService::class);
        $again = $service->linkFromTask($owner, $task, [
            'starts_at' => now()->addDays(3)->toIso8601String(),
        ]);
        $this->assertSame((int) $first, (int) $again->id);
        $this->assertSame(1, CalendarItem::query()
            ->where('related_type', 'workspace_task')
            ->where('related_id', $taskId)
            ->count());
    }

    public function test_calendar_link_rejects_wrong_project_and_unauthorized_am(): void
    {
        ['owner' => $owner, 'otherManager' => $otherManager, 'project' => $project, 'assignee' => $assignee] = $this->seedProject();
        $other = Project::factory()->create([
            'account_manager_id' => $project->account_manager_id,
            'customer_id' => $project->customer_id,
        ]);

        $taskId = (int) $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'مهمة',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Low->value,
        ])->assertCreated()->json('data.task.id');

        $this->asUser($owner)->postJson(
            '/api/operations/projects/'.$other->id.'/tasks/'.$taskId.'/link-calendar',
            ['starts_at' => now()->addDay()->toIso8601String()]
        )->assertNotFound();

        $this->asUser($otherManager)->postJson(
            '/api/operations/projects/'.$project->id.'/tasks/'.$taskId.'/link-calendar',
            ['starts_at' => now()->addDay()->toIso8601String()]
        )->assertForbidden();
    }

    public function test_project_files_remain_customer_readable_by_existing_policy(): void
    {
        Storage::fake('local');
        ['manager' => $manager, 'customer' => $customer, 'project' => $project] = $this->seedProject();

        $created = $this->asUser($manager)
            ->post('/api/workspace/files', [
                'file' => UploadedFile::fake()->create('internal-brief.pdf', 40, 'application/pdf'),
                'project_id' => $project->id,
            ])
            ->assertCreated()
            ->json('data');

        $this->asUser($customer)
            ->getJson('/api/customer/files/'.$created['id'])
            ->assertOk()
            ->assertJsonPath('data.id', $created['id']);

        $this->assertDatabaseHas('files', [
            'id' => $created['id'],
            'project_id' => $project->id,
        ]);
        $this->assertFalse(
            Schema::hasColumn('files', 'client_visible') || Schema::hasColumn('files', 'is_client_visible')
        );
    }

    public function test_customer_payload_has_no_forbidden_keys_recursively(): void
    {
        ['owner' => $owner, 'customer' => $customer, 'project' => $project, 'assignee' => $assignee] = $this->seedProject();

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'ظاهر',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'is_client_visible' => true,
        ])->assertCreated();

        ApprovalRequest::query()->create([
            'type' => ApprovalRequestType::ProjectMilestone->value,
            'related_type' => 'project',
            'related_id' => $project->id,
            'title' => 'موافقة داخلية',
            'status' => ApprovalRequestStatus::Pending->value,
            'requested_by' => $owner->id,
            'assigned_to' => $owner->id,
        ]);

        $payload = $this->asUser($customer)->getJson('/api/customer/projects/'.$project->id)
            ->assertOk()
            ->json('data');

        $forbidden = [
            'team_stats',
            'members',
            'health',
            'risks',
            'unified_work',
            'attention',
            'execution_summary',
            'closure_readiness',
            'recent_activity',
            'pending_approvals',
            'timeline_preview',
            'assigned_to',
            'assignee',
            'task_id',
            'file_id',
            'created_by',
            'creator',
            'important_notes',
            'special_instructions',
        ];

        $hits = $this->findForbiddenKeys($payload, $forbidden);
        $this->assertSame([], $hits, 'Forbidden keys found: '.implode(', ', $hits));
    }

    public function test_progress_consistency_scenarios(): void
    {
        ['owner' => $owner, 'customer' => $customer, 'project' => $project, 'assignee' => $assignee] = $this->seedProject();

        // A: zero tasks
        $empty = $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')->assertOk()->json('data');
        $this->assertSame(0, $empty['progress']['total']);
        $this->assertSame(0, $empty['execution_summary']['open_tasks']);
        $this->assertSame('ready', $empty['closure_readiness']['state']);

        // B: some completed
        $openId = (int) $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'مفتوحة',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Todo->value,
            'is_client_visible' => true,
        ])->assertCreated()->json('data.task.id');

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'مكتملة',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Completed->value,
            'is_client_visible' => true,
        ])->assertCreated();

        $partial = $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')->assertOk()->json('data');
        $this->assertSame(2, $partial['progress']['total']);
        $this->assertSame(1, $partial['progress']['completed']);
        $this->assertSame(1, $partial['execution_summary']['open_tasks']);
        $this->assertEqualsWithDelta(50.0, $partial['progress']['percent'], 0.1);
        $this->assertSame('attention', $partial['closure_readiness']['state']);

        // D: overdue
        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/tasks/'.$openId, [
            'title' => 'مفتوحة',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Todo->value,
            'deadline' => now()->subDay()->toDateString(),
            'is_client_visible' => true,
        ])->assertOk();

        $overdue = $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')->assertOk()->json('data');
        $this->assertSame(1, $overdue['progress']['overdue']);
        $this->assertSame(1, $overdue['execution_summary']['overdue_tasks']);
        $this->assertGreaterThanOrEqual(1, $overdue['attention']['counts']['overdue']);

        // C: all completed
        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/tasks/'.$openId, [
            'title' => 'مفتوحة',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Completed->value,
            'is_client_visible' => true,
        ])->assertOk();

        $done = $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')->assertOk()->json('data');
        $this->assertSame(0, $done['execution_summary']['open_tasks']);
        $this->assertEqualsWithDelta(100.0, $done['progress']['percent'], 0.1);
        $this->assertSame(0, $done['attention']['counts']['overdue']);

        // E: open task under milestone marked done candidate
        $milestone = $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/milestones', [
            'title' => 'معلم',
            'due_date' => now()->addWeek()->toDateString(),
        ])->assertCreated()->json('data');

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'تحت المعلم',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Low->value,
            'status' => TaskStatus::Todo->value,
            'milestone_id' => $milestone['id'],
        ])->assertCreated();

        $listed = $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/milestones')
            ->assertOk()
            ->json('data.items');
        $row = collect($listed)->firstWhere('id', $milestone['id']);
        $this->assertSame(1, $row['open_tasks']);

        // F: customer sees only client-visible
        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'داخلي فقط',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Low->value,
            'status' => TaskStatus::Todo->value,
            'is_client_visible' => false,
        ])->assertCreated();

        $client = $this->asUser($customer)->getJson('/api/customer/projects/'.$project->id)->assertOk()->json('data');
        $this->assertSame(2, $client['progress']['total']);
        $this->assertSame(2, $client['progress']['completed']);
        $this->assertEqualsWithDelta(100.0, $client['progress']['percent'], 0.1);
    }

    public function test_cross_project_child_mutations_return_not_found(): void
    {
        ['owner' => $owner, 'project' => $project, 'assignee' => $assignee] = $this->seedProject();
        $other = Project::factory()->create([
            'account_manager_id' => $project->account_manager_id,
            'customer_id' => $project->customer_id,
        ]);

        $phase = ProjectPhase::query()->create([
            'project_id' => $other->id,
            'title' => 'مرحلة',
            'status' => ProjectPhase::STATUS_PENDING,
            'sort_order' => 1,
            'is_client_visible' => false,
        ]);
        $milestone = ProjectMilestone::query()->create([
            'project_id' => $other->id,
            'title' => 'معلم',
            'status' => ProjectMilestone::STATUS_PENDING,
            'sort_order' => 1,
            'created_by' => $owner->id,
        ]);
        $taskId = (int) $this->asUser($owner)->postJson('/api/operations/projects/'.$other->id.'/tasks', [
            'title' => 'مهمة أجنبية',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Low->value,
        ])->assertCreated()->json('data.task.id');
        $reference = $this->asUser($owner)->postJson('/api/operations/projects/'.$other->id.'/references', [
            'title' => 'مرجع',
            'type' => 'OTHER',
        ])->assertCreated()->json('data');

        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/phases/'.$phase->id, [
            'title' => 'x',
        ])->assertNotFound();

        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/milestones/'.$milestone->id, [
            'title' => 'x',
        ])->assertNotFound();

        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/tasks/'.$taskId, [
            'title' => 'x',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Low->value,
            'status' => TaskStatus::Todo->value,
        ])->assertNotFound();

        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/references/'.$reference['id'], [
            'title' => 'x',
        ])->assertNotFound();
    }

    public function test_role_matrix_for_workspace_access(): void
    {
        ['owner' => $owner, 'manager' => $manager, 'otherManager' => $otherManager, 'customer' => $customer, 'project' => $project] = $this->seedProject();

        $this->getJson('/api/operations/projects/'.$project->id.'/workspace')->assertUnauthorized();
        $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')->assertOk();
        $this->asUser($manager)->getJson('/api/operations/projects/'.$project->id.'/workspace')->assertOk();
        $this->asUser($otherManager)->getJson('/api/operations/projects/'.$project->id.'/workspace')->assertForbidden();
        $this->asUser($customer)->getJson('/api/operations/projects/'.$project->id.'/workspace')->assertForbidden();
        $this->asUser($customer)->getJson('/api/customer/projects/'.$project->id)->assertOk();
    }
}
