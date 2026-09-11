<?php

namespace Tests\Feature;

use App\Enums\CalendarItemType;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\OperationalAttentionSnooze;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Operations\SlaEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsPhase4OpsExtensionsTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_saved_view_crud_and_invalid_filter_rejected(): void
    {
        $owner = User::factory()->owner()->create();

        $create = $this->asUser($owner)->postJson('/api/operations/saved-views', [
            'name' => 'متأخري فريقي',
            'view_type' => 'work',
            'filters' => [
                'scope' => 'team',
                'bucket' => 'overdue',
                'sort' => 'overdue_first',
            ],
            'is_pinned' => true,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'متأخري فريقي')
            ->assertJsonPath('data.is_pinned', true);

        $id = $create->json('data.id');

        $this->asUser($owner)->getJson('/api/operations/saved-views')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $id);

        $this->asUser($owner)->postJson('/api/operations/saved-views', [
            'name' => 'bad',
            'filters' => [
                'scope' => 'mine',
                'raw_sql' => '1=1',
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['filters']);

        $this->asUser($owner)->putJson('/api/operations/saved-views/'.$id, [
            'name' => 'متأخري الفريق',
        ])->assertOk()
            ->assertJsonPath('data.name', 'متأخري الفريق');

        $this->asUser($owner)->deleteJson('/api/operations/saved-views/'.$id)
            ->assertOk();

        $this->assertDatabaseMissing('operational_saved_views', ['id' => $id]);
    }

    public function test_project_milestones_crud(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => $customer->id,
            'title' => 'مشروع معالم',
        ]);

        $create = $this->asUser($owner)->postJson('/api/operations/projects/'.$project->id.'/milestones', [
            'title' => 'تسليم المرحلة الأولى',
            'due_date' => now()->addWeek()->toDateString(),
            'status' => 'PENDING',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'تسليم المرحلة الأولى');

        $milestoneId = $create->json('data.id');

        $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/milestones')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $milestoneId);

        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/milestones/'.$milestoneId, [
            'status' => 'DONE',
        ])->assertOk()
            ->assertJsonPath('data.status', 'DONE');
    }

    public function test_project_timeline_returns_items(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => $customer->id,
            'title' => 'مشروع خط زمني',
        ]);

        Task::factory()->create([
            'project_id' => $project->id,
            'created_by' => $manager->id,
            'assigned_to' => $manager->id,
            'title' => 'مهمة مساحة',
            'status' => TaskStatus::Todo->value,
        ]);

        CalendarItem::query()->create([
            'title' => 'مهمة تقويم للمشروع',
            'type' => CalendarItemType::Task->value,
            'created_by' => $owner->id,
            'starts_at' => now()->addDay(),
            'related_type' => 'project',
            'related_id' => $project->id,
            'status' => 'SCHEDULED',
            'priority' => 'MEDIUM',
            'visibility' => 'PARTICIPANTS',
            'source' => 'MANUAL',
        ]);

        $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/timeline')
            ->assertOk()
            ->assertJsonStructure(['data' => ['items']])
            ->assertJsonFragment(['type' => 'project_created']);

        $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'timeline_preview',
                    'milestones',
                ],
            ]);
    }

    public function test_sla_rule_create(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)->postJson('/api/operations/sla-rules', [
            'name' => 'تواصل أول مع العميل',
            'module' => SlaEvaluationService::MODULE_CRM_LEAD,
            'event_type' => SlaEvaluationService::EVENT_FIRST_ACTIVITY,
            'target_minutes' => 60,
        ])->assertCreated()
            ->assertJsonPath('data.module', 'crm_lead')
            ->assertJsonPath('data.target_minutes', 60);

        $this->assertDatabaseHas('operational_sla_rules', [
            'name' => 'تواصل أول مع العميل',
            'module' => 'crm_lead',
        ]);
    }

    public function test_insights_period_7(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)->getJson('/api/operations/insights?period=7')
            ->assertOk()
            ->assertJsonPath('data.period_days', 7)
            ->assertJsonStructure([
                'data' => [
                    'completed_work',
                    'overdue_rate',
                    'by_department',
                    'by_employee',
                    'project_health',
                    'printing_lateness',
                    'automation',
                    'sla',
                ],
            ]);
    }

    public function test_attention_snooze_hides_item(): void
    {
        $owner = User::factory()->owner()->create();

        $key = 'project_health:project:999';
        OperationalAttentionSnooze::query()->create([
            'user_id' => $owner->id,
            'attention_key' => $key,
            'snoozed_until' => now()->addHour(),
        ]);

        $this->asUser($owner)->postJson('/api/operations/attention/snooze', [
            'attention_key' => 'task_overdue:workspace_task:1',
            'preset' => '1h',
        ])->assertOk()
            ->assertJsonPath('data.attention_key', 'task_overdue:workspace_task:1');

        $response = $this->asUser($owner)->getJson('/api/operations/command-center')->assertOk();
        $attention = $response->json('data.attention') ?? [];
        foreach ($attention as $item) {
            $this->assertNotSame($key, $item['attention_key'] ?? null);
            $this->assertNotSame('task_overdue:workspace_task:1', $item['attention_key'] ?? null);
        }
    }

    public function test_team_dashboard_200_for_owner(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)->getJson('/api/operations/team-dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'today',
                    'overdue',
                    'upcoming',
                    'workload',
                ],
            ]);
    }

    public function test_search_includes_work(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => $customer->id,
        ]);

        Task::factory()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'assigned_to' => $owner->id,
            'title' => 'عمل موحد فريد للاختبار',
            'status' => TaskStatus::Todo->value,
        ]);

        $this->asUser($owner)->getJson('/api/operations/search?q='.urlencode('موحد فريد'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'work',
                    'printing',
                    'approvals',
                ],
            ])
            ->assertJsonFragment(['title' => 'عمل موحد فريد للاختبار']);
    }
}
