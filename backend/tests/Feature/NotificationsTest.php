<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\TaskPriority;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsTest extends TestCase
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

    public function test_guest_cannot_access_notifications(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
        $this->patchJson('/api/notifications/read-all')->assertUnauthorized();
        $this->patchJson('/api/notifications/abc/read')->assertUnauthorized();
    }

    public function test_inactive_user_cannot_access_notifications(): void
    {
        $customer = User::factory()->inactive()->create();

        $this->asUser($customer)
            ->getJson('/api/notifications')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');
    }

    public function test_order_status_change_notifies_owning_customer_only_once(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $developer = User::factory()->webDeveloper()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
            'status' => OrderStatus::Confirmed,
            'reference' => 'HEBR-ORD-000021',
        ]);

        $this->asUser($manager)
            ->patchJson('/api/orders/'.$order->id.'/status', ['status' => OrderStatus::InProgress->value])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::InProgress->value);

        $this->asUser($manager)
            ->patchJson('/api/orders/'.$order->id.'/status', ['status' => OrderStatus::InProgress->value])
            ->assertUnprocessable();

        $this->assertSame(1, $customer->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->assertSame(0, $developer->notifications()->count());
        $this->assertSame(0, $manager->notifications()->count());

        $payload = $this->asUser($customer)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.items.0.type', 'order_status_updated')
            ->assertJsonPath('data.items.0.title', 'تحديث على طلبك')
            ->assertJsonPath('data.items.0.href', '/dashboard/orders/'.$order->id)
            ->assertJsonPath('data.items.0.data.order_id', $order->id)
            ->assertJsonPath('data.items.0.data.order_reference', 'HEBR-ORD-000021')
            ->json('data.items.0');

        $this->assertStringContainsString('HEBR-ORD-000021', $payload['message']);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('customer', $payload['data']);

        $this->asUser($other)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_customer_message_notifies_assignee_and_not_unrelated_staff(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $otherManager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $customer = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
        ]);

        $this->asUser($customer)
            ->postJson('/api/customer/conversations', [
                'subject' => 'استفسار',
                'message' => 'أحتاج تحديثاً على الطلب.',
                'order_id' => $order->id,
            ])
            ->assertCreated();

        $this->assertSame(1, $manager->notifications()->count());
        $this->assertSame(0, $otherManager->notifications()->count());
        $this->assertSame(0, $developer->notifications()->count());
        $this->assertSame(0, $owner->notifications()->count());
        $this->assertSame(0, $customer->notifications()->count());

        $this->asUser($manager)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.items.0.type', 'new_support_message')
            ->assertJsonPath('data.items.0.title', 'رسالة جديدة من عميل');
    }

    public function test_unassigned_customer_message_notifies_owners_only(): void
    {
        $owner = User::factory()->owner()->create();
        $inactiveOwner = User::factory()->owner()->inactive()->create();
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();

        $this->asUser($customer)
            ->postJson('/api/customer/conversations', [
                'subject' => 'دعم عام',
                'message' => 'مرحباً بفريق حبر',
            ])
            ->assertCreated();

        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame(0, $inactiveOwner->notifications()->count());
        $this->assertSame(0, $manager->notifications()->count());
        $this->assertSame(0, $customer->notifications()->count());

        $this->asUser($owner)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.items.0.href', '/owner/support/'.Conversation::query()->value('id'));
    }

    public function test_support_reply_notifies_customer_only(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $manager->id,
        ]);

        $this->asUser($manager)
            ->postJson('/api/support/conversations/'.$conversation->id.'/messages', [
                'message' => 'تم استلام استفسارك.',
            ])
            ->assertOk();

        $this->assertSame(1, $customer->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->assertSame(0, $owner->notifications()->count());
        $this->assertSame(0, $manager->notifications()->count());

        $this->asUser($customer)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.items.0.type', 'new_support_message')
            ->assertJsonPath('data.items.0.title', 'رسالة جديدة من فريق HEBR')
            ->assertJsonPath('data.items.0.href', '/dashboard/messages/'.$conversation->id);
    }

    public function test_task_assignment_notifies_assignee_only(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $other = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => User::factory()->create()->id,
        ]);

        $task = $this->asUser($manager)
            ->postJson('/api/workspace/account-manager/tasks', [
                'title' => 'تصميم الصفحة الرئيسية',
                'project_id' => $project->id,
                'assigned_to' => $developer->id,
                'priority' => TaskPriority::High->value,
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame(1, $developer->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->assertSame(0, $manager->notifications()->count());

        $this->asUser($manager)
            ->putJson('/api/workspace/account-manager/tasks/'.$task['id'], [
                'title' => 'تصميم الصفحة الرئيسية',
                'project_id' => $project->id,
                'assigned_to' => $developer->id,
                'priority' => TaskPriority::High->value,
                'status' => 'TODO',
            ])
            ->assertOk();

        $this->assertSame(1, $developer->notifications()->count());

        $this->asUser($manager)
            ->putJson('/api/workspace/account-manager/tasks/'.$task['id'], [
                'title' => 'تصميم الصفحة الرئيسية',
                'project_id' => $project->id,
                'assigned_to' => $other->id,
                'priority' => TaskPriority::High->value,
                'status' => 'TODO',
            ])
            ->assertOk();

        $this->assertSame(1, $developer->fresh()->notifications()->count());
        $this->assertSame(1, $other->fresh()->notifications()->count());

        $this->asUser($developer)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.items.0.type', 'task_assigned')
            ->assertJsonPath('data.items.0.href', '/workspace/tasks/'.$task['id']);
    }

    public function test_user_cannot_read_or_mutate_another_users_notification(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customerA->id,
            'account_manager_id' => $manager->id,
            'status' => OrderStatus::Confirmed,
        ]);

        $this->asUser($manager)
            ->patchJson('/api/orders/'.$order->id.'/status', ['status' => OrderStatus::InProgress->value])
            ->assertOk();

        $notificationId = $customerA->notifications()->value('id');
        $this->assertNotNull($notificationId);

        $this->asUser($customerB)
            ->patchJson('/api/notifications/'.$notificationId.'/read')
            ->assertNotFound();

        $this->asUser($customerB)
            ->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 0);

        $this->assertNull($customerA->fresh()->notifications()->first()?->read_at);
    }

    public function test_mark_one_and_all_as_read_only_affects_owner(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $first = Order::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
            'status' => OrderStatus::Confirmed,
        ]);
        $second = Order::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
            'status' => OrderStatus::Confirmed,
        ]);
        $otherOrder = Order::factory()->create([
            'customer_id' => $other->id,
            'account_manager_id' => $manager->id,
            'status' => OrderStatus::Confirmed,
        ]);

        $this->asUser($manager)
            ->patchJson('/api/orders/'.$first->id.'/status', ['status' => OrderStatus::InProgress->value])
            ->assertOk();
        $this->asUser($manager)
            ->patchJson('/api/orders/'.$second->id.'/status', ['status' => OrderStatus::InProgress->value])
            ->assertOk();
        $this->asUser($manager)
            ->patchJson('/api/orders/'.$otherOrder->id.'/status', ['status' => OrderStatus::InProgress->value])
            ->assertOk();

        $firstId = $customer->notifications()->oldest()->value('id');

        $read = $this->asUser($customer)
            ->patchJson('/api/notifications/'.$firstId.'/read')
            ->assertOk()
            ->json('data.read_at');
        $this->assertIsString($read);
        $this->assertNotSame('', $read);

        $this->asUser($customer)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->asUser($customer)
            ->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0)
            ->assertJsonPath('data.updated', 1);

        $this->asUser($customer)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(1, $other->unreadNotifications()->count());

        $this->asUser($customer)
            ->getJson('/api/customer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.notifications.available', true)
            ->assertJsonPath('data.summary.notifications.value', 0)
            ->assertJsonPath('data.notifications.available', true);
    }

    public function test_notifications_are_paginated_newest_first(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();

        foreach (range(1, 3) as $index) {
            $order = Order::factory()->create([
                'customer_id' => $customer->id,
                'account_manager_id' => $manager->id,
                'status' => OrderStatus::Confirmed,
                'reference' => 'HEBR-ORD-00000'.$index,
            ]);
            $this->asUser($manager)
                ->patchJson('/api/orders/'.$order->id.'/status', ['status' => OrderStatus::InProgress->value])
                ->assertOk();
            $this->travel(2)->seconds();
        }

        $page = $this->asUser($customer)
            ->getJson('/api/notifications?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 2)
            ->assertJsonPath('data.meta.total', 3)
            ->assertJsonCount(2, 'data.items')
            ->json('data.items');

        $this->assertSame('HEBR-ORD-000003', $page[0]['data']['order_reference']);
    }
}
