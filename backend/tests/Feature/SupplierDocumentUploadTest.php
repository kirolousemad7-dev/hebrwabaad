<?php

namespace Tests\Feature;

use App\Enums\SupplierVisibility;
use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['filesystems.default' => 'local']);
    }

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_owner_upload_download_and_cross_supplier_isolation(): void
    {
        $owner = User::factory()->owner()->create();
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();

        $created = $this->asUser($owner)->post('/api/admin/suppliers/'.$supplierA->id.'/documents', [
            'title' => 'رخصة',
            'visibility' => SupplierVisibility::Internal->value,
            'file' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
        ])->assertCreated()->json('data');

        $document = SupplierDocument::query()->findOrFail($created['id']);

        $this->asUser($owner)
            ->get('/api/admin/suppliers/'.$supplierA->id.'/documents/'.$document->id.'/download')
            ->assertOk();

        $this->asUser($owner)
            ->get('/api/admin/suppliers/'.$supplierB->id.'/documents/'.$document->id.'/download')
            ->assertNotFound();

        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $this->asUser($customer)
            ->get('/api/admin/suppliers/'.$supplierA->id.'/documents/'.$document->id.'/download')
            ->assertForbidden();
    }

    public function test_vendor_can_download_own_vendor_visible_document_only(): void
    {
        $supplierUser = User::factory()->create(['role' => UserRole::Supplier->value]);
        $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id]);
        $otherUser = User::factory()->create(['role' => UserRole::Supplier->value]);
        Supplier::factory()->create(['user_id' => $otherUser->id]);

        $doc = $this->asUser($supplierUser)->post('/api/supplier/documents', [
            'title' => 'ملف مورد',
            'visibility' => SupplierVisibility::Vendor->value,
            'file' => UploadedFile::fake()->create('vendor.pdf', 90, 'application/pdf'),
        ])->assertCreated()->json('data');

        $this->asUser($supplierUser)
            ->get('/api/supplier/documents/'.$doc['id'].'/download')
            ->assertOk();

        $this->asUser($otherUser)
            ->get('/api/supplier/documents/'.$doc['id'].'/download')
            ->assertNotFound();

        $this->asUser($supplierUser)->post('/api/supplier/documents', [
            'title' => 'محاولة عامة',
            'visibility' => SupplierVisibility::Public->value,
            'file' => UploadedFile::fake()->create('public.pdf', 40, 'application/pdf'),
        ])->assertUnprocessable();
    }
}
