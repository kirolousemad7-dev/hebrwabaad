<?php

namespace Tests\Feature;

use App\Enums\PrintingRequestStatus;
use App\Enums\UserRole;
use App\Models\PrintingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrintingRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'product_slug' => 'standard-business-cards',
            'product_name' => 'كروت شخصية قياسية',
            'width' => '9',
            'height' => '5',
            'dimension_unit' => 'CM',
            'shape' => 'RECTANGLE',
            'material' => 'ورق مطفي',
            'quantity' => '100',
            'printing_method' => 'DIGITAL',
            'finishing' => json_encode(['NONE']),
            'file' => UploadedFile::fake()->create('brief.pdf', 200, 'application/pdf'),
            'required_date' => now('Asia/Riyadh')->addDay()->toDateString(),
            'notes' => 'لون الهوية كما في الملف.',
        ], $overrides);
    }

    private function tokenFor(UserRole $role = UserRole::Customer): string
    {
        return User::factory()->create(['role' => $role])->createToken('auth')->plainTextToken;
    }

    public function test_guest_cannot_submit_a_printing_request(): void
    {
        $this->post('/api/printing-requests', $this->validPayload(), [
            'Accept' => 'application/json',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->assertDatabaseCount('printing_requests', 0);
    }

    public function test_authenticated_customer_can_submit_a_valid_request(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->withToken($token)
            ->post('/api/printing-requests', $this->validPayload(), [
                'Accept' => 'application/json',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product_slug', 'standard-business-cards')
            ->assertJsonPath('data.product_name', 'كروت شخصية قياسية')
            ->assertJsonPath('data.quantity', 100)
            ->assertJsonPath('data.status', PrintingRequestStatus::Pending->value)
            ->assertJsonPath('data.pricing_type', 'ESTIMATED')
            ->assertJsonPath('data.estimated_price', '85.00')
            ->assertJsonMissingPath('data.file_path');

        $this->assertDatabaseHas('printing_requests', [
            'user_id' => $user->id,
            'product_slug' => 'standard-business-cards',
            'quantity' => 100,
            'status' => PrintingRequestStatus::Pending->value,
        ]);

        $stored = PrintingRequest::query()->first();
        $this->assertNotNull($stored);
        $this->assertSame($user->id, $stored->user_id);
        $this->assertSame(PrintingRequestStatus::Pending, $stored->status);
        $this->assertStringStartsWith('printing-requests/'.$user->id.'/', $stored->file_path);
        $this->assertNotSame('brief.pdf', basename($stored->file_path));
        Storage::disk('local')->assertExists($stored->file_path);
    }

    public function test_custom_product_submission_requires_a_quote_instead_of_an_estimate(): void
    {
        $token = $this->tokenFor();

        $this->withToken($token)
            ->post('/api/printing-requests', $this->validPayload([
                'product_slug' => 'custom-printed-product',
                'product_name' => 'منتج مطبوع حسب الطلب',
            ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.status', PrintingRequestStatus::Pending->value)
            ->assertJsonPath('data.pricing_type', 'QUOTE_REQUIRED')
            ->assertJsonPath('data.estimated_price', null);
    }

    public function test_customer_can_list_and_view_own_request(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->post('/api/printing-requests', $this->validPayload(), ['Accept' => 'application/json'])
            ->assertCreated();

        $id = PrintingRequest::query()->first()->id;

        $this->withToken($token)
            ->getJson('/api/printing-requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.product_slug', 'standard-business-cards')
            ->assertJsonMissingPath('data.0.file_path');

        $this->withToken($token)
            ->getJson('/api/printing-requests/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.material', 'ورق مطفي');
    }

    public function test_customer_cannot_view_another_customers_request(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $request = PrintingRequest::factory()->create(['user_id' => $owner->id]);

        $this->withToken($other->createToken('auth')->plainTextToken)
            ->getJson('/api/printing-requests/'.$request->id)
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_guest_cannot_list_printing_requests(): void
    {
        $this->getJson('/api/printing-requests')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_customer_cannot_access_internal_printing_request_list(): void
    {
        $this->withToken($this->tokenFor())
            ->getJson('/api/admin/printing-requests')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_customer_cannot_mutate_pricing(): void
    {
        $user = User::factory()->create();
        $request = PrintingRequest::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/admin/printing-requests/'.$request->id.'/pricing', [
                'estimated_price' => 10,
            ])
            ->assertForbidden();

        $this->withToken($token)
            ->patchJson('/api/printing-requests/'.$request->id.'/pricing', [
                'estimated_price' => 10,
            ])
            ->assertNotFound();
    }

    public function test_customer_can_download_own_file_but_not_another_customers_file(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->post('/api/printing-requests', $this->validPayload(), ['Accept' => 'application/json'])
            ->assertCreated();

        $own = PrintingRequest::query()->first();
        $other = PrintingRequest::factory()->create([
            'file_path' => 'printing-requests/other/secret.pdf',
        ]);
        Storage::disk('local')->put($other->file_path, 'secret');

        $this->withToken($token)
            ->get('/api/printing-requests/'.$own->id.'/file', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->withToken($token)
            ->getJson('/api/printing-requests/'.$other->id.'/file')
            ->assertForbidden();
    }

    public function test_required_fields_are_validated(): void
    {
        $this->withToken($this->tokenFor())
            ->post('/api/printing-requests', [], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors([
                'product_slug',
                'product_name',
                'width',
                'height',
                'dimension_unit',
                'shape',
                'material',
                'quantity',
                'printing_method',
                'finishing',
                'file',
                'required_date',
            ]);
    }

    public function test_unknown_product_slug_is_rejected(): void
    {
        $this->withToken($this->tokenFor())
            ->post('/api/printing-requests', $this->validPayload([
                'product_slug' => 'not-a-real-product',
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_slug']);
    }

    public function test_invalid_quantity_is_rejected(): void
    {
        $token = $this->tokenFor();

        $this->withToken($token)
            ->post('/api/printing-requests', $this->validPayload([
                'quantity' => '0',
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);

        $this->withToken($token)
            ->post('/api/printing-requests', $this->validPayload([
                'quantity' => '-5',
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);

        $this->withToken($token)
            ->post('/api/printing-requests', $this->validPayload([
                'quantity' => '1.5',
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_invalid_dimensions_are_rejected(): void
    {
        $token = $this->tokenFor();

        $this->withToken($token)
            ->post('/api/printing-requests', $this->validPayload([
                'width' => '0',
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['width']);

        $this->withToken($token)
            ->post('/api/printing-requests', $this->validPayload([
                'height' => '-2',
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['height']);
    }

    public function test_past_required_date_is_rejected(): void
    {
        $this->withToken($this->tokenFor())
            ->post('/api/printing-requests', $this->validPayload([
                'required_date' => now('Asia/Riyadh')->subDay()->toDateString(),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['required_date']);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $this->withToken($this->tokenFor())
            ->post('/api/printing-requests', $this->validPayload([
                'file' => UploadedFile::fake()->create('malware.exe', 20, 'application/x-msdownload'),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('printing_requests', 0);
    }

    public function test_oversized_file_is_rejected(): void
    {
        $this->withToken($this->tokenFor())
            ->post('/api/printing-requests', $this->validPayload([
                'file' => UploadedFile::fake()->create('huge.pdf', 10 * 1024 + 1, 'application/pdf'),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_owner_cannot_submit_a_printing_request(): void
    {
        $this->withToken($this->tokenFor(UserRole::Owner))
            ->post('/api/printing-requests', $this->validPayload(), [
                'Accept' => 'application/json',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');

        $this->assertDatabaseCount('printing_requests', 0);
    }
}
