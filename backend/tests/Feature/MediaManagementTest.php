<?php

namespace Tests\Feature;

use App\Enums\MediaVisibility;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Media;
use App\Models\Package;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\SupplierPortfolioItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaManagementTest extends TestCase
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

    /**
     * @return array{manager: User, task: Task, project: Project}
     */
    private function seededTask(): array
    {
        $manager = User::factory()->accountManager()->create();
        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => User::factory()->create(['role' => UserRole::Customer->value])->id,
        ]);
        $task = Task::factory()->create([
            'title' => 'مهمة ملفات',
            'assigned_to' => User::factory()->webDeveloper()->create()->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'priority' => TaskPriority::Medium,
            'status' => TaskStatus::Todo,
        ]);

        return compact('manager', 'task', 'project');
    }

    public function test_upload_auto_attaches_to_task_entity(): void
    {
        ['manager' => $manager, 'task' => $task] = $this->seededTask();

        $created = $this->asUser($manager)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->create('brief.pdf', 120, 'application/pdf'),
                'entity_type' => 'task',
                'entity_id' => $task->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'brief.pdf')
            ->assertJsonPath('data.entity_type', 'task')
            ->assertJsonPath('data.entity_id', $task->id)
            ->assertJsonPath('data.visibility', MediaVisibility::Internal->value)
            ->assertJsonMissingPath('data.path')
            ->assertJsonMissingPath('data.disk')
            ->json('data');

        $media = Media::query()->findOrFail($created['id']);
        $this->assertSame('task', $media->owner_type);
        $this->assertSame($task->id, (int) $media->owner_id);
        $this->assertNotNull($media->checksum);
        Storage::disk('local')->assertExists($media->path);

        $this->assertTrue($task->fresh()->media()->whereKey($media->id)->exists());
    }

    public function test_upload_auto_attaches_to_supplier_and_supplier_visibility(): void
    {
        $owner = User::factory()->owner()->create();
        $supplier = Supplier::factory()->create();

        $created = $this->asUser($owner)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->image('logo.png', 40, 40),
                'entity_type' => 'supplier',
                'entity_id' => $supplier->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.entity_type', 'supplier')
            ->assertJsonPath('data.visibility', MediaVisibility::Supplier->value)
            ->json('data');

        $this->assertSame('supplier', Media::query()->findOrFail($created['id'])->owner_type);
    }

    public function test_download_requires_authorization(): void
    {
        ['manager' => $manager, 'task' => $task] = $this->seededTask();
        $stranger = User::factory()->create(['role' => UserRole::Customer->value]);

        $id = $this->asUser($manager)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->create('secret.pdf', 80, 'application/pdf'),
                'entity_type' => 'task',
                'entity_id' => $task->id,
                'visibility' => MediaVisibility::Internal->value,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($manager)
            ->get('/api/media/'.$id.'/download')
            ->assertOk();

        $this->asUser($stranger)
            ->get('/api/media/'.$id.'/download')
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/media/'.$id.'/download')->assertUnauthorized();
    }

    public function test_visibility_gates_customer_and_supplier_access(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => $customer->id,
        ]);

        $customerMediaId = $this->asUser($manager)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->create('customer.pdf', 40, 'application/pdf'),
                'entity_type' => 'project',
                'entity_id' => $project->id,
                'visibility' => MediaVisibility::Customer->value,
            ])
            ->assertCreated()
            ->json('data.id');

        $internalMediaId = $this->asUser($manager)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->create('internal.pdf', 40, 'application/pdf'),
                'entity_type' => 'project',
                'entity_id' => $project->id,
                'visibility' => MediaVisibility::Internal->value,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($customer)
            ->get('/api/media/'.$customerMediaId.'/download')
            ->assertOk();

        $this->asUser($customer)
            ->get('/api/media/'.$internalMediaId.'/download')
            ->assertForbidden();

        $supplierUser = User::factory()->create(['role' => UserRole::Supplier->value]);
        $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id]);

        $supplierMediaId = $this->asUser($manager)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->create('supplier.pdf', 40, 'application/pdf'),
                'entity_type' => 'supplier',
                'entity_id' => $supplier->id,
                'visibility' => MediaVisibility::Supplier->value,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($supplierUser)
            ->get('/api/media/'.$supplierMediaId.'/download')
            ->assertOk();

        $this->asUser($customer)
            ->get('/api/media/'.$supplierMediaId.'/download')
            ->assertForbidden();
    }

    public function test_delete_remove_file_and_record(): void
    {
        ['manager' => $manager, 'task' => $task] = $this->seededTask();

        $id = $this->asUser($manager)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->create('gone.pdf', 30, 'application/pdf'),
                'entity_type' => 'task',
                'entity_id' => $task->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $path = Media::query()->findOrFail($id)->path;

        $this->asUser($manager)
            ->deleteJson('/api/media/'.$id)
            ->assertOk();

        $this->assertNull(Media::query()->find($id));
        Storage::disk('local')->assertMissing($path);
    }

    public function test_replace_updates_file_content(): void
    {
        ['manager' => $manager, 'task' => $task] = $this->seededTask();

        $id = $this->asUser($manager)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->create('v1.pdf', 30, 'application/pdf'),
                'entity_type' => 'task',
                'entity_id' => $task->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $oldPath = Media::query()->findOrFail($id)->path;

        $updated = $this->asUser($manager)
            ->post('/api/media/'.$id, [
                'file' => UploadedFile::fake()->create('v2.pdf', 50, 'application/pdf'),
            ])
            ->assertOk()
            ->assertJsonPath('data.original_name', 'v2.pdf')
            ->json('data');

        $this->assertSame($id, $updated['id']);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists(Media::query()->findOrFail($id)->path);
    }

    public function test_duplicate_copies_file_for_same_entity(): void
    {
        ['manager' => $manager, 'task' => $task] = $this->seededTask();

        $id = $this->asUser($manager)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->create('master.pdf', 30, 'application/pdf'),
                'entity_type' => 'task',
                'entity_id' => $task->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $copy = $this->asUser($manager)
            ->postJson('/api/media/'.$id.'/duplicate')
            ->assertCreated()
            ->json('data');

        $this->assertNotSame($id, $copy['id']);
        $this->assertSame('task', $copy['entity_type']);
        $this->assertSame($task->id, $copy['entity_id']);
        $this->assertSame(2, Media::query()->where('owner_type', 'task')->where('owner_id', $task->id)->count());
        Storage::disk('local')->assertExists(Media::query()->findOrFail($copy['id'])->path);
    }

    public function test_rejects_invalid_type_and_unauthorized_entity(): void
    {
        ['manager' => $manager, 'task' => $task] = $this->seededTask();
        $otherManager = User::factory()->accountManager()->create();

        $this->asUser($manager)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
                'entity_type' => 'task',
                'entity_id' => $task->id,
            ])
            ->assertUnprocessable();

        $foreign = Project::factory()->create([
            'account_manager_id' => $otherManager->id,
        ]);

        $this->asUser($manager)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->create('ok.pdf', 10, 'application/pdf'),
                'entity_type' => 'project',
                'entity_id' => $foreign->id,
            ])
            ->assertUnprocessable();
    }

    public function test_list_filters_by_entity_attachment(): void
    {
        ['manager' => $manager, 'task' => $task, 'project' => $project] = $this->seededTask();

        $this->asUser($manager)->post('/api/media', [
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            'entity_type' => 'task',
            'entity_id' => $task->id,
        ])->assertCreated();

        $this->asUser($manager)->post('/api/media', [
            'file' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
            'entity_type' => 'project',
            'entity_id' => $project->id,
        ])->assertCreated();

        $this->asUser($manager)
            ->getJson('/api/media?entity_type=task&entity_id='.$task->id)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.entity_type', 'task');
    }

    public function test_package_and_supplier_portfolio_accept_media(): void
    {
        $owner = User::factory()->owner()->create();
        $package = Package::factory()->create();
        $supplier = Supplier::factory()->create();
        $portfolioItem = SupplierPortfolioItem::factory()->create([
            'supplier_id' => $supplier->id,
        ]);

        $this->asUser($owner)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->image('pkg.png', 20, 20),
                'entity_type' => 'package',
                'entity_id' => $package->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.entity_type', 'package')
            ->assertJsonPath('data.is_primary', true);

        $this->asUser($owner)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->image('work.png', 20, 20),
                'entity_type' => 'supplier_portfolio_item',
                'entity_id' => $portfolioItem->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.entity_type', 'supplier_portfolio_item')
            ->assertJsonPath('data.visibility', MediaVisibility::Supplier->value);
    }

    public function test_set_primary_and_reorder_media(): void
    {
        $owner = User::factory()->owner()->create();
        $supplier = Supplier::factory()->create();

        $first = $this->asUser($owner)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->image('a.png', 10, 10),
                'entity_type' => 'supplier',
                'entity_id' => $supplier->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $second = $this->asUser($owner)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->image('b.png', 10, 10),
                'entity_type' => 'supplier',
                'entity_id' => $supplier->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertTrue((bool) Media::query()->findOrFail($first)->is_primary);
        $this->assertFalse((bool) Media::query()->findOrFail($second)->is_primary);

        $this->asUser($owner)
            ->postJson('/api/media/'.$second.'/primary')
            ->assertOk()
            ->assertJsonPath('data.is_primary', true);

        $this->assertFalse((bool) Media::query()->findOrFail($first)->fresh()->is_primary);
        $this->assertTrue((bool) Media::query()->findOrFail($second)->fresh()->is_primary);

        $this->asUser($owner)
            ->postJson('/api/media/reorder', [
                'entity_type' => 'supplier',
                'entity_id' => $supplier->id,
                'ordered_ids' => [$second, $first],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $second);

        $this->assertSame(1, (int) Media::query()->findOrFail($second)->sort_order);
        $this->assertSame(2, (int) Media::query()->findOrFail($first)->sort_order);
    }

    public function test_supplier_cannot_mutate_another_suppliers_portfolio_media(): void
    {
        $supplierUserA = User::factory()->create(['role' => UserRole::Supplier->value]);
        $supplierA = Supplier::factory()->create(['user_id' => $supplierUserA->id]);
        $portfolioA = SupplierPortfolioItem::factory()->create(['supplier_id' => $supplierA->id]);

        $supplierUserB = User::factory()->create(['role' => UserRole::Supplier->value]);
        Supplier::factory()->create(['user_id' => $supplierUserB->id]);

        $mediaId = $this->asUser(User::factory()->owner()->create())
            ->post('/api/media', [
                'file' => UploadedFile::fake()->image('a.png', 10, 10),
                'entity_type' => 'supplier_portfolio_item',
                'entity_id' => $portfolioA->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($supplierUserB)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->image('hack.png', 10, 10),
                'entity_type' => 'supplier_portfolio_item',
                'entity_id' => $portfolioA->id,
            ])
            ->assertUnprocessable();

        $this->asUser($supplierUserB)
            ->deleteJson('/api/media/'.$mediaId)
            ->assertForbidden();

        $this->asUser($supplierUserA)
            ->post('/api/media', [
                'file' => UploadedFile::fake()->image('own.png', 10, 10),
                'entity_type' => 'supplier_portfolio_item',
                'entity_id' => $portfolioA->id,
            ])
            ->assertCreated();
    }
}
