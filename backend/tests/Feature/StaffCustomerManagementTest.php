<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffCustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_owner_can_create_customer(): void
    {
        $owner = User::factory()->owner()->create();

        $payload = $this->asUser($owner)
            ->postJson('/api/workspace/account-manager/customers', [
                'name' => 'عميل داخلي',
                'email' => 'internal.customer@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'عميل داخلي')
            ->assertJsonPath('data.email', 'internal.customer@example.com')
            ->assertJsonPath('data.role', UserRole::Customer->value)
            ->assertJsonPath('data.has_account', false)
            ->assertJsonPath('data.account_status', 'NO_ACCOUNT')
            ->assertJsonPath('data.projects_count', 0)
            ->json('data');

        $this->assertDatabaseHas('users', [
            'id' => $payload['id'],
            'email' => 'internal.customer@example.com',
            'role' => UserRole::Customer->value,
            'is_active' => true,
        ]);

        $customer = User::query()->findOrFail($payload['id']);
        $this->assertNotEmpty($customer->password);
        $this->assertFalse(Hash::check('Password123!', $customer->password));
    }

    public function test_account_manager_can_create_customer(): void
    {
        $manager = User::factory()->accountManager()->create();

        $this->asUser($manager)
            ->postJson('/api/workspace/account-manager/customers', [
                'name' => 'AM Customer',
                'email' => 'am.customer@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'am.customer@example.com')
            ->assertJsonPath('data.has_account', false);
    }

    public function test_customer_cannot_create_customer(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $this->asUser($customer)
            ->postJson('/api/workspace/account-manager/customers', [
                'name' => 'Nope',
                'email' => 'nope@example.com',
            ])
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_create_customer(): void
    {
        $this->postJson('/api/workspace/account-manager/customers', [
            'name' => 'Nope',
            'email' => 'nope@example.com',
        ])->assertUnauthorized();
    }

    public function test_created_customer_appears_in_listing(): void
    {
        $owner = User::factory()->owner()->create();

        $id = $this->asUser($owner)
            ->postJson('/api/workspace/account-manager/customers', [
                'name' => 'Listed Customer',
                'email' => 'listed@example.com',
            ])
            ->assertCreated()
            ->json('data.id');

        $items = $this->asUser($owner)
            ->getJson('/api/workspace/account-manager/customers')
            ->assertOk()
            ->json('data');

        $this->assertContains($id, collect($items)->pluck('id')->all());
        $row = collect($items)->firstWhere('id', $id);
        $this->assertSame(false, $row['has_account']);
        $this->assertSame('NO_ACCOUNT', $row['account_status']);
    }

    public function test_created_customer_without_login_can_be_assigned_to_project(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();

        $customerId = $this->asUser($owner)
            ->postJson('/api/workspace/account-manager/customers', [
                'name' => 'Project Customer',
                'email' => 'project.customer@example.com',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($owner)
            ->postJson('/api/workspace/projects', [
                'title' => 'Project for internal customer',
                'customer_id' => $customerId,
                'account_manager_id' => $manager->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.customer_id', $customerId);

        $this->assertDatabaseHas('projects', [
            'customer_id' => $customerId,
            'title' => 'Project for internal customer',
        ]);

        $this->assertSame(1, Project::query()->where('customer_id', $customerId)->count());
    }

    public function test_existing_customer_with_account_still_works_in_listing_and_projects(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create([
            'role' => UserRole::Customer,
            'name' => 'Registered Customer',
            'email' => 'registered@example.com',
        ]);
        // Simulate authenticated usage so has_account becomes true.
        $token = $customer->createToken('auth');
        $token->accessToken->forceFill(['last_used_at' => now()])->save();

        $items = $this->asUser($owner)
            ->getJson('/api/workspace/account-manager/customers')
            ->assertOk()
            ->json('data');

        $row = collect($items)->firstWhere('id', $customer->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['has_account']);
        $this->assertSame('ACTIVE', $row['account_status']);

        $this->asUser($owner)
            ->postJson('/api/workspace/projects', [
                'title' => 'For registered customer',
                'customer_id' => $customer->id,
                'account_manager_id' => $manager->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.customer_id', $customer->id);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->create([
            'role' => UserRole::Customer,
            'email' => 'dup@example.com',
        ]);

        $this->asUser($owner)
            ->postJson('/api/workspace/account-manager/customers', [
                'name' => 'Dup',
                'email' => 'dup@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_public_registration_flow_still_creates_customer(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Self Registered',
            'email' => 'self.registered@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.role', UserRole::Customer->value)
            ->assertJsonPath('data.user.email', 'self.registered@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'self.registered@example.com',
            'role' => UserRole::Customer->value,
        ]);
    }

    public function test_employee_cannot_create_customer(): void
    {
        $employee = User::factory()->webDeveloper()->create();

        $this->asUser($employee)
            ->postJson('/api/workspace/account-manager/customers', [
                'name' => 'Blocked',
                'email' => 'blocked@example.com',
            ])
            ->assertForbidden();
    }
}
