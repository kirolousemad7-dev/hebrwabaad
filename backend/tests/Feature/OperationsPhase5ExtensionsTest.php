<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Operations\OperationsExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsPhase5ExtensionsTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_operations_settings_returns_200_for_owner(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)->getJson('/api/operations/settings')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'business_calendar',
                    'printing_approaching_days',
                    'webhook_count',
                    'automation_failure_24h',
                ],
            ]);

        $this->asUser($owner)->putJson('/api/operations/settings', [
            'printing_approaching_days' => 3,
        ])->assertOk()
            ->assertJsonPath('data.printing_approaching_days', 3);
    }

    public function test_command_center_includes_system_health_for_owner_not_am(): void
    {
        $owner = User::factory()->owner()->create();
        $am = User::factory()->accountManager()->create();

        $ownerResponse = $this->asUser($owner)->getJson('/api/operations/command-center')->assertOk();
        $ownerResponse->assertJsonStructure([
            'data' => [
                'system_health' => [
                    'failed_automations_24h',
                    'failed_webhooks_24h',
                ],
                'operational' => [
                    'printing_lifecycle_issues',
                    'sla_breaches',
                    'overdue_unified_work',
                ],
            ],
        ]);

        $amResponse = $this->asUser($am)->getJson('/api/operations/command-center')->assertOk();
        $this->assertArrayNotHasKey('system_health', $amResponse->json('data') ?? []);
        $amResponse->assertJsonStructure([
            'data' => [
                'operational' => [
                    'printing_lifecycle_issues',
                    'sla_breaches',
                    'overdue_unified_work',
                ],
            ],
        ]);
    }

    public function test_export_work_csv_escapes_formula_injection(): void
    {
        $owner = User::factory()->owner()->create();
        $project = Project::factory()->create(['account_manager_id' => $owner->id]);

        Task::factory()->create([
            'title' => '=CMD|"calc"',
            'assigned_to' => $owner->id,
            'created_by' => $owner->id,
            'project_id' => $project->id,
            'status' => TaskStatus::Todo->value,
            'deadline' => now()->toDateString(),
        ]);

        $response = $this->asUser($owner)->get('/api/operations/export/work.csv');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString("'=CMD", $body);
        $this->assertStringNotContainsString("\n=CMD", $body);
    }

    public function test_export_service_escape_formula_helper(): void
    {
        $service = app(OperationsExportService::class);

        $this->assertSame("'=1+1", $service->escapeFormula('=1+1'));
        $this->assertSame("'+123", $service->escapeFormula('+123'));
        $this->assertSame("'-1", $service->escapeFormula('-1'));
        $this->assertSame("'@sum", $service->escapeFormula('@sum'));
        $this->assertSame('safe', $service->escapeFormula('safe'));
    }

    public function test_health_check_command_runs(): void
    {
        $this->artisan('operations:health-check')
            ->assertSuccessful();

        $this->artisan('operations:health-check', ['--strict' => true])
            ->assertSuccessful();
    }

    public function test_project_workspace_includes_unified_work_counts(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $project = Project::factory()->create([
            'account_manager_id' => $owner->id,
            'customer_id' => $customer->id,
            'title' => 'Workspace unified counts',
        ]);

        Task::factory()->create([
            'title' => 'Open task',
            'project_id' => $project->id,
            'assigned_to' => $owner->id,
            'created_by' => $owner->id,
            'status' => TaskStatus::Todo->value,
            'deadline' => now()->addDays(2)->toDateString(),
        ]);

        Task::factory()->create([
            'title' => 'Overdue task',
            'project_id' => $project->id,
            'assigned_to' => $owner->id,
            'created_by' => $owner->id,
            'status' => TaskStatus::Todo->value,
            'deadline' => now()->subDays(2)->toDateString(),
        ]);

        $response = $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'unified_work' => [
                        'open',
                        'overdue',
                    ],
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, (int) $response->json('data.unified_work.open'));
        $this->assertGreaterThanOrEqual(1, (int) $response->json('data.unified_work.overdue'));
    }
}
