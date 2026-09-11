<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ManagedFile;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\PlatformSetting;
use App\Models\PrintingProduct;
use App\Models\PrintingProductCategory;
use App\Models\PrintingRequest;
use App\Models\Project;
use App\Models\QuoteRequest;
use App\Models\Sector;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Platform\ProductionDataCleanupService;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ProductionDataCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_modifies_nothing(): void
    {
        $customer = User::factory()->create(['email' => 'customer@example.com']);
        Order::factory()->create(['customer_id' => $customer->id]);
        $beforeUsers = User::query()->count();
        $beforeOrders = Order::query()->count();

        $this->artisan('platform:clean-production-data', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame($beforeUsers, User::query()->count());
        $this->assertSame($beforeOrders, Order::query()->count());
    }

    public function test_confirm_removes_runtime_preserves_catalog_and_keeps_single_owner(): void
    {
        Storage::fake('local');

        $service = Service::factory()->create(['name' => 'Keep Service']);
        $package = Package::factory()->create(['name' => 'Keep Package']);
        $sector = Sector::factory()->create(['name_ar' => 'قطاع محفوظ']);
        $this->seed(CrmSettingsSeeder::class);

        $category = PrintingProductCategory::query()->create([
            'name_ar' => 'فئة طباعة',
            'slug' => 'print-cat-keep',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        PrintingProduct::query()->create([
            'category_id' => $category->id,
            'name_ar' => 'منتج طباعة',
            'slug' => 'print-product-keep',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        PlatformSetting::query()->updateOrCreate(
            ['key' => 'brand'],
            ['value' => ['name_ar' => 'حبر وأبعاد']],
        );

        PaymentSetting::current()->fill([
            'bank_transfer_enabled' => true,
            'bank_name' => 'بنك تجريبي',
            'bank_account_number' => '00000000000000',
            'bank_iban' => 'SA0000000000000000000000',
            'bank_instructions' => 'حساب تجريبي',
        ])->save();

        $customer = User::factory()->create(['email' => 'customer@example.com', 'name' => 'عميل وهمي']);
        $employee = User::factory()->graphicDesigner()->create(['email' => 'employee@example.com']);
        $manager = User::factory()->accountManager()->create(['email' => 'manager@example.com']);
        User::factory()->owner()->create(['email' => 'old.owner@example.com']);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
        ]);
        $project = Project::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
        ]);
        PrintingRequest::factory()->create(['user_id' => $customer->id]);
        Payment::factory()->create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
        ]);
        QuoteRequest::factory()->create(['customer_id' => $customer->id]);

        if (Schema::hasTable('crm_leads') && Schema::hasTable('crm_pipeline_stages')) {
            $stageId = DB::table('crm_pipeline_stages')->value('id');
            if ($stageId) {
                DB::table('crm_leads')->insert([
                    'reference' => 'LD-TEST-0001',
                    'full_name' => 'Demo Lead',
                    'email' => 'lead@example.com',
                    'status' => 'NEW',
                    'priority' => 'MEDIUM',
                    'stage_id' => $stageId,
                    'customer_id' => $customer->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Supplier::factory()->create(['name' => 'مورد تجريبي', 'slug' => 'demo-supplier']);

        $path = 'runtime/demo-upload.txt';
        Storage::disk('local')->put($path, 'demo');
        ManagedFile::factory()->create([
            'uploaded_by' => $customer->id,
            'disk' => 'local',
            'path' => $path,
            'order_id' => $order->id,
        ]);

        $token = $customer->createToken('demo')->plainTextToken;
        $this->assertNotSame('', $token);

        $this->artisan('platform:clean-production-data', ['--confirm' => true])
            ->assertSuccessful();

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Project::query()->count());
        $this->assertSame(0, PrintingRequest::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, QuoteRequest::query()->count());
        $this->assertSame(0, Supplier::query()->count());
        $this->assertSame(0, ManagedFile::query()->count());
        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->assertFalse(User::query()->where('email', 'customer@example.com')->exists());
        $this->assertFalse(User::query()->where('email', 'employee@example.com')->exists());
        $this->assertFalse(User::query()->where('email', 'old.owner@example.com')->exists());

        if (Schema::hasTable('crm_leads')) {
            $this->assertSame(0, DB::table('crm_leads')->count());
        }

        $this->assertSame(1, User::query()->count());
        $owner = User::query()->first();
        $this->assertNotNull($owner);
        $this->assertSame(ProductionDataCleanupService::OWNER_EMAIL, $owner->email);
        $this->assertSame(UserRole::Owner, $owner->role);
        $this->assertTrue($owner->is_active);
        $this->assertTrue(Hash::check(ProductionDataCleanupService::OWNER_PASSWORD, $owner->password));

        $this->assertTrue(Service::query()->whereKey($service->id)->exists());
        $this->assertTrue(Package::query()->whereKey($package->id)->exists());
        $this->assertTrue(Sector::query()->whereKey($sector->id)->exists());
        $this->assertTrue(PrintingProduct::query()->where('slug', 'print-product-keep')->exists());
        $this->assertTrue(PlatformSetting::query()->where('key', 'brand')->exists());
        $this->assertGreaterThan(0, DB::table('crm_pipeline_stages')->count());
        $this->assertGreaterThan(0, DB::table('crm_lead_sources')->count());

        $settings = PaymentSetting::current()->fresh();
        $this->assertFalse((bool) $settings->bank_transfer_enabled);
        $this->assertNull($settings->bank_account_number);

        $this->assertFalse(Storage::disk('local')->exists($path));

        // Idempotent second run
        $this->artisan('platform:clean-production-data', ['--confirm' => true])
            ->assertSuccessful();
        $this->assertSame(1, User::query()->count());
        $this->assertSame(ProductionDataCleanupService::OWNER_EMAIL, User::query()->value('email'));
        $this->assertTrue(Service::query()->whereKey($service->id)->exists());
    }

    public function test_owner_can_authenticate_after_cleanup(): void
    {
        User::factory()->create(['email' => 'noise@example.com']);

        $this->artisan('platform:clean-production-data', ['--confirm' => true])
            ->assertSuccessful();

        $this->postJson('/api/auth/login', [
            'email' => ProductionDataCleanupService::OWNER_EMAIL,
            'password' => ProductionDataCleanupService::OWNER_PASSWORD,
        ])->assertOk()
            ->assertJsonPath('data.user.email', ProductionDataCleanupService::OWNER_EMAIL)
            ->assertJsonPath('data.user.role', UserRole::Owner->value);
    }
}
