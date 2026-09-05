<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('auth')->plainTextToken;
    }

    public function test_guest_cannot_list_employees(): void
    {
        $this->getJson('/api/admin/employees')
            ->assertUnauthorized();
    }

    public function test_customer_cannot_access_employee_management(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/admin/employees')
            ->assertForbidden();
    }

    public function test_admin_manager_cannot_access_employee_management(): void
    {
        $user = User::factory()->adminManager()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)
            ->getJson('/api/admin/employees')
            ->assertForbidden();

        $this->withToken($token)
            ->postJson('/api/admin/employees', [
                'name' => 'Staff',
                'email' => 'staff@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => UserRole::WebDeveloper->value,
            ])
            ->assertForbidden();
    }

    public function test_employee_cannot_access_employee_management(): void
    {
        $user = User::factory()->printingSpecialist()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/admin/employees')
            ->assertForbidden();
    }

    public function test_owner_can_list_employees_without_customers_or_owners(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->printingSpecialist()->create(['name' => 'Printer']);
        User::factory()->create(['email' => 'customer@example.com']);

        $this->withToken($this->tokenFor($owner))
            ->getJson('/api/admin/employees')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id', $employee->id)
            ->assertJsonPath('data.items.0.workspace', 'printing')
            ->assertJsonMissingPath('data.items.0.password')
            ->assertJsonMissing(['password', 'remember_token']);

        $emails = collect($this->withToken($this->tokenFor($owner))->getJson('/api/admin/employees')->json('data.items'))
            ->pluck('email');

        $this->assertTrue($emails->contains($employee->email));
        $this->assertFalse($emails->contains($owner->email));
        $this->assertFalse($emails->contains('customer@example.com'));
    }

    public function test_owner_can_create_employee_and_password_is_not_returned(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->withToken($this->tokenFor($owner))
            ->postJson('/api/admin/employees', [
                'name' => 'Hassan Designer',
                'email' => 'hassan.design@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => UserRole::GraphicDesigner->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'hassan.design@example.com')
            ->assertJsonPath('data.role', UserRole::GraphicDesigner->value)
            ->assertJsonPath('data.workspace', 'graphic-designer')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', [
            'email' => 'hassan.design@example.com',
            'role' => UserRole::GraphicDesigner->value,
            'is_active' => true,
        ]);

        $this->assertStringNotContainsString('password123', $response->getContent());
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->printingSpecialist()->create(['email' => 'taken@example.com']);

        $this->withToken($this->tokenFor($owner))
            ->postJson('/api/admin/employees', [
                'name' => 'Other',
                'email' => 'taken@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => UserRole::WebDeveloper->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_owner_and_customer_roles_cannot_be_assigned(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $this->tokenFor($owner);

        foreach ([UserRole::Owner->value, UserRole::Customer->value, 'SUPERADMIN'] as $role) {
            $this->withToken($token)
                ->postJson('/api/admin/employees', [
                    'name' => 'Blocked',
                    'email' => $role.'@example.com',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'role' => $role,
                ])
                ->assertStatus(422);
        }
    }

    public function test_owner_can_update_employee_profile_and_role(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->webDeveloper()->create();

        $this->withToken($this->tokenFor($owner))
            ->putJson('/api/admin/employees/'.$employee->id, [
                'name' => 'Updated Dev',
                'email' => 'updated.dev@example.com',
                'role' => UserRole::MarketingSpecialist->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Dev')
            ->assertJsonPath('data.email', 'updated.dev@example.com')
            ->assertJsonPath('data.role', UserRole::MarketingSpecialist->value)
            ->assertJsonPath('data.workspace', 'marketing');
    }

    public function test_owner_cannot_promote_employee_to_owner(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->adminManager()->create();

        $this->withToken($this->tokenFor($owner))
            ->putJson('/api/admin/employees/'.$employee->id, [
                'name' => $employee->name,
                'email' => $employee->email,
                'role' => UserRole::Owner->value,
            ])
            ->assertStatus(422);
    }

    public function test_owner_can_deactivate_and_reactivate_employee(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->printingSpecialist()->create();
        $employeeToken = $this->tokenFor($employee);

        $this->withToken($this->tokenFor($owner))
            ->patchJson('/api/admin/employees/'.$employee->id.'/status', ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'is_active' => false]);
        $this->assertSame(0, $employee->fresh()->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->withToken($employeeToken)
            ->getJson('/api/admin/printing-requests')
            ->assertUnauthorized();

        $this->withToken($employeeToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => $employee->email,
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');

        $this->withToken($this->tokenFor($owner))
            ->patchJson('/api/admin/employees/'.$employee->id.'/status', ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->postJson('/api/auth/login', [
            'email' => $employee->email,
            'password' => 'password',
        ])->assertOk();
    }

    public function test_invalid_employee_payload_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();
        $token = $this->tokenFor($owner);

        $this->withToken($token)
            ->postJson('/api/admin/employees', [
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'different',
                'role' => UserRole::WebDeveloper->value,
            ])
            ->assertStatus(422);

        $employee = User::factory()->webDeveloper()->create();

        $this->withToken($token)
            ->putJson('/api/admin/employees/'.$employee->id, [
                'name' => '',
                'email' => 'still-bad',
                'role' => UserRole::GraphicDesigner->value,
            ])
            ->assertStatus(422);
    }

    public function test_deactivated_employee_cannot_use_remaining_session(): void
    {
        $employee = User::factory()->printingSpecialist()->create();
        $token = $this->tokenFor($employee);
        $employee->update(['is_active' => false]);

        $this->withToken($token)
            ->getJson('/api/admin/printing-requests')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');
    }

    public function test_owner_accounts_cannot_be_managed_as_employees(): void
    {
        $owner = User::factory()->owner()->create();

        $this->withToken($this->tokenFor($owner))
            ->getJson('/api/admin/employees/'.$owner->id)
            ->assertNotFound();

        $this->withToken($this->tokenFor($owner))
            ->patchJson('/api/admin/employees/'.$owner->id.'/status', ['is_active' => false])
            ->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'role' => UserRole::Owner->value,
            'is_active' => true,
        ]);
    }

    public function test_owner_can_search_and_filter_employees(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->printingSpecialist()->create(['name' => 'Printer Ali', 'email' => 'ali.print@example.com']);
        User::factory()->webDeveloper()->inactive()->create(['name' => 'Inactive Dev', 'email' => 'dev@example.com']);

        $token = $this->tokenFor($owner);

        $this->withToken($token)
            ->getJson('/api/admin/employees?q=ali.print')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.email', 'ali.print@example.com');

        $this->withToken($token)
            ->getJson('/api/admin/employees?role='.UserRole::WebDeveloper->value)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.role', UserRole::WebDeveloper->value);

        $this->withToken($token)
            ->getJson('/api/admin/employees?is_active=false')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.is_active', false);
    }
}
