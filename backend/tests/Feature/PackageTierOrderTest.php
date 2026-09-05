<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Package;
use App\Models\PackageTier;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\Payments\CardPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeCardPaymentGateway;
use Tests\TestCase;

class PackageTierOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(CardPaymentGateway::class, new FakeCardPaymentGateway);

        config([
            'payments.paytabs.profile_id' => 154601,
            'payments.paytabs.server_key' => 'test-server-key',
            'payments.paytabs.base_url' => 'https://secure-egypt.paytabs.com',
        ]);

        PaymentSetting::current()->update(['card_enabled' => true]);

        User::factory()->accountManager()->create();
    }

    public function test_order_uses_the_server_side_tier_price_not_the_package_price(): void
    {
        $package = $this->package();
        $tier = PackageTier::factory()->for($package)->create([
            'name' => 'احترافية',
            'slug' => 'professional',
            'price' => '14500.00',
            'currency' => 'SAR',
        ]);

        $customer = User::factory()->create();

        $order = $this->asUser($customer)
            ->postJson('/api/customer/orders', [
                'package_slug' => $package->slug,
                'package_tier_slug' => $tier->slug,
                'amount' => '1.00',
                'currency' => 'EGP',
            ])
            ->assertCreated()
            ->assertJsonPath('data.package_tier.slug', 'professional')
            ->assertJsonPath('data.payable.available', true)
            ->assertJsonPath('data.payable.amount', '14500.00')
            ->assertJsonPath('data.payable.currency', 'SAR')
            ->json('data');

        $stored = Order::query()->findOrFail($order['id']);

        $this->assertSame($tier->id, $stored->package_tier_id);
        $this->assertStringContainsString('احترافية', (string) $stored->title);

        $payment = $this->asUser($customer)
            ->postJson('/api/customer/payments', [
                'order_id' => $stored->id,
                'method' => 'CARD',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '14500.00')
            ->json('data');

        $this->assertSame('14500.00', Payment::query()->findOrFail($payment['id'])->amount);
    }

    public function test_a_tier_from_another_package_or_an_inactive_tier_cannot_be_ordered(): void
    {
        $package = $this->package();
        $other = $this->package(['slug' => 'brand-building-package']);

        $foreignTier = PackageTier::factory()->for($other)->create(['slug' => 'professional']);
        $inactiveTier = PackageTier::factory()->for($package)->inactive()->create(['slug' => 'advanced']);

        $customer = User::factory()->create();

        $this->asUser($customer)
            ->postJson('/api/customer/orders', [
                'package_slug' => $package->slug,
                'package_tier_slug' => $foreignTier->slug,
            ])
            ->assertStatus(422);

        $this->asUser($customer)
            ->postJson('/api/customer/orders', [
                'package_slug' => $package->slug,
                'package_tier_slug' => $inactiveTier->slug,
            ])
            ->assertStatus(422);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_quote_only_package_creates_an_order_that_is_not_payable_yet(): void
    {
        $package = Package::factory()->quote()->create([
            'slug' => 'event-package',
            'price' => '0.00',
            'discount_amount' => '0.00',
            'is_active' => true,
        ]);

        $customer = User::factory()->create();

        $order = $this->asUser($customer)
            ->postJson('/api/customer/orders', ['package_slug' => $package->slug])
            ->assertCreated()
            ->assertJsonPath('data.payable.available', false)
            ->assertJsonPath('data.payable.amount', null)
            ->assertJsonPath('data.payable.reason', 'awaiting_owner_quote')
            ->json('data');

        $this->asUser($customer)
            ->postJson('/api/customer/payments', [
                'order_id' => $order['id'],
                'method' => 'CARD',
            ])
            ->assertStatus(422);

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_unpriced_tier_defers_to_an_owner_quote_instead_of_charging_zero(): void
    {
        $package = $this->package();
        $tier = PackageTier::factory()->for($package)->create([
            'slug' => 'advanced',
            'price' => null,
        ]);

        $customer = User::factory()->create();

        $order = $this->asUser($customer)
            ->postJson('/api/customer/orders', [
                'package_slug' => $package->slug,
                'package_tier_slug' => $tier->slug,
            ])
            ->assertCreated()
            ->assertJsonPath('data.payable.available', false)
            ->assertJsonPath('data.payable.reason', 'awaiting_owner_quote')
            ->json('data');

        $this->asUser($customer)
            ->postJson('/api/customer/payments', [
                'order_id' => $order['id'],
                'method' => 'CARD',
            ])
            ->assertStatus(422);
    }

    public function test_public_package_payload_exposes_tiers_and_pricing_mode_without_management_fields(): void
    {
        $package = $this->package();
        PackageTier::factory()->for($package)->create([
            'name' => 'أساسية',
            'slug' => 'basic',
            'price' => '9000.00',
            'deliverables' => ['هوية بصرية', 'خطة محتوى'],
        ]);
        PackageTier::factory()->for($package)->inactive()->create(['slug' => 'hidden']);

        $response = $this->getJson('/api/packages')
            ->assertOk()
            ->assertJsonPath('data.0.pricing_mode', 'FIXED')
            ->assertJsonPath('data.0.pricing_label', 'سعر ثابت')
            ->assertJsonPath('data.0.is_chargeable', true)
            ->assertJsonPath('data.0.tiers.0.slug', 'basic')
            ->assertJsonPath('data.0.tiers.0.is_priced', true)
            ->assertJsonPath('data.0.tiers.0.deliverables.0', 'هوية بصرية')
            ->json('data');

        $this->assertCount(1, $response[0]['tiers']);
        $this->assertArrayNotHasKey('is_active', $response[0]);
        $this->assertArrayNotHasKey('is_active', $response[0]['tiers'][0]);
    }

    public function test_owner_manages_package_tiers_through_the_existing_admin_endpoints(): void
    {
        $owner = User::factory()->owner()->create();

        $created = $this->asUser($owner)
            ->postJson('/api/admin/packages', [
                'name' => 'باقة بناء البراند',
                'slug' => 'brand-building-package',
                'category' => 'GENERAL',
                'audience' => 'شركات تريد بناء هوية متكاملة',
                'deliverables' => ['هوية بصرية', 'دليل استخدام'],
                'pricing_mode' => 'QUOTE',
                'price' => null,
                'discount_amount' => 0,
                'duration_days' => 45,
                'revision_rounds' => 3,
                'sort_order' => 2,
                'items' => [],
                'tiers' => [
                    ['name' => 'أساسية', 'slug' => 'basic', 'sort_order' => 0],
                    ['name' => 'احترافية', 'slug' => 'professional', 'price' => 18000, 'sort_order' => 1],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.pricing_mode', 'QUOTE')
            ->assertJsonPath('data.is_chargeable', false)
            ->assertJsonPath('data.audience', 'شركات تريد بناء هوية متكاملة')
            ->assertJsonPath('data.revision_rounds', 3)
            ->assertJsonCount(2, 'data.tiers')
            ->json('data');

        $this->asUser($owner)
            ->putJson('/api/admin/packages/'.$created['id'], [
                'name' => 'باقة بناء البراند',
                'category' => 'GENERAL',
                'pricing_mode' => 'FIXED',
                'price' => 21000,
                'discount_amount' => 1000,
                'items' => [],
                'tiers' => [
                    ['name' => 'احترافية', 'slug' => 'professional', 'price' => 21000, 'sort_order' => 0],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing_mode', 'FIXED')
            ->assertJsonCount(1, 'data.tiers')
            ->assertJsonPath('data.tiers.0.slug', 'professional');

        $this->assertSame(1, PackageTier::query()->where('package_id', $created['id'])->count());
    }

    public function test_customers_and_employees_cannot_manage_package_tiers(): void
    {
        $package = $this->package();

        foreach ([User::factory()->create(), User::factory()->accountManager()->create()] as $user) {
            $this->asUser($user)
                ->putJson('/api/admin/packages/'.$package->id, [
                    'name' => $package->name,
                    'category' => 'GENERAL',
                    'price' => 1,
                    'discount_amount' => 0,
                    'items' => [],
                    'tiers' => [['name' => 'مخترقة', 'slug' => 'hacked', 'price' => 1]],
                ])
                ->assertForbidden();
        }

        $this->assertSame(0, PackageTier::query()->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function package(array $attributes = []): Package
    {
        return Package::factory()->create([
            'name' => 'باقة إطلاق مشروع',
            'slug' => 'foundation-package',
            'price' => '9000.00',
            'discount_amount' => '0.00',
            'currency' => 'SAR',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }
}
