<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\ManagedFile;
use App\Models\Project;
use App\Models\ProjectReference;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectWorkspaceNotesTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_project_brief_notes_and_task_references_stay_on_that_project(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $designer = User::factory()->graphicDesigner()->create();
        $other = User::factory()->graphicDesigner()->create();
        $customer = User::factory()->create();
        $project = Project::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
            'status' => 'IN_PROGRESS',
        ]);
        $otherProject = Project::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
        ]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'assigned_to' => $designer->id,
            'created_by' => $manager->id,
            'priority' => TaskPriority::Medium,
            'status' => TaskStatus::InProgress,
        ]);
        $foreign = ProjectReference::query()->create([
            'project_id' => $otherProject->id,
            'title' => 'مرجع مشروع آخر',
            'type' => ProjectReference::TYPE_OTHER,
            'created_by' => $owner->id,
        ]);

        $this->asUser($owner)
            ->putJson('/api/operations/projects/'.$project->id.'/brief', [
                'brief' => [
                    'objective' => 'بناء هوية احترافية',
                    'tone' => 'Modern, premium, confident.',
                    'deliverables' => 'Logo refinement',
                ],
                'client_profile' => [
                    'company_name' => 'نوفا للتجارة',
                    'phone' => '0500000000',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.brief.objective', 'بناء هوية احترافية')
            ->assertJsonPath('data.client_profile.company_name', 'نوفا للتجارة');

        $this->asUser($owner)
            ->postJson('/api/operations/projects/'.$project->id.'/notes', [
                'body' => 'ممنوع استخدام اللون الأحمر',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'ممنوع استخدام اللون الأحمر');

        $this->asUser($designer)
            ->postJson('/api/operations/projects/'.$project->id.'/notes', [
                'body' => 'ملاحظة مشروع من موظف',
            ])
            ->assertForbidden();

        $this->asUser($designer)
            ->postJson('/api/operations/projects/'.$project->id.'/notes', [
                'task_id' => $task->id,
                'body' => 'تم تجهيز اتجاه أول',
            ])
            ->assertCreated();

        $this->asUser($other)
            ->postJson('/api/operations/projects/'.$project->id.'/notes', [
                'task_id' => $task->id,
                'body' => 'لا يجب أن تُحفظ',
            ])
            ->assertForbidden();

        $this->asUser($owner)
            ->getJson('/api/operations/projects/'.$project->id.'/notes?task_id='.$task->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.body', 'تم تجهيز اتجاه أول');

        $this->asUser($owner)
            ->getJson('/api/operations/projects/'.$project->id.'/notes')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');

        $reference = ProjectReference::query()->create([
            'project_id' => $project->id,
            'title' => 'اتجاه بصري Minimal',
            'type' => ProjectReference::TYPE_DESIGN,
            'url' => 'http://127.0.0.1:5173/brand/mark.png',
            'created_by' => $owner->id,
        ]);

        $this->asUser($designer)
            ->putJson('/api/operations/projects/'.$project->id.'/tasks/'.$task->id.'/references', [
                'reference_ids' => [$reference->id, $foreign->id],
            ])
            ->assertUnprocessable();

        $this->asUser($designer)
            ->putJson('/api/operations/projects/'.$project->id.'/tasks/'.$task->id.'/references', [
                'reference_ids' => [$reference->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.reference_ids.0', $reference->id);

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'action' => 'note.created',
        ]);
    }

    public function test_project_task_list_counts_notes_and_files_without_separate_count_queries(): void
    {
        $owner = User::factory()->owner()->create();
        $project = Project::factory()->create(['customer_id' => User::factory()->create()->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'assigned_to' => $owner->id,
            'created_by' => $owner->id,
        ]);
        $quiet = Task::factory()->create([
            'project_id' => $project->id,
            'assigned_to' => $owner->id,
            'created_by' => $owner->id,
        ]);

        WorkspaceNote::query()->create([
            'project_id' => $project->id,
            'task_id' => $task->id,
            'user_id' => $owner->id,
            'body' => 'ملاحظة على المهمة',
        ]);
        WorkspaceNote::query()->create([
            'project_id' => $project->id,
            'task_id' => null,
            'user_id' => $owner->id,
            'body' => 'ملاحظة على المشروع فقط',
        ]);
        ManagedFile::factory()->create([
            'uploaded_by' => $owner->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
        ]);
        ManagedFile::factory()->create([
            'uploaded_by' => $owner->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
        ]);
        ManagedFile::factory()->create([
            'uploaded_by' => $owner->id,
            'project_id' => $project->id,
            'task_id' => null,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->asUser($owner)
            ->getJson('/api/operations/projects/'.$project->id.'/tasks')
            ->assertOk();

        $items = collect($response->json('data.items'));
        $counted = $items->firstWhere('id', $task->id);
        $empty = $items->firstWhere('id', $quiet->id);

        $this->assertSame(1, $counted['comments_count'] ?? null);
        $this->assertSame(2, $counted['attachments_count'] ?? null);
        $this->assertSame(0, $empty['comments_count'] ?? null);
        $this->assertSame(0, $empty['attachments_count'] ?? null);

        $standaloneCounts = array_filter(
            DB::getQueryLog(),
            fn (array $row): bool => preg_match('/^select count\(\*\)/i', $row['query']) === 1
                && (str_contains($row['query'], 'workspace_notes') || str_contains($row['query'], 'files')),
        );

        $this->assertCount(0, $standaloneCounts);
        $this->assertTrue(collect(DB::getQueryLog())->contains(
            fn (array $row): bool => str_contains($row['query'], 'comments_count')
                && str_contains($row['query'], 'attachments_count'),
        ));
    }
}
