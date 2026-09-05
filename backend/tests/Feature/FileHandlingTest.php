<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Models\ManagedFile;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileHandlingTest extends TestCase
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

    public function test_guest_cannot_access_files(): void
    {
        $this->getJson('/api/customer/files')->assertUnauthorized();
        $this->postJson('/api/customer/files')->assertUnauthorized();
        $this->getJson('/api/workspace/files')->assertUnauthorized();
        $this->getJson('/api/customer/files/1/download')->assertUnauthorized();
    }

    public function test_inactive_customer_cannot_access_files(): void
    {
        $customer = User::factory()->inactive()->create();

        $this->asUser($customer)
            ->getJson('/api/customer/files')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');
    }

    public function test_customer_can_upload_valid_project_file_and_download_it(): void
    {
        $customer = User::factory()->create();
        $project = Project::factory()->create(['customer_id' => $customer->id]);

        $created = $this->asUser($customer)
            ->post('/api/customer/files', [
                'file' => UploadedFile::fake()->create('homepage-design.png', 200, 'image/png'),
                'project_id' => $project->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'homepage-design.png')
            ->assertJsonPath('data.extension', 'png')
            ->assertJsonPath('data.can_preview', true)
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonMissingPath('data.path')
            ->assertJsonMissingPath('data.disk')
            ->assertJsonMissingPath('data.stored_name')
            ->json('data');

        $file = ManagedFile::query()->findOrFail($created['id']);
        $this->assertNotSame('homepage-design.png', $file->stored_name);
        $this->assertSame('local', $file->disk);
        $this->assertStringStartsWith('files/', $file->path);
        $this->assertStringNotContainsString('..', $file->path);
        Storage::disk('local')->assertExists($file->path);

        $this->asUser($customer)
            ->getJson('/api/customer/files')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.original_name', 'homepage-design.png')
            ->assertJsonMissingPath('data.items.0.path');

        $this->asUser($customer)
            ->get('/api/customer/files/'.$file->id.'/download')
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->asUser($customer)
            ->get('/api/customer/files/'.$file->id.'/preview')
            ->assertOk();

        $this->asUser($customer)
            ->getJson('/api/customer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.files.available', true)
            ->assertJsonPath('data.summary.files.value', 1)
            ->assertJsonPath('data.files.items.0.id', $file->id);
    }

    public function test_invalid_and_dangerous_uploads_are_rejected(): void
    {
        $customer = User::factory()->create();
        $project = Project::factory()->create(['customer_id' => $customer->id]);

        $this->asUser($customer)
            ->post('/api/customer/files', [
                'file' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
                'project_id' => $project->id,
            ])
            ->assertUnprocessable();

        $this->asUser($customer)
            ->post('/api/customer/files', [
                'file' => UploadedFile::fake()->create('malware.exe', 20, 'application/x-msdownload'),
                'project_id' => $project->id,
            ])
            ->assertUnprocessable();

        $this->asUser($customer)
            ->post('/api/customer/files', [
                'file' => UploadedFile::fake()->create('shell.php', 20, 'application/x-php'),
                'project_id' => $project->id,
            ])
            ->assertUnprocessable();

        $this->asUser($customer)
            ->post('/api/customer/files', [
                'file' => UploadedFile::fake()->create('image.jpg', 20, 'application/x-msdownload'),
                'project_id' => $project->id,
            ])
            ->assertUnprocessable();

        $this->asUser($customer)
            ->post('/api/customer/files', [
                'file' => UploadedFile::fake()->create('huge.pdf', 10 * 1024 + 1, 'application/pdf'),
                'project_id' => $project->id,
            ])
            ->assertUnprocessable();

        $this->assertSame(0, ManagedFile::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('files'));
    }

    public function test_customer_cannot_spoof_another_customers_project_or_upload_to_task(): void
    {
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $projectB = Project::factory()->create(['customer_id' => $customerB->id]);
        $task = Task::factory()->create([
            'project_id' => $projectB->id,
            'assigned_to' => User::factory()->webDeveloper(),
            'created_by' => User::factory()->accountManager(),
        ]);

        $this->asUser($customerA)
            ->post('/api/customer/files', [
                'file' => UploadedFile::fake()->create('brief.pdf', 40, 'application/pdf'),
                'project_id' => $projectB->id,
            ])
            ->assertUnprocessable();

        $this->asUser($customerA)
            ->post('/api/customer/files', [
                'file' => UploadedFile::fake()->create('brief.pdf', 40, 'application/pdf'),
                'task_id' => $task->id,
            ])
            ->assertUnprocessable();

        $this->assertSame(0, ManagedFile::query()->count());
    }

    public function test_customer_cannot_access_another_customers_file(): void
    {
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $projectB = Project::factory()->create(['customer_id' => $customerB->id]);
        $file = $this->storeFile($customerB, ['project_id' => $projectB->id]);

        $this->asUser($customerA)
            ->getJson('/api/customer/files')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->asUser($customerA)
            ->getJson('/api/customer/files/'.$file->id)
            ->assertForbidden();

        $this->asUser($customerA)
            ->get('/api/customer/files/'.$file->id.'/download')
            ->assertForbidden();

        $this->asUser($customerA)
            ->get('/api/customer/files/'.$file->id.'/preview')
            ->assertForbidden();

        $this->asUser($customerA)
            ->getJson('/api/workspace/files/'.$file->id)
            ->assertForbidden();
    }

    public function test_employee_can_access_authorized_project_file_and_not_unrelated_file(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $other = User::factory()->graphicDesigner()->create();
        $customer = User::factory()->create();
        $project = Project::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
        ]);
        $otherCustomer = User::factory()->create();
        $unrelated = Project::factory()->create([
            'customer_id' => $otherCustomer->id,
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'priority' => TaskPriority::Medium,
        ]);

        $authorized = $this->storeFile($customer, ['project_id' => $project->id]);
        $secret = $this->storeFile($otherCustomer, ['project_id' => $unrelated->id]);

        $this->asUser($developer)
            ->getJson('/api/workspace/files')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id', $authorized->id);

        $this->asUser($developer)
            ->get('/api/workspace/files/'.$authorized->id.'/download')
            ->assertOk();

        $this->asUser($developer)
            ->getJson('/api/workspace/files/'.$secret->id)
            ->assertForbidden();

        $this->asUser($other)
            ->getJson('/api/workspace/files/'.$authorized->id)
            ->assertForbidden();

        $this->asUser($manager)
            ->getJson('/api/workspace/files/'.$authorized->id)
            ->assertOk()
            ->assertJsonPath('data.id', $authorized->id);

        $owner = User::factory()->owner()->create();
        $this->asUser($owner)
            ->get('/api/workspace/files/'.$authorized->id.'/download')
            ->assertOk();
    }

    public function test_account_manager_can_upload_to_managed_project_and_customer_can_see_it(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $project = Project::factory()->create([
            'customer_id' => $customer->id,
            'account_manager_id' => $manager->id,
        ]);

        $created = $this->asUser($manager)
            ->post('/api/workspace/files', [
                'file' => UploadedFile::fake()->create('brand-guidelines.pdf', 80, 'application/pdf'),
                'project_id' => $project->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'brand-guidelines.pdf')
            ->assertJsonPath('data.can_preview', true)
            ->json('data');

        $this->asUser($customer)
            ->getJson('/api/customer/files/'.$created['id'])
            ->assertOk()
            ->assertJsonPath('data.original_name', 'brand-guidelines.pdf');
    }

    public function test_nonexistent_file_is_not_found_and_traversal_names_are_stored_safely(): void
    {
        $customer = User::factory()->create();
        $project = Project::factory()->create(['customer_id' => $customer->id]);

        $this->asUser($customer)
            ->getJson('/api/customer/files/99999')
            ->assertNotFound();

        $created = $this->asUser($customer)
            ->post('/api/customer/files', [
                'file' => UploadedFile::fake()->create('../../passwd.pdf', 30, 'application/pdf'),
                'project_id' => $project->id,
            ])
            ->assertCreated()
            ->json('data');

        $file = ManagedFile::query()->findOrFail($created['id']);
        $this->assertSame('passwd.pdf', $file->original_name);
        $this->assertStringStartsWith('files/', $file->path);
        $this->assertStringNotContainsString('..', $file->path);
    }

    public function test_customer_cannot_use_workspace_file_endpoints(): void
    {
        $customer = User::factory()->create();
        $project = Project::factory()->create(['customer_id' => $customer->id]);
        $file = $this->storeFile($customer, ['project_id' => $project->id]);

        $this->asUser($customer)
            ->getJson('/api/workspace/files')
            ->assertForbidden();

        $this->asUser($customer)
            ->getJson('/api/workspace/files/'.$file->id)
            ->assertForbidden();
    }

    /**
     * @param  array<string, int>  $context
     */
    private function storeFile(User $actor, array $context): ManagedFile
    {
        $id = $this->asUser($actor)
            ->post('/api/customer/files', [
                'file' => UploadedFile::fake()->create('brief.pdf', 40, 'application/pdf'),
                ...$context,
            ])
            ->assertCreated()
            ->json('data.id');

        return ManagedFile::query()->findOrFail($id);
    }
}
