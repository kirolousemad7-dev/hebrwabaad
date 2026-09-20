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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectWorkspaceEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    /**
     * @return array{owner: User, manager: User, customer: User, project: Project}
     */
    private function seedProject(): array
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => $customer->id,
            'title' => 'مساحة مشروع محسنة',
        ]);

        return compact('owner', 'manager', 'customer', 'project');
    }

    public function test_owner_can_update_brief_and_client_profile(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->seedProject();

        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/brief', [
            'brief' => [
                'objective' => 'إطلاق هوية بصرية',
                'desired_outcome' => 'دليل علامة جاهز',
            ],
            'client_profile' => [
                'company_name' => 'شركة النور',
                'industry' => 'تجزئة',
            ],
            'requirements' => [
                'wants' => ['ألوان هادئة'],
                'does_not_want' => ['أسلوب مزدحم'],
            ],
            'scope' => [
                'deliverables' => ['شعار', 'دليل'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.brief.objective', 'إطلاق هوية بصرية')
            ->assertJsonPath('data.client_profile.company_name', 'شركة النور');

        $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')
            ->assertOk()
            ->assertJsonPath('data.brief.objective', 'إطلاق هوية بصرية')
            ->assertJsonPath('data.client_profile.industry', 'تجزئة')
            ->assertJsonStructure([
                'data' => [
                    'structure',
                    'phases',
                    'references',
                    'current_phase',
                    'next_milestone',
                    'next_task',
                ],
            ]);
    }

    public function test_phases_milestones_and_tasks_share_structure(): void
    {
        ['owner' => $owner, 'manager' => $manager, 'project' => $project] = $this->seedProject();
        $assignee = User::factory()->create(['role' => UserRole::GraphicDesigner]);

        $phase = $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/phases', [
            'title' => 'اكتشاف',
            'status' => ProjectPhase::STATUS_IN_PROGRESS,
            'is_client_visible' => true,
        ])->assertCreated()
            ->assertJsonPath('data.title', 'اكتشاف')
            ->json('data');

        $milestone = $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/milestones', [
            'title' => 'اعتماد الموجز',
            'phase_id' => $phase['id'],
            'is_client_visible' => true,
            'due_date' => now()->addDays(5)->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.phase_id', $phase['id'])
            ->json('data');

        $task = $this->asUser($manager)->postJson('/api/workspace/account-manager/tasks', [
            'title' => 'جمع المتطلبات',
            'project_id' => $project->id,
            'phase_id' => $phase['id'],
            'milestone_id' => $milestone['id'],
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::High->value,
            'status' => TaskStatus::Todo->value,
            'deadline' => now()->addDays(3)->toDateString(),
            'is_client_visible' => true,
        ])->assertCreated()
            ->assertJsonPath('data.phase_id', $phase['id'])
            ->assertJsonPath('data.milestone_id', $milestone['id'])
            ->json('data');

        $structure = $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/structure')
            ->assertOk()
            ->json('data');

        $this->assertSame($phase['id'], $structure['phases'][0]['id']);
        $this->assertSame($milestone['id'], $structure['phases'][0]['milestones'][0]['id']);
        $this->assertSame($task['id'], $structure['phases'][0]['milestones'][0]['tasks'][0]['id']);

        $this->assertDatabaseHas('tasks', [
            'id' => $task['id'],
            'project_id' => $project->id,
            'phase_id' => $phase['id'],
            'milestone_id' => $milestone['id'],
        ]);
        $this->assertSame(1, Task::query()->where('id', $task['id'])->count());
    }

    public function test_references_and_client_visibility(): void
    {
        ['owner' => $owner, 'customer' => $customer, 'project' => $project] = $this->seedProject();

        ProjectPhase::query()->create([
            'project_id' => $project->id,
            'title' => 'إنتاج',
            'status' => ProjectPhase::STATUS_IN_PROGRESS,
            'sort_order' => 1,
            'is_client_visible' => true,
        ]);

        ProjectMilestone::query()->create([
            'project_id' => $project->id,
            'title' => 'معلم داخلي',
            'status' => ProjectMilestone::STATUS_PENDING,
            'is_client_visible' => false,
            'created_by' => $owner->id,
        ]);

        ProjectMilestone::query()->create([
            'project_id' => $project->id,
            'title' => 'معلم للعميل',
            'status' => ProjectMilestone::STATUS_PENDING,
            'is_client_visible' => true,
            'due_date' => now()->addWeek()->toDateString(),
            'created_by' => $owner->id,
        ]);

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/references', [
            'title' => 'مرجع داخلي',
            'url' => 'https://example.com/internal',
            'type' => 'WEBSITE',
            'is_client_visible' => false,
        ])->assertCreated();

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/references', [
            'title' => 'مرجع للعميل',
            'url' => 'https://example.com/public',
            'type' => 'DESIGN',
            'is_client_visible' => true,
        ])->assertCreated();

        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/brief', [
            'brief' => [
                'objective' => 'هدف ظاهر',
                'special_instructions' => 'سري داخلي',
            ],
            'client_profile' => [
                'company_name' => 'عميل ظاهر',
                'important_notes' => 'ملاحظات داخلية',
            ],
        ])->assertOk();

        $client = $this->asUser($customer)->getJson('/api/customer/projects/'.$project->id)
            ->assertOk()
            ->json('data');

        $this->assertSame('هدف ظاهر', $client['brief']['objective'] ?? null);
        $this->assertArrayNotHasKey('special_instructions', $client['brief'] ?? []);
        $this->assertSame('عميل ظاهر', $client['client_profile']['company_name'] ?? null);
        $this->assertArrayNotHasKey('important_notes', $client['client_profile'] ?? []);
        $this->assertCount(1, $client['references'] ?? []);
        $this->assertSame('مرجع للعميل', $client['references'][0]['title']);
        $this->assertCount(1, $client['upcoming_milestones'] ?? []);
        $this->assertSame('معلم للعميل', $client['upcoming_milestones'][0]['title']);
        $this->assertSame('إنتاج', $client['current_phase']['title'] ?? null);
    }

    public function test_project_member_can_view_workspace(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->seedProject();
        $member = User::factory()->create(['role' => UserRole::GraphicDesigner]);

        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/members', [
            'members' => [
                ['user_id' => $member->id, 'role' => 'member'],
            ],
        ])->assertOk();

        $this->asUser($member)->getJson('/api/operations/projects/'.$project->id.'/workspace')
            ->assertOk()
            ->assertJsonPath('data.project.id', $project->id);
    }

    public function test_customer_cannot_see_other_customer_project(): void
    {
        ['customer' => $customer, 'project' => $project] = $this->seedProject();
        $other = User::factory()->create(['role' => UserRole::Customer]);

        $this->asUser($other)->getJson('/api/customer/projects/'.$project->id)
            ->assertForbidden();

        $this->asUser($customer)->getJson('/api/customer/projects/'.$project->id)
            ->assertOk();
    }

    public function test_owner_can_create_project_task_and_optionally_link_calendar(): void
    {
        ['owner' => $owner, 'manager' => $manager, 'project' => $project] = $this->seedProject();
        $assignee = User::factory()->create(['role' => UserRole::GraphicDesigner]);

        $deadline = now()->addDays(4)->toDateString();

        $response = $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'مهمة من مساحة المشروع',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'deadline' => $deadline,
            'start_at' => now()->addDay()->toDateString(),
            'link_to_calendar' => true,
            'is_client_visible' => false,
        ])->assertCreated()
            ->assertJsonPath('data.task.title', 'مهمة من مساحة المشروع')
            ->assertJsonPath('data.task.project_id', $project->id);

        $taskId = (int) $response->json('data.task.id');
        $calendarItemId = $response->json('data.calendar_item_id');

        $this->assertNotNull($calendarItemId);
        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'project_id' => $project->id,
            'calendar_item_id' => $calendarItemId,
        ]);
        $this->assertDatabaseHas('calendar_items', [
            'id' => $calendarItemId,
            'related_type' => 'workspace_task',
            'related_id' => $taskId,
        ]);

        $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/tasks')
            ->assertOk()
            ->assertJsonFragment(['id' => $taskId]);

        $this->asUser($manager)->getJson('/api/operations/projects/'.$project->id.'/workspace')
            ->assertOk()
            ->assertJsonStructure(['data' => ['team_stats', 'risks', 'next_deadline']]);
    }

    public function test_task_without_calendar_link_and_idempotent_relink(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->seedProject();
        $assignee = User::factory()->create(['role' => UserRole::GraphicDesigner]);

        $create = $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'بدون تقويم',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Low->value,
            'deadline' => now()->addDays(2)->toDateString(),
            'link_to_calendar' => false,
        ])->assertCreated();

        $taskId = (int) $create->json('data.task.id');
        $this->assertNull($create->json('data.calendar_item_id'));
        $this->assertDatabaseMissing('calendar_items', [
            'related_type' => 'workspace_task',
            'related_id' => $taskId,
        ]);

        $link = $this->asUser($owner)->postJson(
            '/api/operations/projects/'.$project->id.'/tasks/'.$taskId.'/link-calendar',
            ['starts_at' => now()->addDay()->toIso8601String()]
        )->assertOk();

        $calendarId = (int) $link->json('data.calendar_item_id');
        $this->assertGreaterThan(0, $calendarId);

        $this->asUser($owner)->postJson(
            '/api/operations/projects/'.$project->id.'/tasks/'.$taskId.'/link-calendar',
            ['starts_at' => now()->addDays(2)->toIso8601String()]
        )->assertOk()
            ->assertJsonPath('data.calendar_item_id', $calendarId);

        $this->assertSame(1, CalendarItem::query()
            ->where('related_type', 'workspace_task')
            ->where('related_id', $taskId)
            ->count());
    }

    public function test_owner_can_update_project_task_and_customer_cannot_create(): void
    {
        ['owner' => $owner, 'customer' => $customer, 'project' => $project] = $this->seedProject();
        $assignee = User::factory()->create(['role' => UserRole::GraphicDesigner]);
        $otherManager = User::factory()->accountManager()->create();

        $create = $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'للتحديث',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Todo->value,
        ])->assertCreated();

        $taskId = (int) $create->json('data.task.id');

        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/tasks/'.$taskId, [
            'title' => 'تم التحديث',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::High->value,
            'status' => TaskStatus::InProgress->value,
            'deadline' => now()->addDays(3)->toDateString(),
        ])->assertOk()
            ->assertJsonPath('data.task.title', 'تم التحديث')
            ->assertJsonPath('data.task.status', TaskStatus::InProgress->value);

        $this->asUser($customer)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'محظور',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Low->value,
        ])->assertForbidden();

        $this->asUser($otherManager)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'مدير غير معيّن',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Low->value,
        ])->assertForbidden();
    }

    public function test_customer_payload_excludes_internal_brief_and_team_stats(): void
    {
        ['owner' => $owner, 'customer' => $customer, 'project' => $project] = $this->seedProject();

        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/brief', [
            'brief' => [
                'objective' => 'هدف ظاهر',
                'special_instructions' => 'داخلي سري',
            ],
            'client_profile' => [
                'company_name' => 'شركة ظاهرة',
                'important_notes' => 'ملاحظات داخلية',
            ],
        ])->assertOk();

        $payload = $this->asUser($customer)->getJson('/api/customer/projects/'.$project->id)
            ->assertOk()
            ->json('data');

        $this->assertSame('هدف ظاهر', $payload['brief']['objective'] ?? null);
        $this->assertArrayNotHasKey('special_instructions', $payload['brief'] ?? []);
        $this->assertArrayNotHasKey('important_notes', $payload['client_profile'] ?? []);
        $this->assertArrayNotHasKey('team_stats', $payload);
        $this->assertArrayNotHasKey('members', $payload);
        $this->assertArrayNotHasKey('health', $payload);
        $this->assertArrayNotHasKey('unified_work', $payload);
        $this->assertArrayNotHasKey('attention', $payload);
        $this->assertArrayNotHasKey('execution_summary', $payload);
        $this->assertArrayNotHasKey('closure_readiness', $payload);
        $this->assertArrayNotHasKey('recent_activity', $payload);
        $this->assertArrayNotHasKey('pending_approvals', $payload);
        $this->assertArrayHasKey('deliverables', $payload);
    }

    public function test_owner_receives_execution_summary_and_attention(): void
    {
        ['owner' => $owner, 'manager' => $manager, 'project' => $project] = $this->seedProject();
        $assignee = User::factory()->create(['role' => UserRole::GraphicDesigner]);

        $overdueDeadline = now()->subDays(2)->toDateString();
        $dueSoonDeadline = now()->addDays(3)->toDateString();

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'مهمة متأخرة',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::High->value,
            'status' => TaskStatus::Todo->value,
            'deadline' => $overdueDeadline,
        ])->assertCreated();

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'مهمة قريبة',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Review->value,
            'deadline' => $dueSoonDeadline,
        ])->assertCreated();

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/milestones', [
            'title' => 'معلم متأخر',
            'due_date' => now()->subDay()->toDateString(),
            'status' => ProjectMilestone::STATUS_PENDING,
        ])->assertCreated();

        $workspace = $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'execution_summary' => [
                        'open_tasks',
                        'overdue_tasks',
                        'completion_percent',
                        'attention_count',
                        'closure',
                    ],
                    'attention' => ['items', 'counts'],
                    'deliverables',
                    'pending_approvals',
                    'closure_readiness',
                    'recent_activity',
                ],
            ])
            ->json('data');

        $this->assertGreaterThanOrEqual(1, $workspace['execution_summary']['overdue_tasks']);
        $this->assertGreaterThanOrEqual(1, $workspace['attention']['counts']['overdue']);
        $this->assertGreaterThanOrEqual(1, $workspace['attention']['counts']['due_soon']);
        $this->assertGreaterThanOrEqual(1, $workspace['attention']['counts']['waiting']);
        $this->assertSame('attention', $workspace['closure_readiness']['state']);

        $kinds = collect($workspace['attention']['items'])->pluck('kind')->all();
        $this->assertContains('overdue', $kinds);
        $this->assertContains('due_soon', $kinds);
        $this->assertContains('waiting', $kinds);

        $this->asUser($manager)->getJson('/api/operations/projects/'.$project->id.'/workspace')
            ->assertOk()
            ->assertJsonPath('data.project.id', $project->id);
    }

    public function test_unauthorized_account_manager_cannot_access_workspace_execution(): void
    {
        ['project' => $project] = $this->seedProject();
        $otherManager = User::factory()->accountManager()->create();

        $this->asUser($otherManager)->getJson('/api/operations/projects/'.$project->id.'/workspace')
            ->assertForbidden();
    }

    public function test_task_completion_updates_progress_and_attention(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->seedProject();
        $assignee = User::factory()->create(['role' => UserRole::GraphicDesigner]);

        $create = $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'لإكمالها',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::InProgress->value,
            'deadline' => now()->subDay()->toDateString(),
        ])->assertCreated();

        $taskId = (int) $create->json('data.task.id');

        $before = $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $before['progress']['overdue']);
        $this->assertSame(0, $before['progress']['completed']);

        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/tasks/'.$taskId, [
            'title' => 'لإكمالها',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Completed->value,
            'deadline' => now()->subDay()->toDateString(),
        ])->assertOk();

        $after = $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $after['progress']['completed']);
        $this->assertSame(0, $after['progress']['overdue']);
        $this->assertSame(0, $after['execution_summary']['open_tasks']);
        $this->assertSame(0, $after['attention']['counts']['overdue']);
    }

    public function test_milestone_open_tasks_exposed_for_completion_warning(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->seedProject();
        $assignee = User::factory()->create(['role' => UserRole::GraphicDesigner]);

        $milestone = $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/milestones', [
            'title' => 'معلم مع مهام',
            'due_date' => now()->addDays(5)->toDateString(),
        ])->assertCreated()->json('data');

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'مهمة مرتبطة',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Low->value,
            'status' => TaskStatus::Todo->value,
            'milestone_id' => $milestone['id'],
        ])->assertCreated();

        $listed = $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/milestones')
            ->assertOk()
            ->json('data.items.0');

        $this->assertSame(1, $listed['open_tasks'] ?? null);
        $this->assertSame(1, $listed['progress']['total'] ?? null);
        $this->assertSame(0, $listed['progress']['completed'] ?? null);
    }

    public function test_recent_activity_reuses_timeline_and_stays_internal(): void
    {
        ['owner' => $owner, 'customer' => $customer, 'project' => $project] = $this->seedProject();
        $assignee = User::factory()->create(['role' => UserRole::GraphicDesigner]);

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'نشاط مهمة',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
        ])->assertCreated();

        $workspace = $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($workspace['recent_activity']);
        $this->assertSame($workspace['timeline_preview'], $workspace['recent_activity']);
        $this->assertGreaterThanOrEqual(1, $workspace['execution_summary']['recent_activity_count']);

        $customerPayload = $this->asUser($customer)->getJson('/api/customer/projects/'.$project->id)
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('recent_activity', $customerPayload);
        $this->assertArrayNotHasKey('timeline_preview', $customerPayload);
    }

    public function test_cannot_attach_task_to_another_projects_phase_or_milestone(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->seedProject();
        $other = Project::factory()->create([
            'account_manager_id' => $project->account_manager_id,
            'customer_id' => $project->customer_id,
        ]);
        $assignee = User::factory()->create(['role' => UserRole::GraphicDesigner]);

        $foreignPhase = ProjectPhase::query()->create([
            'project_id' => $other->id,
            'title' => 'مرحلة أجنبية',
            'status' => ProjectPhase::STATUS_PENDING,
            'sort_order' => 1,
            'is_client_visible' => false,
        ]);
        $foreignMilestone = ProjectMilestone::query()->create([
            'project_id' => $other->id,
            'title' => 'معلم أجنبي',
            'status' => ProjectMilestone::STATUS_PENDING,
            'sort_order' => 1,
            'is_client_visible' => false,
            'created_by' => $owner->id,
        ]);

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'ارتباط خاطئ',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'phase_id' => $foreignPhase->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['phase_id']);

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'ارتباط خاطئ معلم',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'milestone_id' => $foreignMilestone->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['milestone_id']);
    }

    public function test_task_idor_rejects_task_from_another_project(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->seedProject();
        $other = Project::factory()->create([
            'account_manager_id' => $project->account_manager_id,
            'customer_id' => $project->customer_id,
        ]);
        $assignee = User::factory()->create(['role' => UserRole::GraphicDesigner]);

        $foreignTask = $this->asUser($owner)->postJson('/api/operations/projects/'.$other->id.'/tasks', [
            'title' => 'مهمة مشروع آخر',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Low->value,
        ])->assertCreated()->json('data.task.id');

        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/tasks/'.$foreignTask, [
            'title' => 'محاولة اختراق',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Low->value,
            'status' => TaskStatus::Todo->value,
        ])->assertNotFound();

        $this->asUser($owner)->postJson(
            '/api/operations/projects/'.$project->id.'/tasks/'.$foreignTask.'/link-calendar',
            ['starts_at' => now()->addDay()->toIso8601String()]
        )->assertNotFound();
    }

    public function test_customer_progress_excludes_internal_tasks_and_deliverable_ids(): void
    {
        ['owner' => $owner, 'customer' => $customer, 'project' => $project] = $this->seedProject();
        $assignee = User::factory()->create(['role' => UserRole::GraphicDesigner]);

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'داخلي',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Completed->value,
            'is_client_visible' => false,
        ])->assertCreated();

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'ظاهر للعميل',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::InProgress->value,
            'is_client_visible' => true,
        ])->assertCreated();

        $payload = $this->asUser($customer)->getJson('/api/customer/projects/'.$project->id)
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $payload['progress']['total']);
        $this->assertSame(0, $payload['progress']['completed']);
        $this->assertSame(1, $payload['progress']['in_progress']);

        foreach ($payload['deliverables'] ?? [] as $row) {
            $this->assertArrayNotHasKey('task_id', $row);
            $this->assertArrayNotHasKey('file_id', $row);
            $this->assertArrayNotHasKey('created_by', $row);
            $this->assertArrayNotHasKey('creator', $row);
        }

        foreach ($payload['references'] ?? [] as $row) {
            $this->assertArrayNotHasKey('created_by', $row);
            $this->assertArrayNotHasKey('creator', $row);
            $this->assertArrayNotHasKey('file_id', $row);
        }
    }

    public function test_execution_summary_matches_progress_and_attention(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->seedProject();
        $assignee = User::factory()->create(['role' => UserRole::GraphicDesigner]);

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'مفتوحة',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Todo->value,
            'deadline' => now()->subDays(1)->toDateString(),
        ])->assertCreated();

        $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/tasks', [
            'title' => 'مكتملة',
            'assigned_to' => $assignee->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Completed->value,
        ])->assertCreated();

        $data = $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')
            ->assertOk()
            ->json('data');

        $open = max(0, $data['progress']['total'] - $data['progress']['completed']);
        $this->assertSame($open, $data['execution_summary']['open_tasks']);
        $this->assertSame($data['progress']['overdue'], $data['execution_summary']['overdue_tasks']);
        $this->assertSame($data['progress']['percent'], $data['execution_summary']['completion_percent']);
        $this->assertSame(
            count($data['attention']['items']),
            $data['execution_summary']['attention_count']
        );
        $this->assertSame($data['closure_readiness'], $data['execution_summary']['closure']);
    }

    public function test_reference_update_rejects_cross_project_idor(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->seedProject();
        $other = Project::factory()->create([
            'account_manager_id' => $project->account_manager_id,
            'customer_id' => $project->customer_id,
        ]);

        $reference = $this->asUser($owner)->postJson('/api/operations/projects/'.$other->id.'/references', [
            'title' => 'مرجع مشروع آخر',
            'type' => 'OTHER',
        ])->assertCreated()->json('data');

        $this->asUser($owner)->putJson(
            '/api/operations/projects/'.$project->id.'/references/'.$reference['id'],
            ['title' => 'محاولة']
        )->assertNotFound();

        $this->asUser($owner)->deleteJson(
            '/api/operations/projects/'.$project->id.'/references/'.$reference['id']
        )->assertNotFound();
    }

    public function test_pending_approvals_only_include_related_project(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->seedProject();
        $other = Project::factory()->create([
            'account_manager_id' => $project->account_manager_id,
            'customer_id' => $project->customer_id,
        ]);

        ApprovalRequest::query()->create([
            'type' => ApprovalRequestType::ProjectMilestone->value,
            'related_type' => 'project',
            'related_id' => $other->id,
            'title' => 'موافقة مشروع آخر',
            'status' => ApprovalRequestStatus::Pending->value,
            'requested_by' => $owner->id,
            'assigned_to' => $owner->id,
        ]);

        ApprovalRequest::query()->create([
            'type' => ApprovalRequestType::ProjectMilestone->value,
            'related_type' => 'project',
            'related_id' => $project->id,
            'title' => 'موافقة هذا المشروع',
            'status' => ApprovalRequestStatus::Pending->value,
            'requested_by' => $owner->id,
            'assigned_to' => $owner->id,
        ]);

        $approvals = $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')
            ->assertOk()
            ->json('data.pending_approvals');

        $this->assertCount(1, $approvals);
        $this->assertSame('موافقة هذا المشروع', $approvals[0]['title']);
    }
}
