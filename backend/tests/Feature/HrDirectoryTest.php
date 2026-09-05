<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('auth')->plainTextToken;
    }

    public function test_hr_can_view_employee_directory_without_credentials(): void
    {
        $hr = User::factory()->hr()->create(['name' => 'HR User']);
        $developer = User::factory()->webDeveloper()->create(['name' => 'Dev User']);
        User::factory()->owner()->create(['name' => 'Owner User']);
        User::factory()->create(['name' => 'Customer User']);

        $payload = $this->withToken($this->tokenFor($hr))
            ->getJson('/api/workspace/hr/employees')
            ->assertOk()
            ->json('data');

        $names = array_column($payload['items'], 'name');
        $this->assertContains('Dev User', $names);
        $this->assertContains('HR User', $names);
        $this->assertNotContains('Owner User', $names);
        $this->assertNotContains('Customer User', $names);

        $first = $payload['items'][0];
        $this->assertArrayHasKey('role', $first);
        $this->assertArrayHasKey('is_active', $first);
        $this->assertArrayNotHasKey('password', $first);
        $this->assertArrayNotHasKey('remember_token', $first);
        $this->assertSame(2, $payload['summary']['total']);
        $this->assertSame(2, $payload['summary']['active']);
        $this->assertSame(0, $payload['summary']['inactive']);
    }

    public function test_hr_directory_filters_on_the_server(): void
    {
        $hr = User::factory()->hr()->create(['name' => 'HR User']);
        User::factory()->webDeveloper()->create(['name' => 'Active Dev']);
        User::factory()->graphicDesigner()->inactive()->create(['name' => 'Inactive Designer']);

        $token = $this->tokenFor($hr);

        $active = $this->withToken($token)
            ->getJson('/api/workspace/hr/employees?is_active=true')
            ->assertOk()
            ->json('data');
        $this->assertNotContains('Inactive Designer', array_column($active['items'], 'name'));

        $inactive = $this->withToken($token)
            ->getJson('/api/workspace/hr/employees?is_active=false')
            ->assertOk()
            ->json('data');
        $this->assertSame(['Inactive Designer'], array_column($inactive['items'], 'name'));

        $devs = $this->withToken($token)
            ->getJson('/api/workspace/hr/employees?role=WEB_DEVELOPER&q=Active')
            ->assertOk()
            ->json('data');
        $this->assertSame(['Active Dev'], array_column($devs['items'], 'name'));
    }

    public function test_hr_cannot_manage_employees_or_use_owner_apis(): void
    {
        $hr = User::factory()->hr()->create();
        $token = $this->tokenFor($hr);

        $this->withToken($token)->getJson('/api/admin/employees')->assertForbidden();
        $this->withToken($token)->getJson('/api/admin/dashboard')->assertForbidden();
        $this->withToken($token)->postJson('/api/admin/employees', [
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'WEB_DEVELOPER',
        ])->assertForbidden();
    }

    public function test_other_roles_cannot_access_hr_directory(): void
    {
        $this->withToken($this->tokenFor(User::factory()->accountManager()->create()))
            ->getJson('/api/workspace/hr/employees')
            ->assertForbidden();

        $this->withToken($this->tokenFor(User::factory()->webDeveloper()->create()))
            ->getJson('/api/workspace/hr/employees')
            ->assertForbidden();

        $this->withToken($this->tokenFor(User::factory()->create()))
            ->getJson('/api/workspace/hr/employees')
            ->assertForbidden();
    }

    public function test_inactive_hr_cannot_access_directory(): void
    {
        $hr = User::factory()->hr()->inactive()->create();

        $this->withToken($this->tokenFor($hr))
            ->getJson('/api/workspace/hr/employees')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');
    }
}
