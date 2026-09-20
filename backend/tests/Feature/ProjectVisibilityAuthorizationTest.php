<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\ManagedFile;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectVisibilityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_owner_sees_am_created_project_without_membership(): void
    {
        $owner = User::factory()->owner()->create();
        $am = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $project = Project::factory()->create([
            'account_manager_id' => $am->id,
            'customer_id' => $customer->id,
            'title' => 'مشروع AM فقط',
            'status' => ProjectStatus::InProgress,
            'deadline' => now()->subDay()->toDateString(),
        ]);

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
        ]);

        $list = $this->asUser($owner)->getJson('/api/workspace/projects')->assertOk()->json('data.items');
        $this->assertTrue(collect($list)->contains(fn (array $row): bool => (int) $row['id'] === $project->id));

        $this->asUser($owner)->getJson('/api/operations/projects')->assertOk();
        $ops = $this->asUser($owner)->getJson('/api/operations/projects')->json('data.items');
        $this->assertTrue(collect($ops)->contains(fn (array $row): bool => (int) $row['id'] === $project->id));

        $this->asUser($owner)->getJson('/api/workspace/projects/'.$project->id)->assertOk()
            ->assertJsonPath('data.id', $project->id);

        $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')->assertOk();
        $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/timeline')->assertOk();
        $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/tasks')->assertOk();
        $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/milestones')->assertOk();

        $from = now()->subDay()->toDateString();
        $to = now()->addMonth()->toDateString();
        $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/calendar-items?from='.$from.'&to='.$to)
            ->assertOk();

        $center = $this->asUser($owner)->getJson('/api/operations/command-center')->assertOk()->json('data');
        $healthIds = collect($center['project_health'] ?? [])->pluck('id')->all();
        $this->assertContains($project->id, $healthIds);
    }

    public function test_am_sees_own_project_and_is_isolated_from_other_am(): void
    {
        $amA = User::factory()->accountManager()->create();
        $amB = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $projectA = Project::factory()->create([
            'account_manager_id' => $amA->id,
            'customer_id' => $customer->id,
            'title' => 'مشروع A',
        ]);
        $projectB = Project::factory()->create([
            'account_manager_id' => $amB->id,
            'customer_id' => $customer->id,
            'title' => 'مشروع B',
            'status' => ProjectStatus::InProgress,
            'deadline' => now()->subDay()->toDateString(),
        ]);

        $listA = collect($this->asUser($amA)->getJson('/api/workspace/projects')->assertOk()->json('data.items'));
        $this->assertTrue($listA->contains(fn (array $row): bool => (int) $row['id'] === $projectA->id));
        $this->assertFalse($listA->contains(fn (array $row): bool => (int) $row['id'] === $projectB->id));

        $this->asUser($amA)->getJson('/api/workspace/projects/'.$projectA->id)->assertOk();
        $this->asUser($amA)->getJson('/api/workspace/projects/'.$projectB->id)->assertForbidden();

        $this->asUser($amA)->putJson('/api/workspace/projects/'.$projectB->id, [
            'title' => 'اختراق',
            'customer_id' => $customer->id,
            'status' => ProjectStatus::InProgress->value,
        ])->assertForbidden();

        $this->asUser($amA)->putJson('/api/operations/projects/'.$projectB->id.'/members', [
            'members' => [['user_id' => $amA->id, 'role' => 'member']],
        ])->assertForbidden();

        $center = $this->asUser($amA)->getJson('/api/operations/command-center')->assertOk()->json('data');
        $healthIds = collect($center['project_health'] ?? [])->pluck('id')->all();
        $this->assertNotContains($projectB->id, $healthIds);
    }

    public function test_am_member_of_other_project_can_view_but_not_update(): void
    {
        $amA = User::factory()->accountManager()->create();
        $amB = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $project = Project::factory()->create([
            'account_manager_id' => $amA->id,
            'customer_id' => $customer->id,
        ]);

        ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $amB->id,
            'role' => 'member',
        ]);

        $list = collect($this->asUser($amB)->getJson('/api/workspace/projects')->assertOk()->json('data.items'));
        $this->assertTrue($list->contains(fn (array $row): bool => (int) $row['id'] === $project->id));

        $this->asUser($amB)->getJson('/api/workspace/projects/'.$project->id)->assertOk();
        $this->asUser($amB)->putJson('/api/workspace/projects/'.$project->id, [
            'title' => 'لا يجب',
            'customer_id' => $customer->id,
            'status' => ProjectStatus::Planning->value,
        ])->assertForbidden();
        $this->asUser($amB)->putJson('/api/operations/projects/'.$project->id.'/members', [
            'members' => [],
        ])->assertForbidden();
    }

    public function test_project_member_without_task_appears_in_list_and_can_show(): void
    {
        $am = User::factory()->accountManager()->create();
        $member = User::factory()->webDeveloper()->create();
        $stranger = User::factory()->graphicDesigner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $project = Project::factory()->create([
            'account_manager_id' => $am->id,
            'customer_id' => $customer->id,
            'title' => 'عضو بلا مهمة',
        ]);

        ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $this->assertSame(0, Task::query()->where('project_id', $project->id)->where('assigned_to', $member->id)->count());

        $list = collect($this->asUser($member)->getJson('/api/workspace/projects')->assertOk()->json('data.items'));
        $this->assertTrue($list->contains(fn (array $row): bool => (int) $row['id'] === $project->id));

        $this->asUser($member)->getJson('/api/workspace/projects/'.$project->id)->assertOk();

        $strangerList = collect($this->asUser($stranger)->getJson('/api/workspace/projects')->assertOk()->json('data.items'));
        $this->assertFalse($strangerList->contains(fn (array $row): bool => (int) $row['id'] === $project->id));
        $this->asUser($stranger)->getJson('/api/workspace/projects/'.$project->id)->assertForbidden();
    }

    public function test_project_calendar_respects_item_authorization_and_project_bound(): void
    {
        $owner = User::factory()->owner()->create();
        $am = User::factory()->accountManager()->create();
        $designer = User::factory()->graphicDesigner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $projectA = Project::factory()->create([
            'account_manager_id' => $am->id,
            'customer_id' => $customer->id,
        ]);
        $projectB = Project::factory()->create([
            'account_manager_id' => $am->id,
            'customer_id' => $customer->id,
        ]);

        ProjectMember::query()->create([
            'project_id' => $projectA->id,
            'user_id' => $designer->id,
            'role' => 'member',
        ]);

        $visibleToDesigner = CalendarItem::factory()->create([
            'created_by' => $designer->id,
            'related_type' => 'project',
            'related_id' => $projectA->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
            'title' => 'حدث المصمم',
        ]);
        $visibleToDesigner->assignees()->sync([$designer->id]);

        $otherProjectItem = CalendarItem::factory()->create([
            'created_by' => $am->id,
            'related_type' => 'project',
            'related_id' => $projectB->id,
            'starts_at' => now()->addDay()->setTime(12, 0),
            'ends_at' => now()->addDay()->setTime(13, 0),
            'title' => 'حدث مشروع آخر',
        ]);

        $teamOnlyOnA = CalendarItem::factory()->create([
            'created_by' => $am->id,
            'related_type' => 'project',
            'related_id' => $projectA->id,
            'starts_at' => now()->addDay()->setTime(14, 0),
            'ends_at' => now()->addDay()->setTime(15, 0),
            'title' => 'حدث داخلي للفريق',
        ]);

        $from = now()->toDateString();
        $to = now()->addDays(7)->toDateString();
        $query = '?from='.$from.'&to='.$to;

        $ownerItems = collect($this->asUser($owner)
            ->getJson('/api/operations/projects/'.$projectA->id.'/calendar-items'.$query)
            ->assertOk()
            ->json('data.items'));
        $this->assertTrue($ownerItems->contains(fn (array $row): bool => (int) $row['id'] === $visibleToDesigner->id));
        $this->assertTrue($ownerItems->contains(fn (array $row): bool => (int) $row['id'] === $teamOnlyOnA->id));
        $this->assertFalse($ownerItems->contains(fn (array $row): bool => (int) $row['id'] === $otherProjectItem->id));

        $designerItems = collect($this->asUser($designer)
            ->getJson('/api/operations/projects/'.$projectA->id.'/calendar-items'.$query)
            ->assertOk()
            ->json('data.items'));
        $this->assertTrue($designerItems->contains(fn (array $row): bool => (int) $row['id'] === $visibleToDesigner->id));
        $this->assertFalse($designerItems->contains(fn (array $row): bool => (int) $row['id'] === $teamOnlyOnA->id));
        $this->assertFalse($designerItems->contains(fn (array $row): bool => (int) $row['id'] === $otherProjectItem->id));

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $this->asUser($customer)
            ->getJson('/api/operations/projects/'.$projectA->id.'/calendar-items'.$query)
            ->assertForbidden();
    }

    public function test_nested_task_and_milestone_mismatch_are_denied(): void
    {
        $owner = User::factory()->owner()->create();
        $am = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $projectA = Project::factory()->create(['account_manager_id' => $am->id, 'customer_id' => $customer->id]);
        $projectB = Project::factory()->create(['account_manager_id' => $am->id, 'customer_id' => $customer->id]);

        $taskB = Task::factory()->create([
            'project_id' => $projectB->id,
            'created_by' => $am->id,
            'assigned_to' => User::factory()->webDeveloper(),
            'status' => TaskStatus::Todo,
        ]);

        $this->asUser($owner)->putJson('/api/operations/projects/'.$projectA->id.'/tasks/'.$taskB->id, [
            'title' => 'محاولة نقل',
        ])->assertNotFound();

        $milestoneB = ProjectMilestone::query()->create([
            'project_id' => $projectB->id,
            'title' => 'معلم B',
            'status' => ProjectMilestone::STATUS_PENDING,
            'sort_order' => 1,
        ]);

        $this->asUser($owner)->putJson('/api/operations/projects/'.$projectA->id.'/milestones/'.$milestoneB->id, [
            'title' => 'محاولة',
        ])->assertNotFound();
    }

    public function test_nested_file_from_other_project_is_forbidden_for_unrelated_member(): void
    {
        Storage::fake('local');

        $am = User::factory()->accountManager()->create();
        $member = User::factory()->webDeveloper()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $projectA = Project::factory()->create(['account_manager_id' => $am->id, 'customer_id' => $customer->id]);
        $projectB = Project::factory()->create(['account_manager_id' => $am->id, 'customer_id' => $customer->id]);

        ProjectMember::query()->create([
            'project_id' => $projectA->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $fileB = ManagedFile::factory()->create([
            'project_id' => $projectB->id,
            'uploaded_by' => $am->id,
            'disk' => 'local',
            'path' => 'files/secret-b.txt',
        ]);
        Storage::disk('local')->put($fileB->path, 'secret');

        $this->asUser($member)->getJson('/api/workspace/files/'.$fileB->id)->assertForbidden();
    }

    public function test_customer_cannot_access_another_customers_project(): void
    {
        $customerA = User::factory()->create(['role' => UserRole::Customer]);
        $customerB = User::factory()->create(['role' => UserRole::Customer]);
        $am = User::factory()->accountManager()->create();
        $projectB = Project::factory()->create([
            'customer_id' => $customerB->id,
            'account_manager_id' => $am->id,
        ]);

        $this->asUser($customerA)->getJson('/api/customer/projects')->assertOk();
        $this->asUser($customerA)->getJson('/api/customer/projects/'.$projectB->id)->assertForbidden();
        $this->asUser($customerB)->getJson('/api/customer/projects/'.$projectB->id)->assertOk();
    }
}
