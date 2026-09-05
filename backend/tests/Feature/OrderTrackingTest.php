<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Package;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_guest_cannot_access_order_apis(): void
    {
        $this->getJson('/api/customer/orders')->assertUnauthorized();
        $this->getJson('/api/customer/orders/1')->assertUnauthorized();
        $this->getJson('/api/orders')->assertUnauthorized();
        $this->getJson('/api/orders/lookups')->assertUnauthorized();
        $this->postJson('/api/orders', ['title' => 'x', 'customer_id' => 1])->assertUnauthorized();
    }

    public function test_account_manager_creates_order_with_reference_and_customer_can_view(): void
    {
        $manager = User::factory()->accountManager()->create(['name' => 'أحمد']);
        $customer = User::factory()->create(['name' => 'منى']);
        $project = Project::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
            'title' => 'متجر إلكتروني',
        ]);
        $service = Service::factory()->create(['name' => 'إنشاء متجر إلكتروني']);
        $package = Package::factory()->create(['name' => 'الباقة التأسيسية']);

        $created = $this->asUser($manager)
            ->postJson('/api/orders', [
                'title' => 'متجر إلكتروني',
                'customer_id' => $customer->id,
                'project_id' => $project->id,
                'service_id' => $service->id,
                'package_id' => $package->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'متجر إلكتروني')
            ->assertJsonPath('data.status', OrderStatus::Received->value)
            ->assertJsonPath('data.status_label', 'تم استلام الطلب')
            ->assertJsonPath('data.progress', 0)
            ->assertJsonPath('data.project.title', 'متجر إلكتروني')
            ->assertJsonPath('data.customer.email', $customer->email)
            ->json('data');

        $this->assertMatchesRegularExpression('/^HEBR-ORD-\d{6}$/', $created['reference']);
        $this->assertArrayNotHasKey('password', $created['customer']);

        $this->asUser($customer)
            ->getJson('/api/customer/orders')
            ->assertOk()
            ->assertJsonPath('data.0.reference', $created['reference'])
            ->assertJsonPath('data.0.progress', 0)
            ->assertJsonMissingPath('data.0.customer')
            ->assertJsonMissingPath('data.0.allowed_transitions');

        $this->asUser($customer)
            ->getJson('/api/customer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.orders.available', true)
            ->assertJsonPath('data.summary.orders.value', 1)
            ->assertJsonPath('data.orders.0.reference', $created['reference']);
    }

    public function test_customer_cannot_see_or_open_another_customers_order(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $orderB = Order::factory()->create([
            'customer_id' => $customerB->id,
            'account_manager_id' => $manager->id,
            'title' => 'طلب سري',
        ]);

        $this->asUser($customerA)
            ->getJson('/api/customer/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->asUser($customerA)
            ->getJson('/api/customer/orders/'.$orderB->id)
            ->assertForbidden();

        $this->asUser($customerA)
            ->getJson('/api/customer/dashboard')
            ->assertOk()
            ->assertJsonCount(0, 'data.orders');
    }

    public function test_customer_cannot_update_order_status(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
        ]);

        $this->asUser($customer)
            ->patchJson('/api/orders/'.$order->id.'/status', [
                'status' => OrderStatus::Confirmed->value,
            ])
            ->assertForbidden();

        $this->assertSame(OrderStatus::Received, $order->fresh()->status);
    }

    public function test_valid_and_invalid_status_transitions(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
        ]);
        $token = $this->tokenFor($manager);

        $this->withToken($token)
            ->patchJson('/api/orders/'.$order->id.'/status', ['status' => OrderStatus::Delivered->value])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.');

        $flow = [
            OrderStatus::Confirmed,
            OrderStatus::InProgress,
            OrderStatus::Review,
            OrderStatus::Revision,
            OrderStatus::Review,
            OrderStatus::Completed,
            OrderStatus::Delivered,
        ];

        foreach ($flow as $status) {
            $this->withToken($token)
                ->patchJson('/api/orders/'.$order->id.'/status', ['status' => $status->value])
                ->assertOk()
                ->assertJsonPath('data.status', $status->value)
                ->assertJsonPath('data.progress', $status->progressPercent());
        }

        $this->asUser($customer)
            ->getJson('/api/customer/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Delivered->value)
            ->assertJsonPath('data.progress', 100)
            ->assertJsonPath('data.timeline.6.state', 'current')
            ->assertJsonPath('data.timeline.0.state', 'completed');
    }

    public function test_account_manager_cannot_access_unrelated_order(): void
    {
        $managerA = User::factory()->accountManager()->create();
        $managerB = User::factory()->accountManager()->create();
        $order = Order::factory()->create([
            'account_manager_id' => $managerB->id,
        ]);

        $this->asUser($managerA)
            ->getJson('/api/orders/'.$order->id)
            ->assertForbidden();

        $this->asUser($managerA)
            ->patchJson('/api/orders/'.$order->id.'/status', [
                'status' => OrderStatus::Confirmed->value,
            ])
            ->assertForbidden();
    }

    public function test_owner_can_view_all_orders_and_update_status(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $order = Order::factory()->create([
            'account_manager_id' => $manager->id,
        ]);

        $this->asUser($owner)
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.reference', $order->reference);

        $this->asUser($owner)
            ->patchJson('/api/orders/'.$order->id.'/status', [
                'status' => OrderStatus::Confirmed->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Confirmed->value);

        $this->asUser($owner)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.overview.orders.available', true)
            ->assertJsonPath('data.overview.orders.value', 1);
    }

    public function test_owner_creates_order_assigned_to_account_manager(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();

        $this->asUser($owner)
            ->postJson('/api/orders', [
                'title' => 'حملة هوية',
                'customer_id' => $customer->id,
                'account_manager_id' => $manager->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.account_manager.id', $manager->id)
            ->assertJsonPath('data.status', OrderStatus::Received->value);
    }

    public function test_order_lookups_are_scoped_for_account_managers(): void
    {
        $manager = User::factory()->accountManager()->create();
        $otherManager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['name' => 'منى العميل']);
        $managed = Project::factory()->create([
            'title' => 'مشروع مدار',
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
        ]);
        Project::factory()->create([
            'title' => 'مشروع آخر',
            'account_manager_id' => $otherManager->id,
        ]);

        $payload = $this->asUser($manager)
            ->getJson('/api/orders/lookups')
            ->assertOk()
            ->json('data');

        $this->assertSame('منى العميل', collect($payload['customers'])->firstWhere('id', $customer->id)['name']);
        $this->assertSame([$managed->id], collect($payload['projects'])->pluck('id')->all());
        $this->assertSame([], $payload['account_managers']);
    }

    public function test_other_employees_cannot_access_orders(): void
    {
        $developer = User::factory()->webDeveloper()->create();
        $designer = User::factory()->graphicDesigner()->create();
        $hr = User::factory()->hr()->create();
        $order = Order::factory()->create();

        foreach ([$developer, $designer, $hr] as $employee) {
            $this->asUser($employee)
                ->getJson('/api/orders')
                ->assertForbidden();

            $this->asUser($employee)
                ->getJson('/api/orders/lookups')
                ->assertForbidden();

            $this->asUser($employee)
                ->getJson('/api/orders/'.$order->id)
                ->assertForbidden();
        }
    }

    public function test_inactive_customer_cannot_access_orders(): void
    {
        $customer = User::factory()->inactive()->create();
        Order::factory()->create(['customer_id' => $customer->id]);

        $this->asUser($customer)
            ->getJson('/api/customer/orders')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');
    }

    public function test_sensitive_fields_are_not_returned(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
        ]);

        $payload = $this->asUser($customer)
            ->getJson('/api/customer/orders/'.$order->id)
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('customer', $payload);
        $this->assertArrayNotHasKey('history', $payload);
        $json = json_encode($payload);
        $this->assertStringNotContainsString('password', (string) $json);
        $this->assertStringNotContainsString('remember_token', (string) $json);
    }
}
