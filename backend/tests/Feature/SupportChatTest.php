<?php

namespace Tests\Feature;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportChatTest extends TestCase
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

    public function test_guest_cannot_access_conversation_apis(): void
    {
        $this->getJson('/api/customer/conversations')->assertUnauthorized();
        $this->getJson('/api/customer/conversations/1')->assertUnauthorized();
        $this->postJson('/api/customer/conversations', [
            'subject' => 'استفسار',
            'message' => 'مرحبا',
        ])->assertUnauthorized();
        $this->postJson('/api/customer/conversations/1/messages', [
            'message' => 'مرحبا',
        ])->assertUnauthorized();
        $this->getJson('/api/support/conversations')->assertUnauthorized();
        $this->patchJson('/api/support/conversations/1/status', [
            'status' => ConversationStatus::InProgress->value,
        ])->assertUnauthorized();
    }

    public function test_customer_can_create_list_and_open_own_conversation(): void
    {
        $customer = User::factory()->create(['name' => 'منى']);

        $created = $this->asUser($customer)
            ->postJson('/api/customer/conversations', [
                'subject' => 'استفسار عن طلبي',
                'message' => 'أحتاج معرفة آخر تحديث على الطلب.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.subject', 'استفسار عن طلبي')
            ->assertJsonPath('data.status', ConversationStatus::Open->value)
            ->assertJsonPath('data.status_label', 'مفتوحة')
            ->assertJsonPath('data.can_reply', true)
            ->assertJsonPath('data.messages.0.body', 'أحتاج معرفة آخر تحديث على الطلب.')
            ->assertJsonPath('data.messages.0.from_support', false)
            ->assertJsonPath('data.messages.0.sender.name', 'منى')
            ->assertJsonMissingPath('data.customer')
            ->assertJsonMissingPath('data.allowed_transitions')
            ->json('data');

        $this->assertMatchesRegularExpression('/^HEBR-CS-\d{6}$/', $created['reference']);
        $this->assertArrayNotHasKey('password', $created['messages'][0]['sender']);

        $this->asUser($customer)
            ->getJson('/api/customer/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.reference', $created['reference'])
            ->assertJsonPath('data.0.last_message.body', 'أحتاج معرفة آخر تحديث على الطلب.')
            ->assertJsonMissingPath('data.0.messages')
            ->assertJsonMissingPath('data.0.customer');

        $this->asUser($customer)
            ->getJson('/api/customer/conversations/'.$created['id'])
            ->assertOk()
            ->assertJsonPath('data.reference', $created['reference'])
            ->assertJsonPath('data.messages.0.body', 'أحتاج معرفة آخر تحديث على الطلب.');

        $this->asUser($customer)
            ->getJson('/api/customer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.messages.available', true)
            ->assertJsonPath('data.summary.messages.value', 1)
            ->assertJsonPath('data.messages.0.reference', $created['reference']);
    }

    public function test_customer_cannot_open_or_message_another_customers_conversation(): void
    {
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'customer_id' => $customerB->id,
            'subject' => 'سر العميل ب',
            'assigned_to' => null,
        ]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customerB->id,
            'body' => 'رسالة سرية',
        ]);

        $this->asUser($customerA)
            ->getJson('/api/customer/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->asUser($customerA)
            ->getJson('/api/customer/conversations/'.$conversation->id)
            ->assertForbidden();

        $this->asUser($customerA)
            ->postJson('/api/customer/conversations/'.$conversation->id.'/messages', [
                'message' => 'محاولة وصول',
            ])
            ->assertForbidden();

        $this->asUser($customerA)
            ->getJson('/api/customer/dashboard')
            ->assertOk()
            ->assertJsonCount(0, 'data.messages');

        $this->assertSame(1, Message::query()->where('conversation_id', $conversation->id)->count());
    }

    public function test_customer_can_link_own_order_and_cannot_link_another_customers_order(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $orderA = Order::factory()->create([
            'customer_id' => $customerA->id,
            'account_manager_id' => $manager->id,
        ]);
        $orderB = Order::factory()->create([
            'customer_id' => $customerB->id,
            'account_manager_id' => $manager->id,
        ]);

        $this->asUser($customerA)
            ->postJson('/api/customer/conversations', [
                'order_id' => $orderA->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.order.id', $orderA->id)
            ->assertJsonPath('data.order.reference', $orderA->reference)
            ->assertJsonPath('data.assignee.id', $manager->id);

        $this->asUser($customerA)
            ->postJson('/api/customer/conversations', [
                'subject' => 'طلب غير مملوك',
                'message' => 'محاولة ربط',
                'order_id' => $orderB->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.');
    }

    public function test_customer_can_link_own_project_and_cannot_link_another_customers_project(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $projectA = Project::factory()->create([
            'customer_id' => $customerA->id,
            'account_manager_id' => $manager->id,
            'title' => 'متجر إلكتروني',
        ]);
        $projectB = Project::factory()->create([
            'customer_id' => $customerB->id,
            'account_manager_id' => $manager->id,
            'title' => 'مشروع سري',
        ]);

        $this->asUser($customerA)
            ->postJson('/api/customer/conversations', [
                'project_id' => $projectA->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.project.id', $projectA->id)
            ->assertJsonPath('data.project.title', 'متجر إلكتروني');

        $this->asUser($customerA)
            ->postJson('/api/customer/conversations', [
                'subject' => 'مشروع غير مملوك',
                'message' => 'محاولة ربط',
                'project_id' => $projectB->id,
            ])
            ->assertUnprocessable();
    }

    public function test_order_context_reuses_existing_open_conversation(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
        ]);

        $first = $this->asUser($customer)
            ->postJson('/api/customer/conversations', [
                'order_id' => $order->id,
                'message' => 'أول رسالة',
            ])
            ->assertCreated()
            ->json('data');

        $second = $this->asUser($customer)
            ->postJson('/api/customer/conversations', [
                'order_id' => $order->id,
                'message' => 'رسالة لاحقة',
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame($first['reference'], $second['reference']);
        $this->assertCount(2, $second['messages']);
        $this->assertSame(1, Conversation::query()->where('order_id', $order->id)->count());
    }

    public function test_customer_can_send_valid_message_and_empty_or_oversized_are_rejected(): void
    {
        $customer = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => null,
        ]);

        $this->asUser($customer)
            ->postJson('/api/customer/conversations/'.$conversation->id.'/messages', [
                'message' => 'أحتاج معرفة آخر تحديث على الطلب.',
            ])
            ->assertOk()
            ->assertJsonPath('data.messages.0.body', 'أحتاج معرفة آخر تحديث على الطلب.');

        $this->asUser($customer)
            ->postJson('/api/customer/conversations/'.$conversation->id.'/messages', [
                'message' => '   ',
            ])
            ->assertUnprocessable();

        $this->asUser($customer)
            ->postJson('/api/customer/conversations/'.$conversation->id.'/messages', [
                'message' => str_repeat('أ', ConversationService::MESSAGE_MAX_LENGTH + 1),
            ])
            ->assertUnprocessable();

        $this->asUser($customer)
            ->postJson('/api/customer/conversations/'.$conversation->id.'/messages', [
                'message' => '<script>alert(1)</script>نص آمن',
            ])
            ->assertOk()
            ->assertJsonPath('data.messages.1.body', 'نص آمن');
    }

    public function test_closed_conversation_rejects_new_messages(): void
    {
        $customer = User::factory()->create();
        $conversation = Conversation::factory()->closed()->create([
            'customer_id' => $customer->id,
            'assigned_to' => null,
        ]);

        $this->asUser($customer)
            ->getJson('/api/customer/conversations/'.$conversation->id)
            ->assertOk()
            ->assertJsonPath('data.can_reply', false)
            ->assertJsonPath('data.status_label', 'مغلقة');

        $this->asUser($customer)
            ->postJson('/api/customer/conversations/'.$conversation->id.'/messages', [
                'message' => 'بعد الإغلاق',
            ])
            ->assertUnprocessable();

        $this->assertSame(0, Message::query()->where('conversation_id', $conversation->id)->count());
    }

    public function test_customer_cannot_change_status(): void
    {
        $customer = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => null,
        ]);

        $this->asUser($customer)
            ->patchJson('/api/support/conversations/'.$conversation->id.'/status', [
                'status' => ConversationStatus::Closed->value,
            ])
            ->assertForbidden();

        $this->assertSame(ConversationStatus::Open, $conversation->fresh()->status);
    }

    public function test_account_manager_can_reply_and_change_status_within_scope(): void
    {
        $manager = User::factory()->accountManager()->create(['name' => 'أحمد']);
        $otherManager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['name' => 'منى']);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
        ]);
        $conversation = Conversation::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $manager->id,
            'order_id' => $order->id,
        ]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
            'body' => 'سؤال العميل',
        ]);

        $this->asUser($manager)
            ->getJson('/api/support/conversations')
            ->assertOk()
            ->assertJsonPath('data.items.0.reference', $conversation->reference)
            ->assertJsonPath('data.items.0.customer.name', 'منى')
            ->assertJsonMissingPath('data.items.0.customer.password');

        $this->asUser($manager)
            ->postJson('/api/support/conversations/'.$conversation->id.'/messages', [
                'message' => 'تم استلام استفسارك.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ConversationStatus::InProgress->value)
            ->assertJsonPath('data.status_label', 'قيد المتابعة')
            ->assertJsonPath('data.messages.1.body', 'تم استلام استفسارك.')
            ->assertJsonPath('data.messages.1.from_support', true)
            ->assertJsonPath('data.messages.1.sender.name', 'أحمد');

        $this->asUser($customer)
            ->getJson('/api/customer/conversations/'.$conversation->id)
            ->assertOk()
            ->assertJsonPath('data.status_label', 'قيد المتابعة')
            ->assertJsonPath('data.messages.1.body', 'تم استلام استفسارك.')
            ->assertJsonMissingPath('data.allowed_transitions')
            ->assertJsonMissingPath('data.customer');

        $this->asUser($manager)
            ->patchJson('/api/support/conversations/'.$conversation->id.'/status', [
                'status' => ConversationStatus::Closed->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ConversationStatus::Closed->value);

        $this->asUser($otherManager)
            ->getJson('/api/support/conversations/'.$conversation->id)
            ->assertForbidden();

        $this->asUser($otherManager)
            ->postJson('/api/support/conversations/'.$conversation->id.'/messages', [
                'message' => 'رد غير مصرح',
            ])
            ->assertForbidden();
    }

    public function test_account_manager_cannot_see_unassigned_general_conversations(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => null,
            'order_id' => null,
            'project_id' => null,
            'subject' => 'دعم عام',
        ]);

        $this->asUser($manager)
            ->getJson('/api/support/conversations')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);

        $this->asUser($manager)
            ->getJson('/api/support/conversations/'.$conversation->id)
            ->assertForbidden();
    }

    public function test_owner_can_view_reply_and_update_any_conversation(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'المالك']);
        $customer = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => null,
        ]);

        $this->asUser($owner)
            ->getJson('/api/support/conversations/'.$conversation->id)
            ->assertOk()
            ->assertJsonPath('data.reference', $conversation->reference);

        $this->asUser($owner)
            ->postJson('/api/support/conversations/'.$conversation->id.'/messages', [
                'message' => 'رد المالك',
            ])
            ->assertOk()
            ->assertJsonPath('data.messages.0.sender.name', 'المالك');

        $this->asUser($owner)
            ->patchJson('/api/support/conversations/'.$conversation->id.'/status', [
                'status' => ConversationStatus::Resolved->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status_label', 'تم الحل');
    }

    public function test_invalid_status_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => null,
        ]);

        $this->asUser($owner)
            ->patchJson('/api/support/conversations/'.$conversation->id.'/status', [
                'status' => 'DELIVERED',
            ])
            ->assertUnprocessable();

        $this->asUser($owner)
            ->patchJson('/api/support/conversations/'.$conversation->id.'/status', [
                'status' => ConversationStatus::Closed->value,
            ])
            ->assertOk();

        $this->asUser($owner)
            ->patchJson('/api/support/conversations/'.$conversation->id.'/status', [
                'status' => ConversationStatus::Open->value,
            ])
            ->assertUnprocessable();
    }

    public function test_unrelated_employee_cannot_access_support_inbox(): void
    {
        $developer = User::factory()->webDeveloper()->create();
        $hr = User::factory()->hr()->create();
        $customer = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->asUser($developer)
            ->getJson('/api/support/conversations')
            ->assertForbidden();

        $this->asUser($developer)
            ->getJson('/api/support/conversations/'.$conversation->id)
            ->assertForbidden();

        $this->asUser($hr)
            ->postJson('/api/support/conversations/'.$conversation->id.'/messages', [
                'message' => 'لا يجب',
            ])
            ->assertForbidden();

        $this->asUser($developer)
            ->getJson('/api/customer/conversations')
            ->assertForbidden();
    }

    public function test_inactive_users_cannot_use_support_chat(): void
    {
        $customer = User::factory()->inactive()->create();
        $manager = User::factory()->accountManager()->inactive()->create();

        $this->asUser($customer)
            ->getJson('/api/customer/conversations')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');

        $this->asUser($manager)
            ->getJson('/api/support/conversations')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');
    }

    public function test_support_payloads_never_expose_secrets(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['password' => 'secret-password']);
        $conversation = Conversation::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $manager->id,
        ]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
            'body' => 'مرحبا',
        ]);

        $payload = $this->asUser($manager)
            ->getJson('/api/support/conversations/'.$conversation->id)
            ->assertOk()
            ->json('data');

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('secret-password', (string) $encoded);
        $this->assertStringNotContainsString('remember_token', (string) $encoded);
        $this->assertArrayNotHasKey('password', $payload['customer']);
        $this->assertArrayNotHasKey('role', $payload['messages'][0]['sender']);
    }
}
