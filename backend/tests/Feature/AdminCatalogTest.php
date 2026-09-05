<?php

namespace Tests\Feature;

use App\Enums\PackageCategory;
use App\Enums\ServiceCategory;
use App\Enums\UserRole;
use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(UserRole $role): string
    {
        return User::factory()->create(['role' => $role])->createToken('auth')->plainTextToken;
    }

    public function test_unauthenticated_user_cannot_reach_admin_catalog(): void
    {
        $this->getJson('/api/admin/services')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->getJson('/api/admin/packages')
            ->assertUnauthorized();
    }

    public function test_customer_cannot_reach_admin_catalog(): void
    {
        $token = $this->tokenFor(UserRole::Customer);

        $this->withToken($token)->getJson('/api/admin/services')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');

        $this->withToken($token)->postJson('/api/admin/packages', [])
            ->assertForbidden();
    }

    public function test_owner_and_admin_manager_can_reach_admin_catalog(): void
    {
        $this->withToken($this->tokenFor(UserRole::Owner))
            ->getJson('/api/admin/services')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($this->tokenFor(UserRole::AdminManager))
            ->getJson('/api/admin/services')
            ->assertOk();
    }

    public function test_admin_service_list_includes_inactive_services_and_management_fields(): void
    {
        Service::factory()->create(['slug' => 'live-service']);
        Service::factory()->inactive()->create(['slug' => 'archived-service']);

        $this->withToken($this->tokenFor(UserRole::Owner))
            ->getJson('/api/admin/services')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['slug' => 'live-service', 'is_active' => true])
            ->assertJsonFragment(['slug' => 'archived-service', 'is_active' => false]);
    }

    public function test_owner_can_create_a_service_and_slug_is_generated(): void
    {
        $this->withToken($this->tokenFor(UserRole::Owner))
            ->postJson('/api/admin/services', [
                'name' => 'Video Production',
                'category' => ServiceCategory::Production->value,
                'base_price' => 6500,
                'duration_days' => 21,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'video-production')
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('services', ['slug' => 'video-production']);
    }

    public function test_service_slug_conflicts_are_resolved(): void
    {
        Service::factory()->create(['slug' => 'video-production']);

        $this->withToken($this->tokenFor(UserRole::Owner))
            ->postJson('/api/admin/services', [
                'name' => 'Video Production',
                'category' => ServiceCategory::Production->value,
                'base_price' => 100,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'video-production-2');
    }

    public function test_owner_can_update_and_deactivate_a_service(): void
    {
        $service = Service::factory()->create(['slug' => 'graphic-design', 'base_price' => 1000]);
        $token = $this->tokenFor(UserRole::Owner);

        $this->withToken($token)
            ->putJson('/api/admin/services/'.$service->id, [
                'name' => 'Graphic Design Pro',
                'category' => ServiceCategory::Production->value,
                'base_price' => 3200,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Graphic Design Pro')
            ->assertJsonPath('data.base_price', '3200.00')
            ->assertJsonPath('data.slug', 'graphic-design');

        $this->withToken($token)
            ->patchJson('/api/admin/services/'.$service->id, ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('services', ['id' => $service->id, 'is_active' => false]);
    }

    public function test_service_used_by_a_package_cannot_be_deleted(): void
    {
        $service = Service::factory()->create(['slug' => 'linked-service']);
        Package::factory()->create(['slug' => 'linked-package'])->services()->attach($service->id);

        $this->withToken($this->tokenFor(UserRole::Owner))
            ->deleteJson('/api/admin/services/'.$service->id)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }

    public function test_unused_service_can_be_deleted(): void
    {
        $service = Service::factory()->create(['slug' => 'free-service']);

        $this->withToken($this->tokenFor(UserRole::Owner))
            ->deleteJson('/api/admin/services/'.$service->id)
            ->assertOk();

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    public function test_owner_can_create_a_package_with_items(): void
    {
        $first = Service::factory()->create(['slug' => 'strategy-service']);
        $second = Service::factory()->create(['slug' => 'content-service']);

        $response = $this->withToken($this->tokenFor(UserRole::Owner))
            ->postJson('/api/admin/packages', [
                'name' => 'Marketing Starter',
                'category' => PackageCategory::Marketing->value,
                'price' => 1500,
                'discount_amount' => 100,
                'duration_days' => 14,
                'is_featured' => true,
                'items' => [
                    ['service_id' => $first->id, 'quantity' => 1, 'sort_order' => 1, 'notes' => 'ورشة تأسيسية'],
                    ['service_id' => $second->id, 'quantity' => 2, 'sort_order' => 2],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'marketing-starter')
            ->assertJsonPath('data.final_price', '1400.00')
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('package_items', [
            'service_id' => $second->id,
            'quantity' => 2,
            'sort_order' => 2,
        ]);
    }

    public function test_owner_can_replace_package_items_on_update(): void
    {
        $kept = Service::factory()->create(['slug' => 'kept-service']);
        $removed = Service::factory()->create(['slug' => 'removed-service']);
        $added = Service::factory()->create(['slug' => 'added-service']);

        $package = Package::factory()->create(['slug' => 'evolving-package', 'price' => 2000]);
        $package->services()->attach([$kept->id, $removed->id]);

        $this->withToken($this->tokenFor(UserRole::Owner))
            ->patchJson('/api/admin/packages/'.$package->id, [
                'items' => [
                    ['service_id' => $kept->id, 'quantity' => 3, 'sort_order' => 0, 'notes' => 'محدث'],
                    ['service_id' => $added->id, 'quantity' => 1, 'sort_order' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('package_items', [
            'package_id' => $package->id,
            'service_id' => $kept->id,
            'quantity' => 3,
            'notes' => 'محدث',
        ]);
        $this->assertDatabaseMissing('package_items', [
            'package_id' => $package->id,
            'service_id' => $removed->id,
        ]);
        $this->assertDatabaseHas('package_items', [
            'package_id' => $package->id,
            'service_id' => $added->id,
        ]);
    }

    public function test_owner_can_deactivate_and_delete_a_package(): void
    {
        $service = Service::factory()->create(['slug' => 'bundled-service']);
        $package = Package::factory()->create(['slug' => 'temporary-package']);
        $package->services()->attach($service->id);
        $token = $this->tokenFor(UserRole::Owner);

        $this->withToken($token)
            ->patchJson('/api/admin/packages/'.$package->id, ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->withToken($token)
            ->deleteJson('/api/admin/packages/'.$package->id)
            ->assertOk();

        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
        $this->assertDatabaseMissing('package_items', ['package_id' => $package->id]);
        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }

    public function test_service_validation_rejects_bad_input(): void
    {
        $token = $this->tokenFor(UserRole::Owner);

        $this->withToken($token)
            ->postJson('/api/admin/services', [
                'name' => '',
                'category' => 'NOT_A_CATEGORY',
                'base_price' => -10,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['name', 'category', 'base_price']);
    }

    public function test_package_validation_rejects_bad_input(): void
    {
        $token = $this->tokenFor(UserRole::Owner);
        $service = Service::factory()->create(['slug' => 'valid-service']);

        $this->withToken($token)
            ->postJson('/api/admin/packages', [
                'name' => 'Bad Package',
                'category' => PackageCategory::General->value,
                'price' => -1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price']);

        $this->withToken($token)
            ->postJson('/api/admin/packages', [
                'name' => 'Over Discounted',
                'category' => PackageCategory::General->value,
                'price' => 100,
                'discount_amount' => 500,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_amount']);

        $this->withToken($token)
            ->postJson('/api/admin/packages', [
                'name' => 'Unknown Service',
                'category' => PackageCategory::General->value,
                'price' => 100,
                'items' => [['service_id' => 9999]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.service_id']);

        $this->withToken($token)
            ->postJson('/api/admin/packages', [
                'name' => 'Duplicate Service',
                'category' => PackageCategory::General->value,
                'price' => 100,
                'items' => [
                    ['service_id' => $service->id],
                    ['service_id' => $service->id],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.service_id']);
    }

    public function test_admin_endpoints_return_not_found_in_the_project_envelope(): void
    {
        $this->withToken($this->tokenFor(UserRole::Owner))
            ->getJson('/api/admin/services/9999')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Not found.');
    }
}
