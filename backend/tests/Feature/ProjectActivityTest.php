<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\FileService;
use App\Services\ProjectService;
use App\Services\TaskService;
use App\Support\ProjectActivityAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectActivityTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_owner_can_see_activities_for_any_project_without_membership(): void
    {
        $owner = User::factory()->owner()->create();
        $am = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $project = Project::factory()->create([
            'account_manager_id' => $am->id,
            'customer_id' => $customer->id,
        ]);

        ProjectActivity::factory()->create([
            'project_id' => $project->id,
            'actor_user_id' => $am->id,
            'action' => ProjectActivityAction::TASK_CREATED,
            'description' => 'Internal task',
            'is_client_visible' => false,
        ]);

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
        ]);

        $this->asUser($owner)
            ->getJson('/api/workspace/projects/'.$project->id.'/activities')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.action', ProjectActivityAction::TASK_CREATED);
    }

    public function test_account_manager_sees_authorized_project_activities_only(): void
    {
        $amA = User::factory()->accountManager()->create();
        $amB = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $projectA = Project::factory()->create([
            'account_manager_id' => $amA->id,
            'customer_id' => $customer->id,
        ]);
        $projectB = Project::factory()->create([
            'account_manager_id' => $amB->id,
            'customer_id' => $customer->id,
        ]);

        ProjectActivity::factory()->create([
            'project_id' => $projectA->id,
            'actor_user_id' => $amA->id,
            'action' => ProjectActivityAction::PROJECT_UPDATED,
        ]);
        ProjectActivity::factory()->create([
            'project_id' => $projectB->id,
            'actor_user_id' => $amB->id,
            'action' => ProjectActivityAction::PROJECT_UPDATED,
        ]);

        $this->asUser($amA)
            ->getJson('/api/workspace/projects/'.$projectA->id.'/activities')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->asUser($amA)
            ->getJson('/api/workspace/projects/'.$projectB->id.'/activities')
            ->assertForbidden();
    }

    public function test_team_member_sees_only_authorized_project_activities(): void
    {
        $am = User::factory()->accountManager()->create();
        $member = User::factory()->webDeveloper()->create();
        $stranger = User::factory()->graphicDesigner()->create();
        $customer = User::factory()->create();
        $project = Project::factory()->create([
            'account_manager_id' => $am->id,
            'customer_id' => $customer->id,
        ]);
        $other = Project::factory()->create([
            'account_manager_id' => $am->id,
            'customer_id' => $customer->id,
        ]);

        ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        ProjectActivity::factory()->create([
            'project_id' => $project->id,
            'actor_user_id' => $am->id,
            'action' => ProjectActivityAction::TASK_CREATED,
        ]);
        ProjectActivity::factory()->create([
            'project_id' => $other->id,
            'actor_user_id' => $am->id,
            'action' => ProjectActivityAction::TASK_CREATED,
        ]);

        $this->asUser($member)
            ->getJson('/api/workspace/projects/'.$project->id.'/activities')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->asUser($member)
            ->getJson('/api/workspace/projects/'.$other->id.'/activities')
            ->assertForbidden();

        $this->asUser($stranger)
            ->getJson('/api/workspace/projects/'.$project->id.'/activities')
            ->assertForbidden();
    }

    public function test_customer_sees_only_own_project_client_safe_activities(): void
    {
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $am = User::factory()->accountManager()->create();
        $projectA = Project::factory()->create([
            'customer_id' => $customerA->id,
            'account_manager_id' => $am->id,
        ]);
        $projectB = Project::factory()->create([
            'customer_id' => $customerB->id,
            'account_manager_id' => $am->id,
        ]);

        ProjectActivity::factory()->clientVisible()->create([
            'project_id' => $projectA->id,
            'actor_user_id' => $am->id,
            'action' => ProjectActivityAction::PROJECT_STATUS_CHANGED,
            'description' => 'Status updated',
            'metadata' => ['old_status' => 'PLANNING', 'new_status' => 'IN_PROGRESS', 'internal_note' => 'secret'],
        ]);

        ProjectActivity::factory()->create([
            'project_id' => $projectA->id,
            'actor_user_id' => $am->id,
            'action' => ProjectActivityAction::PROJECT_MEMBER_ADDED,
            'description' => 'Internal member add',
            'is_client_visible' => false,
            'metadata' => ['member_name' => 'Hidden'],
        ]);

        ProjectActivity::factory()->clientVisible()->create([
            'project_id' => $projectB->id,
            'actor_user_id' => $am->id,
            'action' => ProjectActivityAction::PROJECT_STATUS_CHANGED,
            'is_client_visible' => true,
        ]);

        $response = $this->asUser($customerA)
            ->getJson('/api/customer/projects/'.$projectA->id.'/activities')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.action', ProjectActivityAction::PROJECT_STATUS_CHANGED)
            ->assertJsonMissingPath('data.items.0.metadata');

        $this->assertSame('staff', $response->json('data.items.0.actor.type'));
        $this->assertNull($response->json('data.items.0.actor.id'));

        $this->asUser($customerA)
            ->getJson('/api/customer/projects/'.$projectB->id.'/activities')
            ->assertForbidden();
    }

    public function test_project_creation_creates_activity(): void
    {
        $am = User::factory()->accountManager()->create();
        $customer = User::factory()->create();

        $project = app(ProjectService::class)->create($am, [
            'title' => 'New Project',
            'customer_id' => $customer->id,
            'status' => ProjectStatus::Planning->value,
        ]);

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'action' => ProjectActivityAction::PROJECT_CREATED,
            'actor_user_id' => $am->id,
        ]);
        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'action' => ProjectActivityAction::PROJECT_ACCOUNT_MANAGER_ASSIGNED,
        ]);
    }

    public function test_task_creation_and_status_change_create_activities(): void
    {
        $am = User::factory()->accountManager()->create();
        $dev = User::factory()->webDeveloper()->create();
        $customer = User::factory()->create();
        $project = Project::factory()->create([
            'account_manager_id' => $am->id,
            'customer_id' => $customer->id,
        ]);

        $task = app(TaskService::class)->create($am, [
            'title' => 'Build landing',
            'project_id' => $project->id,
            'assigned_to' => $dev->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Todo->value,
        ]);

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'action' => ProjectActivityAction::TASK_CREATED,
            'entity_id' => $task->id,
        ]);

        app(TaskService::class)->updateStatus($dev, $task, TaskStatus::InProgress);

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'action' => ProjectActivityAction::TASK_STATUS_CHANGED,
            'entity_id' => $task->id,
        ]);
    }

    public function test_member_assignment_creates_activity(): void
    {
        $am = User::factory()->accountManager()->create();
        $member = User::factory()->webDeveloper()->create();
        $customer = User::factory()->create();
        $project = Project::factory()->create([
            'account_manager_id' => $am->id,
            'customer_id' => $customer->id,
        ]);

        app(ProjectService::class)->syncMembers($am, $project, [
            ['user_id' => $member->id, 'role' => 'member'],
        ]);

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'action' => ProjectActivityAction::PROJECT_MEMBER_ADDED,
            'entity_id' => $member->id,
        ]);
    }

    public function test_file_upload_and_client_visibility_change_create_activities(): void
    {
        Storage::fake('local');
        $am = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $project = Project::factory()->create([
            'account_manager_id' => $am->id,
            'customer_id' => $customer->id,
        ]);

        $file = app(FileService::class)->store(
            $am,
            UploadedFile::fake()->create('brief.pdf', 40, 'application/pdf'),
            ['project_id' => $project->id],
        );

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'action' => ProjectActivityAction::FILE_UPLOADED,
            'entity_id' => $file->id,
            'is_client_visible' => 0,
        ]);

        app(FileService::class)->updateClientVisibility($am, $file, true);

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'action' => ProjectActivityAction::FILE_CLIENT_VISIBILITY_CHANGED,
            'entity_id' => $file->id,
            'is_client_visible' => 1,
        ]);

        $this->assertTrue($file->fresh()->is_client_visible);
    }

    public function test_rolled_back_business_operation_does_not_persist_activity(): void
    {
        $am = User::factory()->accountManager()->create();
        $customer = User::factory()->create();

        try {
            DB::transaction(function () use ($am, $customer): void {
                app(ProjectService::class)->create($am, [
                    'title' => 'Will rollback',
                    'customer_id' => $customer->id,
                    'status' => ProjectStatus::Planning->value,
                ]);

                throw new \RuntimeException('force rollback');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, Project::query()->where('title', 'Will rollback')->count());
        $this->assertSame(0, ProjectActivity::query()->where('action', ProjectActivityAction::PROJECT_CREATED)->count());
    }

    public function test_activity_endpoint_paginates_newest_first(): void
    {
        $owner = User::factory()->owner()->create();
        $am = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $project = Project::factory()->create([
            'account_manager_id' => $am->id,
            'customer_id' => $customer->id,
        ]);

        foreach (range(1, 30) as $i) {
            ProjectActivity::factory()->create([
                'project_id' => $project->id,
                'actor_user_id' => $am->id,
                'action' => ProjectActivityAction::TASK_UPDATED,
                'description' => 'Item '.$i,
            ]);
        }

        $page1 = $this->asUser($owner)
            ->getJson('/api/workspace/projects/'.$project->id.'/activities?per_page=25')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 25)
            ->assertJsonPath('data.meta.total', 30)
            ->assertJsonPath('data.meta.last_page', 2)
            ->json('data.items');

        $this->assertCount(25, $page1);
        $this->assertGreaterThan($page1[1]['id'], $page1[0]['id']);

        $this->asUser($owner)
            ->getJson('/api/workspace/projects/'.$project->id.'/activities?page=2&per_page=25')
            ->assertOk()
            ->assertJsonCount(5, 'data.items');
    }

    public function test_nested_project_activity_access_cannot_cross_boundaries(): void
    {
        $am = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $projectA = Project::factory()->create([
            'account_manager_id' => $am->id,
            'customer_id' => $customer->id,
        ]);
        $projectB = Project::factory()->create([
            'account_manager_id' => $am->id,
            'customer_id' => $customer->id,
        ]);

        $activityB = ProjectActivity::factory()->create([
            'project_id' => $projectB->id,
            'actor_user_id' => $am->id,
            'action' => ProjectActivityAction::TASK_CREATED,
        ]);

        $items = $this->asUser($am)
            ->getJson('/api/workspace/projects/'.$projectA->id.'/activities')
            ->assertOk()
            ->json('data.items');

        $ids = array_column($items, 'id');
        $this->assertNotContains($activityB->id, $ids);
    }

    public function test_customer_file_upload_logs_customer_safe_activity(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create();
        $project = Project::factory()->create(['customer_id' => $customer->id]);

        $this->asUser($customer)
            ->post('/api/customer/files', [
                'file' => UploadedFile::fake()->create('mine.pdf', 30, 'application/pdf'),
                'project_id' => $project->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'action' => ProjectActivityAction::CUSTOMER_FILE_UPLOADED,
            'actor_customer_id' => $customer->id,
            'is_client_visible' => 1,
        ]);

        $this->asUser($customer)
            ->getJson('/api/customer/projects/'.$project->id.'/activities')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonMissingPath('data.items.0.metadata');
    }
}
