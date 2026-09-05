<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class PlatformHardeningTest extends TestCase
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

    /**
     * @return list<string>
     */
    private function middlewareFor(string $uri, string $method): array
    {
        $route = collect(Route::getRoutes())->first(
            fn ($candidate) => $candidate->uri() === $uri && in_array($method, $candidate->methods(), true)
        );

        $this->assertNotNull($route, 'Missing route '.$method.' '.$uri);

        return $route->gatherMiddleware();
    }

    public function test_sensitive_write_routes_are_rate_limited(): void
    {
        $this->assertContains('throttle:hebr-login', $this->middlewareFor('api/auth/login', 'POST'));
        $this->assertContains('throttle:hebr-register', $this->middlewareFor('api/auth/register', 'POST'));
        $this->assertContains('throttle:hebr-password', $this->middlewareFor('api/auth/forgot-password', 'POST'));
        $this->assertContains('throttle:hebr-password', $this->middlewareFor('api/auth/reset-password', 'POST'));
        $this->assertContains('throttle:hebr-consultations', $this->middlewareFor('api/consultations', 'POST'));
        $this->assertContains('throttle:hebr-messages', $this->middlewareFor('api/customer/conversations/{conversation}/messages', 'POST'));
        $this->assertContains('throttle:hebr-uploads', $this->middlewareFor('api/customer/files', 'POST'));
        $this->assertContains('throttle:hebr-uploads', $this->middlewareFor('api/workspace/files', 'POST'));
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        config(['testing.force_rate_limits' => true]);

        $user = User::factory()->create([
            'email' => 'limit@example.com',
            'password' => 'password',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Too many attempts. Please try again later.');
    }

    public function test_forgot_password_is_rate_limited_after_repeated_requests(): void
    {
        config(['testing.force_rate_limits' => true]);

        $payload = ['email' => 'reset-limit@example.com'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/forgot-password', $payload)->assertOk();
        }

        $this->postJson('/api/auth/forgot-password', $payload)
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Too many attempts. Please try again later.');
    }

    public function test_private_local_disk_is_not_publicly_served(): void
    {
        $this->assertFalse((bool) config('filesystems.disks.local.serve'));
        $this->assertNull(Route::getRoutes()->getByName('storage.local'));
        $this->get('/storage/files/qa-secret.txt')->assertNotFound();
        $this->getJson('/storage/files/qa-secret.txt')->assertNotFound();
    }

    public function test_rbac_matrix_blocks_cross_role_admin_and_customer_apis(): void
    {
        $users = [
            UserRole::Owner->value => User::factory()->owner()->create(),
            UserRole::AdminManager->value => User::factory()->adminManager()->create(),
            UserRole::Customer->value => User::factory()->create(),
            UserRole::WebDeveloper->value => User::factory()->webDeveloper()->create(),
            UserRole::GraphicDesigner->value => User::factory()->graphicDesigner()->create(),
            UserRole::MarketingSpecialist->value => User::factory()->marketingSpecialist()->create(),
            UserRole::EventSpecialist->value => User::factory()->eventSpecialist()->create(),
            UserRole::PrintingSpecialist->value => User::factory()->printingSpecialist()->create(),
            UserRole::MediaBuyer->value => User::factory()->mediaBuyer()->create(),
            UserRole::VideoEditor->value => User::factory()->videoEditor()->create(),
            UserRole::AccountManager->value => User::factory()->accountManager()->create(),
            UserRole::Hr->value => User::factory()->hr()->create(),
        ];

        foreach ($users as $role => $user) {
            $adminDashboard = $this->asUser($user)->getJson('/api/admin/dashboard');
            $employees = $this->asUser($user)->getJson('/api/admin/employees');
            $services = $this->asUser($user)->getJson('/api/admin/services');
            $customerDashboard = $this->asUser($user)->getJson('/api/customer/dashboard');
            $workspace = $this->asUser($user)->getJson('/api/workspace');
            $hrDirectory = $this->asUser($user)->getJson('/api/workspace/hr/employees');
            $managedOrders = $this->asUser($user)->getJson('/api/orders');

            $role === UserRole::Owner->value ? $adminDashboard->assertOk() : $adminDashboard->assertForbidden();
            $role === UserRole::Owner->value ? $employees->assertOk() : $employees->assertForbidden();

            in_array($role, [UserRole::Owner->value, UserRole::AdminManager->value], true)
                ? $services->assertOk()
                : $services->assertForbidden();

            $role === UserRole::Customer->value ? $customerDashboard->assertOk() : $customerDashboard->assertForbidden();

            in_array($role, UserRole::workspaceEmployeeValues(), true)
                ? $workspace->assertOk()
                : $workspace->assertForbidden();

            $role === UserRole::Hr->value ? $hrDirectory->assertOk() : $hrDirectory->assertForbidden();

            in_array($role, [UserRole::Owner->value, UserRole::AccountManager->value], true)
                ? $managedOrders->assertOk()
                : $managedOrders->assertForbidden();
        }
    }

    public function test_customer_cannot_read_another_customers_project_or_order(): void
    {
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $manager = User::factory()->accountManager()->create();
        $projectB = Project::factory()->create([
            'customer_id' => $customerB->id,
            'account_manager_id' => $manager->id,
        ]);
        $orderB = Order::factory()->create([
            'customer_id' => $customerB->id,
            'account_manager_id' => $manager->id,
            'project_id' => $projectB->id,
        ]);

        $this->asUser($customerA)
            ->getJson('/api/customer/projects/'.$projectB->id)
            ->assertForbidden();

        $this->asUser($customerA)
            ->getJson('/api/customer/orders/'.$orderB->id)
            ->assertForbidden();

        $this->asUser($customerA)
            ->getJson('/api/customer/projects')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->asUser($customerA)
            ->getJson('/api/customer/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unhandled_api_exception_is_generic_when_debug_is_disabled(): void
    {
        config(['app.debug' => false]);

        Route::get('/api/__task30-boom', function (): never {
            throw new RuntimeException('SQLSTATE leaked at C:\\hebr\\secret.php');
        });

        $response = $this->getJson('/api/__task30-boom');
        $body = (string) $response->getContent();

        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Server error.');

        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('secret.php', $body);
        $this->assertStringNotContainsString('RuntimeException', $body);
        $this->assertStringNotContainsString('C:\\hebr', $body);
    }
}
