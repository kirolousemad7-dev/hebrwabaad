<?php

namespace Tests\Feature;

use App\Enums\PrintingPricingType;
use App\Enums\UserRole;
use App\Models\PrintingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPrintingRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function tokenFor(UserRole $role): string
    {
        return User::factory()->create(['role' => $role])->createToken('auth')->plainTextToken;
    }

    public function test_printing_specialist_can_view_internal_requests(): void
    {
        $request = PrintingRequest::factory()->create([
            'product_name' => 'كروت شخصية قياسية',
        ]);

        $this->withToken($this->tokenFor(UserRole::PrintingSpecialist))
            ->getJson('/api/admin/printing-requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $request->id)
            ->assertJsonPath('data.0.customer.email', $request->user->email)
            ->assertJsonMissingPath('data.0.file_path');
    }

    public function test_owner_and_admin_manager_can_view_internal_requests(): void
    {
        PrintingRequest::factory()->create();

        $this->withToken($this->tokenFor(UserRole::Owner))
            ->getJson('/api/admin/printing-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($this->tokenFor(UserRole::AdminManager))
            ->getJson('/api/admin/printing-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_unauthorized_staff_role_cannot_view_internal_requests(): void
    {
        $this->withToken($this->tokenFor(UserRole::MarketingSpecialist))
            ->getJson('/api/admin/printing-requests')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_guest_cannot_access_internal_printing_requests(): void
    {
        $this->getJson('/api/admin/printing-requests')
            ->assertUnauthorized();
    }

    public function test_printing_specialist_can_set_estimated_price(): void
    {
        $request = PrintingRequest::factory()->create();
        $token = $this->tokenFor(UserRole::PrintingSpecialist);

        $this->withToken($token)
            ->patchJson('/api/admin/printing-requests/'.$request->id.'/pricing', [
                'estimated_price' => 850,
                'pricing_notes' => 'تقدير بعد مراجعة الكمية.',
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing_type', PrintingPricingType::Estimated->value)
            ->assertJsonPath('data.estimated_price', '850.00')
            ->assertJsonPath('data.pricing_notes', 'تقدير بعد مراجعة الكمية.');

        $this->assertDatabaseHas('printing_requests', [
            'id' => $request->id,
            'pricing_type' => PrintingPricingType::Estimated->value,
        ]);
    }

    public function test_printing_specialist_can_mark_request_as_quote_required(): void
    {
        $request = PrintingRequest::factory()->create();

        $this->withToken($this->tokenFor(UserRole::PrintingSpecialist))
            ->patchJson('/api/admin/printing-requests/'.$request->id.'/request-quote', [
                'pricing_notes' => 'شكل مخصص يحتاج عرض سعر.',
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing_type', PrintingPricingType::QuoteRequired->value)
            ->assertJsonPath('data.pricing_notes', 'شكل مخصص يحتاج عرض سعر.');
    }

    public function test_printing_specialist_can_provide_final_quote(): void
    {
        $specialist = User::factory()->printingSpecialist()->create();
        $request = PrintingRequest::factory()->create();

        $this->withToken($specialist->createToken('auth')->plainTextToken)
            ->patchJson('/api/admin/printing-requests/'.$request->id.'/quote', [
                'quoted_price' => 1250.5,
                'pricing_notes' => 'عرض نهائي بعد مراجعة الملف.',
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing_type', PrintingPricingType::QuoteReady->value)
            ->assertJsonPath('data.quoted_price', '1250.50')
            ->assertJsonPath('data.quoted_by.id', $specialist->id);

        $request->refresh();
        $this->assertSame($specialist->id, $request->quoted_by);
        $this->assertNotNull($request->quoted_at);
    }

    public function test_invalid_price_receives_validation_error(): void
    {
        $request = PrintingRequest::factory()->create();
        $token = $this->tokenFor(UserRole::PrintingSpecialist);

        $this->withToken($token)
            ->patchJson('/api/admin/printing-requests/'.$request->id.'/pricing', [
                'estimated_price' => -5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estimated_price']);

        $this->withToken($token)
            ->patchJson('/api/admin/printing-requests/'.$request->id.'/quote', [
                'quoted_price' => 'free',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quoted_price']);
    }

    public function test_specialist_can_download_request_file(): void
    {
        $path = 'printing-requests/9/design.pdf';
        Storage::disk('local')->put($path, 'design-bytes');
        $request = PrintingRequest::factory()->create([
            'file_path' => $path,
            'original_filename' => 'identity.pdf',
        ]);

        $this->withToken($this->tokenFor(UserRole::PrintingSpecialist))
            ->get('/api/admin/printing-requests/'.$request->id.'/file', [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_pricing_filters_work(): void
    {
        PrintingRequest::factory()->create([
            'pricing_type' => PrintingPricingType::Estimated,
            'product_name' => 'كروت',
        ]);
        PrintingRequest::factory()->create([
            'pricing_type' => PrintingPricingType::QuoteRequired,
            'product_name' => 'علب',
        ]);

        $this->withToken($this->tokenFor(UserRole::Owner))
            ->getJson('/api/admin/printing-requests?pricing_type=QUOTE_REQUIRED')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_name', 'علب');
    }

    public function test_existing_auth_endpoints_remain_protected(): void
    {
        $this->getJson('/api/admin/test')
            ->assertUnauthorized();

        $token = User::factory()->create()->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/test')
            ->assertForbidden();
    }
}
