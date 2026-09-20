<?php

namespace Tests\Feature;

use App\Models\ManagedFile;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerManagedFileVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

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
     * @param  array<string, mixed>  $overrides
     */
    private function createStoredFile(User $uploader, Project $project, bool $clientVisible, array $overrides = []): ManagedFile
    {
        $stored = fake()->uuid().'.pdf';
        Storage::disk('local')->put('files/'.$stored, 'pdf-bytes');

        return ManagedFile::factory()->create([
            'uploaded_by' => $uploader->id,
            'original_name' => $overrides['original_name'] ?? 'document.pdf',
            'stored_name' => $stored,
            'path' => 'files/'.$stored,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'project_id' => $project->id,
            'is_client_visible' => $clientVisible,
            ...$overrides,
        ]);
    }

    public function test_customer_sees_only_client_visible_files_on_owned_projects(): void
    {
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $owner = User::factory()->owner()->create();
        $projectA = Project::factory()->create(['customer_id' => $customerA->id]);
        $projectB = Project::factory()->create(['customer_id' => $customerB->id]);

        $fileA = $this->createStoredFile($owner, $projectA, true, ['original_name' => 'visible-a.pdf']);
        $fileB = $this->createStoredFile($owner, $projectA, false, ['original_name' => 'internal-b.pdf']);
        $fileC = $this->createStoredFile($owner, $projectB, true, ['original_name' => 'visible-c.pdf']);

        $list = $this->asUser($customerA)
            ->getJson('/api/customer/files')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->json('data.items');

        $this->assertSame([$fileA->id], array_column($list, 'id'));

        $this->asUser($customerA)
            ->getJson('/api/customer/files/'.$fileA->id)
            ->assertOk()
            ->assertJsonPath('data.id', $fileA->id);

        $this->asUser($customerA)
            ->getJson('/api/customer/files/'.$fileB->id)
            ->assertForbidden();

        $this->asUser($customerA)
            ->getJson('/api/customer/files/'.$fileC->id)
            ->assertForbidden();
    }

    public function test_customer_cannot_download_or_preview_internal_file(): void
    {
        $customer = User::factory()->create();
        $owner = User::factory()->owner()->create();
        $project = Project::factory()->create(['customer_id' => $customer->id]);
        $internal = $this->createStoredFile($owner, $project, false);

        $this->asUser($customer)
            ->get('/api/customer/files/'.$internal->id.'/download')
            ->assertForbidden();

        $this->asUser($customer)
            ->get('/api/customer/files/'.$internal->id.'/preview')
            ->assertForbidden();
    }

    public function test_customer_can_download_and_preview_client_visible_file(): void
    {
        $customer = User::factory()->create();
        $owner = User::factory()->owner()->create();
        $project = Project::factory()->create(['customer_id' => $customer->id]);
        $visible = $this->createStoredFile($owner, $project, true);

        $this->asUser($customer)
            ->get('/api/customer/files/'.$visible->id.'/download')
            ->assertOk();

        $this->asUser($customer)
            ->get('/api/customer/files/'.$visible->id.'/preview')
            ->assertOk();
    }

    public function test_customer_cannot_access_another_customers_client_visible_file(): void
    {
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $owner = User::factory()->owner()->create();
        $projectB = Project::factory()->create(['customer_id' => $customerB->id]);
        $fileC = $this->createStoredFile($owner, $projectB, true);

        $this->asUser($customerA)
            ->getJson('/api/customer/files/'.$fileC->id)
            ->assertForbidden();

        $this->asUser($customerA)
            ->get('/api/customer/files/'.$fileC->id.'/download')
            ->assertForbidden();

        $this->asUser($customerA)
            ->get('/api/customer/files/'.$fileC->id.'/preview')
            ->assertForbidden();
    }

    public function test_owner_sees_internal_and_client_visible_files(): void
    {
        $customer = User::factory()->create();
        $owner = User::factory()->owner()->create();
        $project = Project::factory()->create(['customer_id' => $customer->id]);
        $internal = $this->createStoredFile($owner, $project, false);
        $visible = $this->createStoredFile($owner, $project, true);

        $this->asUser($owner)
            ->getJson('/api/workspace/files/'.$internal->id)
            ->assertOk()
            ->assertJsonPath('data.is_client_visible', false);

        $this->asUser($owner)
            ->getJson('/api/workspace/files/'.$visible->id)
            ->assertOk()
            ->assertJsonPath('data.is_client_visible', true);

        $this->asUser($owner)
            ->get('/api/workspace/files/'.$internal->id.'/download')
            ->assertOk();
    }

    public function test_authorized_staff_sees_internal_file(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $customer = User::factory()->create();
        $project = Project::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
        ]);
        ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $developer->id,
            'role' => 'member',
        ]);
        $internal = $this->createStoredFile($manager, $project, false);

        $this->asUser($manager)
            ->getJson('/api/workspace/files/'.$internal->id)
            ->assertOk()
            ->assertJsonPath('data.is_client_visible', false);

        $this->asUser($developer)
            ->getJson('/api/workspace/files/'.$internal->id)
            ->assertOk();

        $this->asUser($developer)
            ->get('/api/workspace/files/'.$internal->id.'/download')
            ->assertOk();
    }

    public function test_existing_files_default_to_not_client_visible(): void
    {
        $this->assertTrue(Schema::hasColumn('files', 'is_client_visible'));

        $uploader = User::factory()->owner()->create();
        $project = Project::factory()->create();
        $file = ManagedFile::factory()->create([
            'uploaded_by' => $uploader->id,
            'project_id' => $project->id,
        ]);

        $this->assertFalse($file->fresh()->is_client_visible);

        $customer = User::factory()->create();
        $owned = Project::factory()->create(['customer_id' => $customer->id]);
        $legacy = $this->createStoredFile($uploader, $owned, false);

        $this->asUser($customer)
            ->getJson('/api/customer/files')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);

        $this->asUser($customer)
            ->getJson('/api/customer/files/'.$legacy->id)
            ->assertForbidden();
    }

    public function test_cross_project_file_idor_is_denied(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $owner = User::factory()->owner()->create();
        $projectA = Project::factory()->create(['customer_id' => $customer->id]);
        $projectB = Project::factory()->create(['customer_id' => $otherCustomer->id]);
        $fileOnB = $this->createStoredFile($owner, $projectB, true);

        $this->asUser($customer)
            ->getJson('/api/customer/files?project_id='.$projectA->id)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);

        $this->asUser($customer)
            ->getJson('/api/customer/files?project_id='.$projectB->id)
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->asUser($customer)
            ->getJson('/api/customer/files/'.$fileOnB->id)
            ->assertForbidden();
    }

    public function test_customer_cannot_spoof_is_client_visible_false_on_upload(): void
    {
        $customer = User::factory()->create();
        $project = Project::factory()->create(['customer_id' => $customer->id]);

        $created = $this->asUser($customer)
            ->post('/api/customer/files', [
                'file' => UploadedFile::fake()->create('mine.pdf', 40, 'application/pdf'),
                'project_id' => $project->id,
                'is_client_visible' => false,
            ])
            ->assertCreated()
            ->json('data');

        $file = ManagedFile::query()->findOrFail($created['id']);
        $this->assertTrue($file->is_client_visible);

        $this->asUser($customer)
            ->getJson('/api/customer/files/'.$file->id)
            ->assertOk();
    }

    public function test_customer_dashboard_excludes_internal_files(): void
    {
        $customer = User::factory()->create();
        $owner = User::factory()->owner()->create();
        $project = Project::factory()->create(['customer_id' => $customer->id]);
        $visible = $this->createStoredFile($owner, $project, true, ['original_name' => 'share.pdf']);
        $this->createStoredFile($owner, $project, false, ['original_name' => 'secret.pdf']);

        $this->asUser($customer)
            ->getJson('/api/customer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.files.value', 1)
            ->assertJsonPath('data.files.items.0.id', $visible->id)
            ->assertJsonCount(1, 'data.files.items');
    }
}
