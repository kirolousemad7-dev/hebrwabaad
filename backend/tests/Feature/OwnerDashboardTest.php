<?php

namespace Tests\Feature;

use App\Enums\ConsultationStatus;
use App\Enums\PrintingPricingType;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Consultation;
use App\Models\ConsultationLead;
use App\Models\PrintingRequest;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('auth')->plainTextToken;
    }

    public function test_guest_cannot_access_owner_dashboard(): void
    {
        $this->getJson('/api/admin/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_customer_cannot_access_owner_dashboard(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_employee_roles_cannot_access_owner_dashboard(): void
    {
        foreach ([UserRole::AdminManager, UserRole::PrintingSpecialist, UserRole::MarketingSpecialist] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->withToken($this->tokenFor($user))
                ->getJson('/api/admin/dashboard')
                ->assertForbidden()
                ->assertJsonPath('message', 'Forbidden.');
        }
    }

    public function test_owner_receives_aggregated_dashboard_metrics_from_real_records(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->count(3)->create();
        User::factory()->adminManager()->create();
        User::factory()->printingSpecialist()->create();
        User::factory()->create(['role' => UserRole::WebDeveloper]);

        Supplier::factory()->count(2)->create(['is_active' => true]);
        Supplier::factory()->create(['is_active' => false]);

        $unpriced = PrintingRequest::factory()->create(['pricing_type' => null]);
        PrintingRequest::factory()->create([
            'pricing_type' => PrintingPricingType::QuoteRequired,
        ]);
        PrintingRequest::factory()->create([
            'pricing_type' => PrintingPricingType::Estimated,
            'estimated_price' => 120,
        ]);
        $quoted = PrintingRequest::factory()->create([
            'product_name' => 'أكياس تسوق فاخرة',
            'pricing_type' => PrintingPricingType::QuoteReady,
            'quoted_price' => 280,
            'quoted_at' => now()->addMinute(),
            'quoted_by' => $owner->id,
        ]);

        $customer = User::query()->where('role', UserRole::Customer)->firstOrFail();
        $staff = User::query()->where('role', UserRole::WebDeveloper)->firstOrFail();
        Project::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $staff->id,
            'status' => ProjectStatus::InProgress,
        ]);

        $consultation = Consultation::query()->create([
            'public_token' => Str::random(64),
            'status' => ConsultationStatus::InProgress,
            'state' => [],
        ]);
        ConsultationLead::query()->create([
            'consultation_id' => $consultation->id,
            'name' => 'مطعم تجريبي',
            'email' => 'lead@example.com',
            'contact_method' => 'email',
        ]);

        $response = $this->withToken($this->tokenFor($owner))
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.overview.revenue.available', false)
            ->assertJsonPath('data.overview.orders.available', true)
            ->assertJsonPath('data.overview.orders.value', 0)
            ->assertJsonPath('data.overview.projects.available', true)
            ->assertJsonPath('data.overview.projects.value', 1)
            ->assertJsonPath('data.overview.projects.secondary.in_progress', 1)
            ->assertJsonPath('data.overview.leads.available', true)
            ->assertJsonPath('data.overview.leads.value', 1)
            ->assertJsonPath('data.overview.customers.available', true)
            ->assertJsonPath('data.overview.customers.value', 7)
            ->assertJsonPath('data.overview.employees.available', true)
            ->assertJsonPath('data.overview.employees.value', 3)
            ->assertJsonPath('data.overview.suppliers.available', true)
            ->assertJsonPath('data.overview.suppliers.value', 3)
            ->assertJsonPath('data.overview.suppliers.secondary.active', 2)
            ->assertJsonPath('data.overview.pending_requests.available', true)
            ->assertJsonPath('data.overview.pending_requests.value', 2)
            ->assertJsonPath('data.overview.pending_requests.secondary.awaiting_pricing', 1)
            ->assertJsonPath('data.overview.pending_requests.secondary.quote_required', 1)
            ->assertJsonPath('data.pricing_breakdown.unpriced', 1)
            ->assertJsonPath('data.pricing_breakdown.estimated', 1)
            ->assertJsonPath('data.pricing_breakdown.quote_required', 1)
            ->assertJsonPath('data.pricing_breakdown.quote_ready', 1);

        $response->assertJsonPath('data.overview.revenue.value', null);

        $pendingIds = collect($response->json('data.pending_requests'))->pluck('id')->all();
        $this->assertContains($unpriced->id, $pendingIds);
        $this->assertNotContains($quoted->id, $pendingIds);

        $activityTypes = collect($response->json('data.recent_activity'))->pluck('type')->all();
        $this->assertContains('printing_request_submitted', $activityTypes);
        $this->assertContains('printing_quote_ready', $activityTypes);
        $this->assertContains('customer_registered', $activityTypes);
        $this->assertContains('supplier_added', $activityTypes);

        $this->assertCount(14, $response->json('data.request_activity'));
        $this->assertGreaterThanOrEqual(
            4,
            collect($response->json('data.request_activity'))->sum('count')
        );

        $this->assertStringNotContainsString('file_path', $response->getContent());
    }

    public function test_owner_dashboard_empty_database_returns_zero_counts_without_fake_revenue(): void
    {
        $owner = User::factory()->owner()->create();

        $this->withToken($this->tokenFor($owner))
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.overview.customers.value', 0)
            ->assertJsonPath('data.overview.employees.value', 0)
            ->assertJsonPath('data.overview.suppliers.value', 0)
            ->assertJsonPath('data.overview.pending_requests.value', 0)
            ->assertJsonPath('data.overview.revenue.available', false)
            ->assertJsonPath('data.overview.orders.available', true)
            ->assertJsonPath('data.overview.orders.value', 0)
            ->assertJsonPath('data.overview.projects.available', true)
            ->assertJsonPath('data.overview.projects.value', 0)
            ->assertJsonPath('data.overview.leads.available', true)
            ->assertJsonPath('data.overview.leads.value', 0)
            ->assertJsonCount(0, 'data.recent_activity')
            ->assertJsonCount(0, 'data.pending_requests');
    }
}
