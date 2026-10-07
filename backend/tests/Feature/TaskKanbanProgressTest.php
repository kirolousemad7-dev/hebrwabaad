<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskKanbanProgressTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_owner_can_set_progress_and_move_task_on_the_kanban(): void
    {
        $owner = User::factory()->owner()->create();
        $assignee = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'title' => 'مهمة اللوحة',
            'assigned_to' => $assignee->id,
            'created_by' => $owner->id,
            'project_id' => $project->id,
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::High,
            'deadline' => now()->addDay()->toDateString(),
        ]);

        $this->asUser($owner)
            ->postJson('/api/operations/work/task:'.$task->id.'/progress', [
                'progress_percent' => 65,
            ])
            ->assertOk()
            ->assertJsonPath('data.progress_percent', 65)
            ->assertJsonPath('data.board_status', 'open');

        $this->asUser($owner)
            ->postJson('/api/operations/work/task:'.$task->id.'/progress', [
                'progress_percent' => 140,
            ])
            ->assertUnprocessable();

        $this->asUser($owner)
            ->postJson('/api/operations/work/task:'.$task->id.'/move', [
                'status' => 'waiting_client',
                'sort_order' => 2,
                'ordered_ids' => ['task:'.$task->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.board_status', 'waiting_client');

        $task->refresh();
        $this->assertSame(TaskStatus::Revision, $task->status);
        $this->assertSame(65, (int) $task->progress_percent);
        $this->assertSame(0, (int) $task->sort_order);

        $board = $this->asUser($owner)
            ->getJson('/api/operations/work/kanban?scope=team')
            ->assertOk()
            ->json('data.columns');

        $waitingIds = array_column($board['waiting_client'], 'id');
        $this->assertContains('task:'.$task->id, $waitingIds);
        $this->assertSame(65, $board['waiting_client'][0]['progress_percent']);
    }

    public function test_revision_tasks_use_the_waiting_client_column_and_overdue_stays_separate(): void
    {
        $owner = User::factory()->owner()->create();
        $assignee = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create();

        $revision = Task::factory()->create([
            'title' => 'بانتظار العميل',
            'assigned_to' => $assignee->id,
            'created_by' => $owner->id,
            'project_id' => $project->id,
            'status' => TaskStatus::Revision,
            'deadline' => now()->addDays(3)->toDateString(),
        ]);
        $overdue = Task::factory()->create([
            'title' => 'متأخرة',
            'assigned_to' => $assignee->id,
            'created_by' => $owner->id,
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
            'deadline' => now()->subDay()->toDateString(),
        ]);

        $board = $this->asUser($owner)
            ->getJson('/api/operations/work/kanban?scope=team')
            ->assertOk()
            ->json('data.columns');

        $this->assertContains('task:'.$revision->id, array_column($board['waiting_client'], 'id'));
        $this->assertNotContains('task:'.$revision->id, array_column($board['review'], 'id'));
        $this->assertContains('task:'.$overdue->id, array_column($board['overdue'], 'id'));
        $this->assertNotContains('task:'.$overdue->id, array_column($board['in_progress'], 'id'));
    }
}
