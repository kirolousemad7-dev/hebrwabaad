<?php

namespace Tests\Feature;

use App\Enums\PrintingPricingType;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\PrintingRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('auth')->plainTextToken;
    }

    public function test_guest_cannot_access_customer_dashboard(): void
    {
        $this->getJson('/api/customer/dashboard')->assertUnauthorized();
        $this->getJson('/api/customer/projects')->assertUnauthorized();
    }

    public function test_customer_can_access_own_dashboard_with_real_data(): void
    {
        $customer = User::factory()->create(['name' => 'منى']);
        $manager = User::factory()->accountManager()->create(['name' => 'أحمد']);
        $project = Project::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
            'title' => 'هوية المطعم',
            'status' => ProjectStatus::InProgress,
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Completed,
            'assigned_to' => User::factory()->webDeveloper(),
            'created_by' => $manager->id,
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
            'assigned_to' => User::factory()->graphicDesigner(),
            'created_by' => $manager->id,
        ]);
        PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'product_name' => 'فلايرز A5',
            'pricing_type' => PrintingPricingType::QuoteReady,
            'quoted_price' => 180,
            'quoted_at' => now(),
        ]);

        $response = $this->withToken($this->tokenFor($customer))
            ->getJson('/api/customer/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.customer.name', 'منى')
            ->assertJsonPath('data.customer.email', $customer->email)
            ->assertJsonPath('data.summary.projects.available', true)
            ->assertJsonPath('data.summary.projects.value', 1)
            ->assertJsonPath('data.summary.projects.secondary.active', 1)
            ->assertJsonPath('data.summary.requests.available', true)
            ->assertJsonPath('data.summary.needs_attention.value', 1)
            ->assertJsonPath('data.summary.orders.available', true)
            ->assertJsonPath('data.summary.messages.available', true)
            ->assertJsonPath('data.summary.messages.value', 0)
            ->assertJsonCount(0, 'data.messages')
            ->assertJsonPath('data.summary.files.available', true)
            ->assertJsonPath('data.summary.files.value', 0)
            ->assertJsonPath('data.summary.notifications.available', true)
            ->assertJsonPath('data.files.available', true)
            ->assertJsonCount(0, 'data.files.items')
            ->assertJsonPath('data.notifications.available', true)
            ->assertJsonCount(0, 'data.notifications.items')
            ->assertJsonPath('data.projects.0.title', 'هوية المطعم')
            ->assertJsonPath('data.projects.0.account_manager.name', 'أحمد')
            ->assertJsonMissingPath('data.customer.password')
            ->assertJsonMissingPath('data.customer.role')
            ->assertJsonMissingPath('data.customer.is_active')
            ->assertJsonMissingPath('data.projects.0.customer');

        $payload = $response->json('data.customer');
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('role', $payload);
        $this->assertArrayNotHasKey('is_active', $payload);
        $this->assertEqualsWithDelta(50, $response->json('data.projects.0.progress.percent'), 0.1);
        $this->assertNotEmpty($response->json('data.activity'));
    }

    public function test_customer_cannot_see_another_customers_project_or_request(): void
    {
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $projectB = Project::factory()->create([
            'customer_id' => $customerB->id,
            'title' => 'سر العميل ب',
        ]);
        PrintingRequest::factory()->create([
            'user_id' => $customerB->id,
            'product_name' => 'طلب سري',
        ]);

        $this->withToken($this->tokenFor($customerA))
            ->getJson('/api/customer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.projects.value', 0)
            ->assertJsonPath('data.summary.requests.value', 0)
            ->assertJsonCount(0, 'data.projects')
            ->assertJsonCount(0, 'data.requests');

        $this->withToken($this->tokenFor($customerA))
            ->getJson('/api/customer/projects/'.$projectB->id)
            ->assertForbidden();

        $this->withToken($this->tokenFor($customerA))
            ->getJson('/api/printing-requests/'.PrintingRequest::query()->where('user_id', $customerB->id)->value('id'))
            ->assertForbidden();
    }

    public function test_customer_can_view_own_project_and_not_workspace_project_api(): void
    {
        $customer = User::factory()->create();
        $project = Project::factory()->create([
            'customer_id' => $customer->id,
            'title' => 'مشروعي',
        ]);

        $this->withToken($this->tokenFor($customer))
            ->getJson('/api/customer/projects/'.$project->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'مشروعي')
            ->assertJsonMissingPath('data.customer');

        $this->withToken($this->tokenFor($customer))
            ->getJson('/api/workspace/projects')
            ->assertForbidden();

        $this->withToken($this->tokenFor($customer))
            ->getJson('/api/workspace/projects/'.$project->id)
            ->assertForbidden();

        $this->withToken($this->tokenFor($customer))
            ->getJson('/api/workspace')
            ->assertForbidden();

        $this->withToken($this->tokenFor($customer))
            ->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }

    public function test_employee_and_owner_cannot_access_customer_dashboard(): void
    {
        $employee = User::factory()->webDeveloper()->create();
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();

        $this->withToken($this->tokenFor($employee))->getJson('/api/customer/dashboard')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($this->tokenFor($owner))->getJson('/api/customer/dashboard')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($this->tokenFor($manager))->getJson('/api/customer/projects')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($this->tokenFor($owner))
            ->getJson('/api/admin/dashboard')
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($this->tokenFor($employee))
            ->getJson('/api/workspace')
            ->assertOk();
    }

    public function test_inactive_customer_cannot_access_dashboard(): void
    {
        $customer = User::factory()->inactive()->create();

        $this->withToken($this->tokenFor($customer))
            ->getJson('/api/customer/dashboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');
    }

    public function test_empty_dashboard_does_not_invent_orders_or_messages(): void
    {
        $customer = User::factory()->create();

        $this->withToken($this->tokenFor($customer))
            ->getJson('/api/customer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.projects.value', 0)
            ->assertJsonPath('data.summary.orders.available', true)
            ->assertJsonPath('data.summary.orders.value', 0)
            ->assertJsonPath('data.summary.messages.available', true)
            ->assertJsonPath('data.summary.messages.value', 0)
            ->assertJsonCount(0, 'data.messages')
            ->assertJsonPath('data.files.available', true)
            ->assertJsonCount(0, 'data.files.items')
            ->assertJsonPath('data.notifications.available', true)
            ->assertJsonCount(0, 'data.notifications.items')
            ->assertJsonCount(0, 'data.projects')
            ->assertJsonCount(0, 'data.requests')
            ->assertJsonCount(0, 'data.orders')
            ->assertJsonCount(0, 'data.activity');
    }
}
