<?php

namespace Tests\Feature;

use App\Enums\CatalogPricingMode;
use App\Enums\OrderStatus;
use App\Enums\ServiceCategory;
use App\Enums\TaskStatus;
use App\Models\Department;
use App\Models\Order;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use App\Services\Catalog\CustomPackageOperationsService;
use App\Services\Catalog\RevisionScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomPackageOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->accountManager()->create();
    }

    public function test_confirming_custom_package_creates_one_project_and_tasks_per_item(): void
    {
        $customer = User::factory()->create();
        $design = Department::query()->create([
            'name' => 'التصميم',
            'slug' => 'design',
            'is_active' => true,
        ]);
        $video = Department::query()->create([
            'name' => 'الفيديو',
            'slug' => 'video',
            'is_active' => true,
        ]);

        $a = $this->service('content-strategy', 'استراتيجية محتوى', $design->id, 'إعداد :name');
        $b = $this->service('reels', 'ريلز', $video->id, null);

        $orderId = $this->asUser($customer)
            ->postJson('/api/customer/orders/custom-package', [
                'items' => [
                    ['service_id' => $a->id, 'quantity' => 1],
                    ['service_id' => $b->id, 'quantity' => 8],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $order = Order::query()->findOrFail($orderId);
        $this->assertNull($order->project_id);

        $this->asUser($this->manager)
            ->patchJson('/api/orders/'.$order->id.'/status', ['status' => OrderStatus::Confirmed->value])
            ->assertOk();

        $order->refresh();
        $this->assertNotNull($order->project_id);
        $this->assertSame(1, Order::query()->where('project_id', $order->project_id)->count());
        $this->assertDatabaseCount('tasks', 2);

        $tasks = Task::query()->where('project_id', $order->project_id)->orderBy('id')->get();
        $this->assertSame('إعداد استراتيجية محتوى', $tasks[0]->title);
        $this->assertSame('تنفيذ 8 × ريلز', $tasks[1]->title);
        $this->assertSame($design->id, $tasks[0]->department_id);
        $this->assertSame($video->id, $tasks[1]->department_id);
        $this->assertNull($tasks[0]->assigned_to);
        $this->assertNull($tasks[1]->assigned_to);
        $this->assertSame(CustomPackageOperationsService::SOURCE, $tasks[0]->source);
        $this->assertSame(TaskStatus::Todo, $tasks[0]->status);
    }

    public function test_confirmation_is_idempotent_and_does_not_duplicate_tasks(): void
    {
        $customer = User::factory()->create();
        $service = $this->service('logo-design', 'شعار', null, null);

        $orderId = $this->asUser($customer)
            ->postJson('/api/customer/orders/custom-package', [
                'items' => [['service_id' => $service->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.id');

        $ops = app(CustomPackageOperationsService::class);
        $order = Order::query()->findOrFail($orderId);

        $first = $ops->ensureOperationalWork($order);
        $second = $ops->ensureOperationalWork($order->fresh(['items.service', 'project']));

        $this->assertSame(1, $first['tasks_created']);
        $this->assertSame(0, $second['tasks_created']);
        $this->assertSame(1, $second['tasks_existing']);
        $this->assertSame($first['project']?->id, $second['project']?->id);
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_inactive_service_cannot_be_ordered_in_custom_package(): void
    {
        $customer = User::factory()->create();
        $service = $this->service('hidden-service', 'مخفية', null, null);
        $service->update(['is_active' => false]);

        $this->asUser($customer)
            ->postJson('/api/customer/orders/custom-package', [
                'items' => [['service_id' => $service->id, 'quantity' => 1]],
            ])
            ->assertUnprocessable();
    }

    public function test_customer_order_payload_does_not_expose_department_names(): void
    {
        $customer = User::factory()->create();
        $department = Department::query()->create([
            'name' => 'قسم سري',
            'slug' => 'secret-dept',
            'is_active' => true,
        ]);
        $service = $this->service('brand-identity', 'هوية', $department->id, null);

        $payload = $this->asUser($customer)
            ->postJson('/api/customer/orders/custom-package', [
                'items' => [['service_id' => $service->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data');

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('قسم سري', $encoded);
        $this->assertArrayNotHasKey('department', $payload['items'][0]['service'] ?? []);
    }

    public function test_customer_project_hides_department_and_shows_service_progress(): void
    {
        $customer = User::factory()->create();
        $department = Department::query()->create([
            'name' => 'قسم داخلي',
            'slug' => 'internal-dept',
            'is_active' => true,
        ]);
        $service = $this->service('strategy', 'استراتيجية', $department->id, null);
        $service->update([
            'requires_customer_approval' => true,
            'revision_rounds' => 2,
        ]);

        $orderId = $this->asUser($customer)
            ->postJson('/api/customer/orders/custom-package', [
                'items' => [['service_id' => $service->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.id');

        $order = Order::query()->findOrFail($orderId);
        app(CustomPackageOperationsService::class)->ensureOperationalWork($order);

        $projectId = $order->fresh()->project_id;
        $this->assertNotNull($projectId);

        $payload = $this->asUser($customer)
            ->getJson('/api/customer/projects/'.$projectId)
            ->assertOk()
            ->json('data');

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('قسم داخلي', $encoded);
        $this->assertArrayHasKey('service_progress', $payload);
        $this->assertSame('استراتيجية', $payload['service_progress'][0]['service_name']);
        $this->assertSame('لم يبدأ', $payload['service_progress'][0]['status_label']);
        $this->assertArrayNotHasKey('department_name', $payload['service_progress'][0]);
    }

    public function test_revision_scope_requires_addon_when_exhausted(): void
    {
        $service = $this->service('design-pack', 'تصاميم', null, null);
        $service->update(['revision_rounds' => 1]);

        $customer = User::factory()->create();
        $orderId = $this->asUser($customer)
            ->postJson('/api/customer/orders/custom-package', [
                'items' => [['service_id' => $service->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.id');

        $order = Order::query()->findOrFail($orderId);
        app(CustomPackageOperationsService::class)->ensureOperationalWork($order);
        $item = $order->items()->firstOrFail();

        Task::query()->where('order_item_id', $item->id)->update(['status' => TaskStatus::Revision->value]);

        $scope = app(RevisionScopeService::class)->evaluate($item->fresh(['service']));
        $this->assertFalse($scope['allowed']);
        $this->assertTrue($scope['requires_addon']);
        $this->assertSame('extra-revision-round', $scope['addon_slug']);
    }

    public function test_unmapped_service_still_creates_unassigned_task(): void
    {
        $customer = User::factory()->create();
        $service = $this->service('orphan-service', 'خدمة بلا قسم', null, null);

        $orderId = $this->asUser($customer)
            ->postJson('/api/customer/orders/custom-package', [
                'items' => [['service_id' => $service->id, 'quantity' => 3]],
            ])
            ->assertCreated()
            ->json('data.id');

        $order = Order::query()->findOrFail($orderId);
        $result = app(CustomPackageOperationsService::class)->ensureOperationalWork($order);

        $this->assertSame(1, $result['tasks_created']);
        $task = Task::query()->where('project_id', $result['project']->id)->firstOrFail();
        $this->assertNull($task->department_id);
        $this->assertNull($task->assigned_to);
        $this->assertSame('تنفيذ 3 × خدمة بلا قسم', $task->title);
    }

    private function service(string $slug, string $name, ?int $departmentId, ?string $template): Service
    {
        return Service::factory()->create([
            'name' => $name,
            'slug' => $slug,
            'category' => ServiceCategory::Production,
            'pricing_mode' => CatalogPricingMode::Fixed,
            'base_price' => 100,
            'currency' => 'SAR',
            'is_active' => true,
            'is_public' => true,
            'department_id' => $departmentId,
            'task_title_template' => $template,
        ]);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('auth')->plainTextToken;
    }

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($this->tokenFor($user));
    }
}
